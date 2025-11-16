<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\DeepSeekApiBundle\Command\Helper\ApiKeyStatisticsFormatter;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekModelRepository;

#[AsCommand(
    name: 'deep-seek:api-key',
    description: 'Manage DeepSeek API keys',
)]
class ManageApiKeyCommand extends Command
{
    public function __construct(
        private readonly DeepSeekApiKeyRepository $apiKeyRepository,
        private readonly DeepSeekModelRepository $modelRepository,
        private readonly DeepSeekBalanceRepository $balanceRepository,
        private readonly ApiKeyStatisticsFormatter $statisticsFormatter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: list, add, enable, disable, delete, stats')
            ->addArgument('api-key', InputArgument::OPTIONAL, 'API key value (for add, enable, disable, delete actions)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the API key (for add action)')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Priority for the API key (for add action)', '0')
            ->addOption('show-keys', null, InputOption::VALUE_NONE, 'Show full API keys (for list action)')
            ->addOption('reset-invalid', null, InputOption::VALUE_NONE, 'Reset all invalid keys to valid state')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $actionRaw = $input->getArgument('action');
        $apiKeyValueRaw = $input->getArgument('api-key');

        // Type safety for Console Input parameters
        assert(is_string($actionRaw), 'action argument must be string');
        assert(is_string($apiKeyValueRaw) || is_null($apiKeyValueRaw), 'api-key argument must be string or null');

        $action = $actionRaw;
        $apiKeyValue = $apiKeyValueRaw;

        try {
            switch ($action) {
                case 'list':
                    $showKeysRaw = $input->getOption('show-keys');
                    assert(is_bool($showKeysRaw) || is_null($showKeysRaw), 'show-keys option must be bool or null');
                    $showKeys = (bool) $showKeysRaw;

                    return $this->listApiKeys($io, $showKeys);

                case 'add':
                    if (null === $apiKeyValue || '' === $apiKeyValue) {
                        $io->error('API key value is required for add action');

                        return Command::FAILURE;
                    }

                    $nameRaw = $input->getOption('name');
                    $priorityRaw = $input->getOption('priority');
                    assert(is_string($nameRaw) || is_null($nameRaw), 'name option must be string or null');
                    assert(is_string($priorityRaw) || is_null($priorityRaw), 'priority option must be string or null');

                    $name = $nameRaw;
                    $priority = is_string($priorityRaw) ? (int) $priorityRaw : 0;

                    return $this->addApiKey($io, $apiKeyValue, $name, $priority);

                case 'enable':
                    if (null === $apiKeyValue || '' === $apiKeyValue) {
                        $io->error('API key value is required for enable action');

                        return Command::FAILURE;
                    }

                    return $this->enableApiKey($io, $apiKeyValue);

                case 'disable':
                    if (null === $apiKeyValue || '' === $apiKeyValue) {
                        $io->error('API key value is required for disable action');

                        return Command::FAILURE;
                    }

                    return $this->disableApiKey($io, $apiKeyValue);

                case 'delete':
                    if (null === $apiKeyValue || '' === $apiKeyValue) {
                        $io->error('API key value is required for delete action');

                        return Command::FAILURE;
                    }

                    return $this->deleteApiKey($io, $apiKeyValue);

                case 'stats':
                    return $this->showStatistics($io, $apiKeyValue);

                default:
                    $io->error(sprintf('Unknown action: %s', $action));
                    $io->note('Available actions: list, add, enable, disable, delete, stats');

                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Command failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function listApiKeys(SymfonyStyle $io, bool $showKeys): int
    {
        /** @var DeepSeekApiKey[] $apiKeys */
        $apiKeys = $this->apiKeyRepository->findAll();

        if ([] === $apiKeys) {
            $io->warning('No API keys found');

            return Command::SUCCESS;
        }

        $this->displayApiKeysTable($io, $apiKeys, $showKeys);
        $this->displayApiKeysSummary($io);

        return Command::SUCCESS;
    }

    /**
     * @param DeepSeekApiKey[] $apiKeys
     */
    private function displayApiKeysTable(SymfonyStyle $io, array $apiKeys, bool $showKeys): void
    {
        $rows = array_map(
            fn (DeepSeekApiKey $apiKey) => $this->buildApiKeyTableRow($apiKey, $showKeys),
            $apiKeys
        );

        $io->table(
            ['ID', 'Name', 'API Key', 'Status', 'Priority', 'Usage', 'Last Used', 'Models', 'Balance'],
            $rows
        );
    }

    /**
     * @return array{int, string, string, string, int, int, string, int, string}
     */
    private function buildApiKeyTableRow(DeepSeekApiKey $apiKey, bool $showKeys): array
    {
        $id = $apiKey->getId();
        assert(null !== $id, 'API key ID cannot be null');

        return [
            $id,
            $apiKey->getName(),
            $this->formatApiKeyDisplay($apiKey, $showKeys),
            $this->formatApiKeyStatus($apiKey),
            $apiKey->getPriority(),
            $apiKey->getUsageCount(),
            $this->formatLastUsedTime($apiKey),
            $this->countApiKeyModels($apiKey),
            $this->formatApiKeyBalance($apiKey),
        ];
    }

    private function formatApiKeyDisplay(DeepSeekApiKey $apiKey, bool $showKeys): string
    {
        $keyValue = $apiKey->getApiKey();

        return $showKeys
            ? $keyValue
            : substr($keyValue, 0, 8) . '...' . substr($keyValue, -4);
    }

    private function formatApiKeyStatus(DeepSeekApiKey $apiKey): string
    {
        $status = [];

        $status[] = $apiKey->isActive()
            ? '<info>Active</info>'
            : '<comment>Inactive</comment>';

        $status[] = $apiKey->isValid()
            ? '<info>Valid</info>'
            : '<error>Invalid</error>';

        return implode(', ', $status);
    }

    private function formatLastUsedTime(DeepSeekApiKey $apiKey): string
    {
        $lastUseTime = $apiKey->getLastUseTime();

        return null !== $lastUseTime
            ? $lastUseTime->format('Y-m-d H:i:s')
            : 'Never';
    }

    private function countApiKeyModels(DeepSeekApiKey $apiKey): int
    {
        $models = $this->modelRepository->findByApiKey($apiKey);

        return count($models);
    }

    private function formatApiKeyBalance(DeepSeekApiKey $apiKey): string
    {
        $balance = $this->balanceRepository->findLatestByApiKey($apiKey);

        if (null === $balance) {
            return 'N/A';
        }

        $totalBalance = $balance->getTotalBalanceAsFloat();
        $currency = $balance->getCurrency();

        return sprintf('%.4f %s', $totalBalance, $currency);
    }

    private function displayApiKeysSummary(SymfonyStyle $io): void
    {
        $stats = $this->apiKeyRepository->getStatistics();
        $io->section('Summary');
        $io->listing([
            sprintf('Total: %d', is_int($stats['total']) ? $stats['total'] : 0),
            sprintf('Active: %d', is_int($stats['active']) ? $stats['active'] : 0),
            sprintf('Valid: %d', is_int($stats['valid']) ? $stats['valid'] : 0),
            sprintf('Usable: %d', is_int($stats['usable']) ? $stats['usable'] : 0),
        ]);
    }

    private function addApiKey(SymfonyStyle $io, string $apiKeyValue, ?string $name, int $priority): int
    {
        $existingKey = $this->apiKeyRepository->findByApiKey($apiKeyValue);

        if (null !== $existingKey) {
            $io->error('API key already exists');

            return Command::FAILURE;
        }

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey($apiKeyValue);
        $apiKey->setName($name ?? sprintf('Key-%s', substr($apiKeyValue, -4)));
        $apiKey->setPriority($priority);

        $this->apiKeyRepository->save($apiKey, true);

        $io->success(sprintf('API key "%s" added successfully', $apiKey->getName()));

        return Command::SUCCESS;
    }

    private function enableApiKey(SymfonyStyle $io, string $apiKeyValue): int
    {
        $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyValue);

        if (null === $apiKey) {
            $io->error('API key not found');

            return Command::FAILURE;
        }

        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);
        $this->apiKeyRepository->save($apiKey, true);

        $io->success(sprintf('API key "%s" enabled', $apiKey->getName()));

        return Command::SUCCESS;
    }

    private function disableApiKey(SymfonyStyle $io, string $apiKeyValue): int
    {
        $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyValue);

        if (null === $apiKey) {
            $io->error('API key not found');

            return Command::FAILURE;
        }

        $apiKey->setIsActive(false);
        $this->apiKeyRepository->save($apiKey, true);

        $io->success(sprintf('API key "%s" disabled', $apiKey->getName()));

        return Command::SUCCESS;
    }

    private function deleteApiKey(SymfonyStyle $io, string $apiKeyValue): int
    {
        $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyValue);

        if (null === $apiKey) {
            $io->error('API key not found');

            return Command::FAILURE;
        }

        $io->warning(sprintf('This will delete API key "%s" and all associated data', $apiKey->getName()));

        if (!$io->confirm('Are you sure you want to continue?', false)) {
            $io->comment('Operation cancelled');

            return Command::SUCCESS;
        }

        $this->apiKeyRepository->remove($apiKey, true);

        $io->success(sprintf('API key "%s" deleted', $apiKey->getName()));

        return Command::SUCCESS;
    }

    private function showStatistics(SymfonyStyle $io, ?string $apiKeyValue): int
    {
        if (null !== $apiKeyValue) {
            return $this->showSingleKeyStatistics($io, $apiKeyValue);
        }

        return $this->showGlobalStatistics($io);
    }

    private function showSingleKeyStatistics(SymfonyStyle $io, string $apiKeyValue): int
    {
        $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyValue);
        if (null === $apiKey) {
            $io->error('API key not found');

            return Command::FAILURE;
        }

        $io->title(sprintf('Statistics for API Key: %s', $apiKey->getName()));
        $this->statisticsFormatter->displayBasicKeyInformation($io, $apiKey);
        $this->statisticsFormatter->displayKeyModels($io, $apiKey);
        $this->statisticsFormatter->displayKeyBalance($io, $apiKey);
        $this->statisticsFormatter->displayKeyApiLogs($io, $apiKey);

        return Command::SUCCESS;
    }

    private function showGlobalStatistics(SymfonyStyle $io): int
    {
        $io->title('Global Statistics');
        $this->statisticsFormatter->displayGlobalKeyStatistics($io);
        $this->statisticsFormatter->displayGlobalModelStatistics($io);
        $this->statisticsFormatter->displayGlobalBalanceStatistics($io);
        $this->statisticsFormatter->displayGlobalApiStatistics($io);
        $this->statisticsFormatter->displayApiKeyPerformance($io);

        return Command::SUCCESS;
    }
}
