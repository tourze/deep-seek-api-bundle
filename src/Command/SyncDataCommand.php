<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiLogRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceRepository;
use Tourze\DeepSeekApiBundle\Service\DeepSeekService;

#[AsCommand(
    name: 'deep-seek:sync-data',
    description: 'Sync models and balances for all API keys',
)]
class SyncDataCommand extends Command
{
    public function __construct(
        private readonly DeepSeekService $deepSeekService,
        private readonly DeepSeekApiKeyRepository $apiKeyRepository,
        private readonly DeepSeekBalanceRepository $balanceRepository,
        private readonly DeepSeekApiLogRepository $apiLogRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force-all', 'f', InputOption::VALUE_NONE, 'Force sync all keys regardless of last sync time')
            ->addOption('models-only', null, InputOption::VALUE_NONE, 'Only sync models')
            ->addOption('balance-only', null, InputOption::VALUE_NONE, 'Only sync balances')
            ->addOption('clean-old-data', null, InputOption::VALUE_NONE, 'Clean old logs and balance records after sync')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('DeepSeek Data Synchronization');

        // Type safety for Console Input parameters
        $forceAllRaw = $input->getOption('force-all');
        $modelsOnlyRaw = $input->getOption('models-only');
        $balanceOnlyRaw = $input->getOption('balance-only');
        $cleanOldDataRaw = $input->getOption('clean-old-data');

        assert(is_bool($forceAllRaw) || is_null($forceAllRaw), 'force-all option must be bool or null');
        assert(is_bool($modelsOnlyRaw) || is_null($modelsOnlyRaw), 'models-only option must be bool or null');
        assert(is_bool($balanceOnlyRaw) || is_null($balanceOnlyRaw), 'balance-only option must be bool or null');
        assert(is_bool($cleanOldDataRaw) || is_null($cleanOldDataRaw), 'clean-old-data option must be bool or null');

        $forceAll = (bool) $forceAllRaw;
        $modelsOnly = (bool) $modelsOnlyRaw;
        $balanceOnly = (bool) $balanceOnlyRaw;
        $cleanOldData = (bool) $cleanOldDataRaw;

        if (true === $modelsOnly && true === $balanceOnly) {
            $io->error('Cannot use --models-only and --balance-only together');

            return Command::FAILURE;
        }

        try {
            $stats = $this->apiKeyRepository->getStatistics();
            $totalRaw = $stats['total'] ?? 0;
            $activeRaw = $stats['active'] ?? 0;
            $validRaw = $stats['valid'] ?? 0;

            $totalCount = is_numeric($totalRaw) ? (int) $totalRaw : 0;
            $activeCount = is_numeric($activeRaw) ? (int) $activeRaw : 0;
            $validCount = is_numeric($validRaw) ? (int) $validRaw : 0;

            $io->info(sprintf(
                'Found %d API keys (%d active, %d valid)',
                $totalCount,
                $activeCount,
                $validCount
            ));

            if (false === $balanceOnly) {
                $io->section('Syncing Models');
                $this->syncModels($io, $forceAll);
            }

            if (false === $modelsOnly) {
                $io->section('Syncing Balances');
                $this->syncBalances($io, $forceAll);
            }

            if (true === $cleanOldData) {
                $io->section('Cleaning Old Data');
                $this->cleanOldData($io);
            }

            $io->success('Data synchronization completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Synchronization failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function syncModels(SymfonyStyle $io, bool $forceAll): void
    {
        if ($forceAll) {
            $apiKeys = $this->apiKeyRepository->findActiveKeys();
            $io->note(sprintf('Force syncing models for %d active keys', count($apiKeys)));
        } else {
            $apiKeys = $this->apiKeyRepository->findKeysNeedingModelsSync();
            $io->note(sprintf('Found %d keys needing models sync', count($apiKeys)));
        }

        if ([] === $apiKeys) {
            $io->info('No API keys need models sync');

            return;
        }

        $progressBar = $io->createProgressBar(count($apiKeys));
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($apiKeys as $apiKey) {
            try {
                $models = $this->deepSeekService->listModels($apiKey->getApiKey());

                if ($forceAll) {
                    $apiKey->setLastModelsSyncTime(new \DateTimeImmutable('-25 hours'));
                    $this->apiKeyRepository->save($apiKey, true);
                }

                $this->deepSeekService->listModelsForAllKeys();

                ++$successCount;
                $progressBar->setMessage(sprintf('Synced models for %s', $apiKey->getName() ?? 'Unknown'));
            } catch (\Exception $e) {
                ++$errorCount;
                $progressBar->setMessage(sprintf('Failed for %s: %s', $apiKey->getName() ?? 'Unknown', $e->getMessage()));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->table(
            ['Result', 'Count'],
            [
                ['Success', $successCount],
                ['Errors', $errorCount],
            ]
        );
    }

    private function syncBalances(SymfonyStyle $io, bool $forceAll): void
    {
        $apiKeys = $this->getApiKeysForBalanceSync($forceAll);
        $this->displayBalanceSyncInfo($io, $apiKeys, $forceAll);

        if ([] === $apiKeys) {
            $io->info('No API keys need balance sync');

            return;
        }

        $syncResult = $this->performBalanceSync($io, $apiKeys, $forceAll);
        $this->displaySyncResults($io, $syncResult);
        $this->displayTotalBalances($io);
    }

    /**
     * @return DeepSeekApiKey[]
     */
    private function getApiKeysForBalanceSync(bool $forceAll): array
    {
        return $forceAll
            ? $this->apiKeyRepository->findActiveKeys()
            : $this->apiKeyRepository->findKeysNeedingBalanceSync();
    }

    /**
     * @param DeepSeekApiKey[] $apiKeys
     */
    private function displayBalanceSyncInfo(SymfonyStyle $io, array $apiKeys, bool $forceAll): void
    {
        $message = $forceAll
            ? sprintf('Force syncing balances for %d active keys', count($apiKeys))
            : sprintf('Found %d keys needing balance sync', count($apiKeys));

        $io->note($message);
    }

    /**
     * @param DeepSeekApiKey[] $apiKeys
     * @return array{success: int, error: int}
     */
    private function performBalanceSync(SymfonyStyle $io, array $apiKeys, bool $forceAll): array
    {
        $progressBar = $io->createProgressBar(count($apiKeys));
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($apiKeys as $apiKey) {
            $result = $this->syncSingleBalance($apiKey, $forceAll);

            if ($result['success']) {
                ++$successCount;
                $progressBar->setMessage(sprintf('Synced balance for %s', $apiKey->getName() ?? 'Unknown'));
            } else {
                ++$errorCount;
                $errorMessage = $result['error'] ?? 'Unknown error';
                $progressBar->setMessage(sprintf('Failed for %s: %s', $apiKey->getName() ?? 'Unknown', $errorMessage));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        return ['success' => $successCount, 'error' => $errorCount];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function syncSingleBalance(DeepSeekApiKey $apiKey, bool $forceAll): array
    {
        try {
            if ($forceAll) {
                $apiKey->setLastBalanceSyncTime(new \DateTimeImmutable('-2 hours'));
                $this->apiKeyRepository->save($apiKey, true);
            }

            $this->deepSeekService->getBalance($apiKey->getApiKey());

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array{success: int, error: int} $syncResult
     */
    private function displaySyncResults(SymfonyStyle $io, array $syncResult): void
    {
        $io->table(
            ['Result', 'Count'],
            [
                ['Success', $syncResult['success']],
                ['Errors', $syncResult['error']],
            ]
        );
    }

    private function displayTotalBalances(SymfonyStyle $io): void
    {
        $totals = $this->balanceRepository->getTotalBalanceByCurrency();

        if ([] === $totals) {
            return;
        }

        $io->section('Total Balances');
        $rows = $this->formatTotalBalancesForDisplay($totals);
        $io->table(['Currency', 'Total Balance'], $rows);
    }

    /**
     * @param array<string, mixed> $totals
     * @return array<array{string, string}>
     */
    private function formatTotalBalancesForDisplay(array $totals): array
    {
        $rows = [];
        foreach ($totals as $currency => $total) {
            $currencyCode = is_string($currency) ? $currency : 'Unknown';
            $totalAmount = is_numeric($total) ? (float) $total : 0.0;
            $rows[] = [$currencyCode, number_format($totalAmount, 4)];
        }

        return $rows;
    }

    private function cleanOldData(SymfonyStyle $io): void
    {
        $deletedLogs = $this->apiLogRepository->cleanOldRecords(7);
        $io->info(sprintf('Deleted %d old API log records (older than 7 days)', $deletedLogs));

        $deletedBalances = $this->balanceRepository->cleanOldRecords(30);
        $io->info(sprintf('Deleted %d old balance records (older than 30 days)', $deletedBalances));
    }
}
