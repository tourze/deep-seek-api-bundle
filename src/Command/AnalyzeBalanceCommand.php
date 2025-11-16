<?php

namespace Tourze\DeepSeekApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\DeepSeekApiBundle\Command\Helper\BalanceDataFormatter;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceHistoryRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceRepository;

#[AsCommand(
    name: 'deep-seek:analyze-balance',
    description: 'Analyze balance history and consumption trends',
)]
class AnalyzeBalanceCommand extends Command
{
    public function __construct(
        private readonly DeepSeekApiKeyRepository $apiKeyRepository,
        private readonly DeepSeekBalanceRepository $balanceRepository,
        private readonly DeepSeekBalanceHistoryRepository $balanceHistoryRepository,
        private readonly BalanceDataFormatter $formatter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('api-key', InputArgument::OPTIONAL, 'API key to analyze (analyzes all if not specified)')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to analyze', '7')
            ->addOption('interval', 'i', InputOption::VALUE_REQUIRED, 'Analysis interval (hourly, daily, weekly, monthly)', 'daily')
            ->addOption('currency', 'c', InputOption::VALUE_REQUIRED, 'Currency to analyze (CNY, USD)', 'CNY')
            ->addOption('show-alerts', null, InputOption::VALUE_NONE, 'Show balance alerts')
            ->addOption('export', null, InputOption::VALUE_REQUIRED, 'Export to CSV file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('DeepSeek Balance Analysis');

        try {
            $params = $this->extractInputParameters($input);
            $this->performAnalysis($io, $params);
            $this->performOptionalActions($io, $params);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Analysis failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array{apiKeyValue: string|null, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, currency: string, interval: string, showAlerts: bool, exportFile: string|null}
     */
    private function extractInputParameters(InputInterface $input): array
    {
        $apiKeyValue = $input->getArgument('api-key');
        $apiKeyValue = is_string($apiKeyValue) ? $apiKeyValue : null;

        $daysOption = $input->getOption('days');
        $days = is_numeric($daysOption) ? (int) $daysOption : 7;
        $validDays = $days > 0 ? $days : 7;

        $intervalOption = $input->getOption('interval');
        $interval = is_string($intervalOption) ? $intervalOption : 'daily';

        $currencyOption = $input->getOption('currency');
        $currency = is_string($currencyOption) ? $currencyOption : 'CNY';

        $showAlerts = (bool) $input->getOption('show-alerts');

        $exportFileOption = $input->getOption('export');
        $exportFile = is_string($exportFileOption) ? $exportFileOption : null;

        return [
            'apiKeyValue' => $apiKeyValue,
            'startDate' => new \DateTimeImmutable(sprintf('-%d days', $validDays)),
            'endDate' => new \DateTimeImmutable(),
            'currency' => $currency,
            'interval' => $interval,
            'showAlerts' => $showAlerts,
            'exportFile' => $exportFile,
        ];
    }

    /**
     * @param array{apiKeyValue: string|null, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, currency: string, interval: string, showAlerts: bool, exportFile: string|null} $params
     */
    private function performAnalysis(SymfonyStyle $io, array $params): void
    {
        $apiKeyValue = $params['apiKeyValue'];

        if (null !== $apiKeyValue && '' !== $apiKeyValue) {
            $this->analyzeSpecificApiKey($io, $params);
        } else {
            $this->analyzeAllKeys($io, $params['startDate'], $params['endDate'], $params['currency'], $params['interval']);
        }
    }

    /**
     * @param array{apiKeyValue: string|null, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, currency: string, interval: string, showAlerts: bool, exportFile: string|null} $params
     */
    private function analyzeSpecificApiKey(SymfonyStyle $io, array $params): void
    {
        $apiKeyValue = $params['apiKeyValue'];
        if (null === $apiKeyValue) {
            throw new \RuntimeException('API key value is null');
        }

        $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyValue);

        if (null === $apiKey) {
            throw new \RuntimeException('API key not found');
        }

        $this->analyzeApiKey(
            $io,
            $apiKey,
            $params['startDate'],
            $params['endDate'],
            $params['currency'],
            $params['interval']
        );
    }

    /**
     * @param array{apiKeyValue: string|null, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable, currency: string, interval: string, showAlerts: bool, exportFile: string|null} $params
     */
    private function performOptionalActions(SymfonyStyle $io, array $params): void
    {
        if ($params['showAlerts']) {
            $this->showBalanceAlerts($io);
        }

        if (null !== $params['exportFile'] && '' !== $params['exportFile']) {
            $this->exportToCSV(
                $io,
                $params['exportFile'],
                $params['startDate'],
                $params['endDate'],
                $params['apiKeyValue']
            );
        }
    }

    private function analyzeApiKey(
        SymfonyStyle $io,
        DeepSeekApiKey $apiKey,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $currency,
        string $interval,
    ): void {
        $io->section(sprintf('Analyzing API Key: %s', $apiKey->getName()));

        $currentBalance = $this->balanceRepository->findLatestByApiKey($apiKey);
        $this->formatter->displayCurrentBalance($io, $currentBalance);

        $trend = $this->balanceHistoryRepository->getBalanceTrend(
            $apiKey,
            $currency,
            $startDate,
            $endDate,
            $interval
        );
        $this->formatter->displayBalanceTrend($io, $trend);

        $consumption = $this->balanceHistoryRepository->getConsumptionStatistics(
            $apiKey,
            $startDate,
            $endDate
        );
        $this->formatter->displayConsumptionStatistics($io, $consumption);
        $this->formatter->displayBalanceExhaustionPrediction($io, $currentBalance, $consumption);
    }

    private function analyzeAllKeys(
        SymfonyStyle $io,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $currency,
        string $interval,
    ): void {
        $apiKeys = $this->apiKeyRepository->findActiveKeys();

        if ([] === $apiKeys) {
            $io->warning('No active API keys found');

            return;
        }

        $io->section('Global Balance Overview');
        $this->displayGlobalBalanceOverview($io);
        $this->displayApiKeysSummary($io, $apiKeys, $startDate, $endDate, $currency);
    }

    private function displayGlobalBalanceOverview(SymfonyStyle $io): void
    {
        $totalByCurrency = $this->balanceRepository->getTotalBalanceByCurrency();
        if ([] === $totalByCurrency) {
            return;
        }

        $rows = [];
        foreach ($totalByCurrency as $curr => $total) {
            $currencyCode = is_string($curr) ? $curr : 'Unknown';
            $totalAmount = is_numeric($total) ? (float) $total : 0.0;
            $rows[] = [$currencyCode, number_format($totalAmount, 4)];
        }
        $io->table(['Currency', 'Total Balance'], $rows);
    }

    /**
     * @param DeepSeekApiKey[] $apiKeys
     */
    private function displayApiKeysSummary(
        SymfonyStyle $io,
        array $apiKeys,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $currency,
    ): void {
        $summaryRows = [];
        foreach ($apiKeys as $apiKey) {
            $summaryRows[] = $this->buildApiKeySummaryRow($apiKey, $startDate, $endDate, $currency);
        }

        $io->table(
            ['API Key', 'Current Balance', 'Consumed', 'Topped Up', 'Records'],
            $summaryRows
        );
    }

    /**
     * @return array{string, string, string, string, int}
     */
    private function buildApiKeySummaryRow(
        DeepSeekApiKey $apiKey,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $currency,
    ): array {
        $balance = $this->balanceRepository->findLatestByApiKey($apiKey);
        $history = $this->balanceHistoryRepository->findByDateRange(
            $startDate,
            $endDate,
            $apiKey,
            $currency
        );

        [$consumption, $topup] = $this->calculateConsumptionAndTopup($history);

        return [
            $apiKey->getName(),
            null !== $balance ? sprintf('%.4f %s', $balance->getTotalBalanceAsFloat(), $balance->getCurrency()) : 'N/A',
            sprintf('%.4f', $consumption),
            sprintf('%.4f', $topup),
            count($history),
        ];
    }

    /**
     * @param DeepSeekBalanceHistory[] $history
     * @return array{float, float}
     */
    private function calculateConsumptionAndTopup(array $history): array
    {
        $consumption = 0.0;
        $topup = 0.0;

        foreach ($history as $record) {
            [$recordConsumption, $recordTopup] = $this->extractConsumptionAndTopupFromRecord($record);
            $consumption += $recordConsumption;
            $topup += $recordTopup;
        }

        return [$consumption, $topup];
    }

    /**
     * @return array{float, float}
     */
    private function extractConsumptionAndTopupFromRecord(DeepSeekBalanceHistory $record): array
    {
        $changeType = $record->getChangeType();
        $balanceChange = $record->getBalanceChangeAsFloat();

        if (!is_string($changeType) || !is_numeric($balanceChange)) {
            return [0.0, 0.0];
        }

        if ('decrease' === $changeType) {
            return [abs((float) $balanceChange), 0.0];
        }

        if ('increase' === $changeType) {
            return [0.0, (float) $balanceChange];
        }

        return [0.0, 0.0];
    }

    private function showBalanceAlerts(SymfonyStyle $io): void
    {
        $io->section('Balance Alerts');

        $alerts = $this->balanceHistoryRepository->getBalanceAlerts(10.0);

        if ([] === $alerts) {
            $io->success('No balance alerts at this time');

            return;
        }

        $rows = [];
        foreach ($alerts as $alert) {
            if (!is_array($alert)) {
                continue;
            }

            $apiKey = is_string($alert['api_key'] ?? '') ? $alert['api_key'] : 'Unknown';
            $currency = is_string($alert['currency'] ?? '') ? $alert['currency'] : 'N/A';
            $currentBalance = is_numeric($alert['current_balance'] ?? 0) ? (float) $alert['current_balance'] : 0.0;
            $threshold = is_numeric($alert['threshold'] ?? 0) ? (float) $alert['threshold'] : 0.0;
            $alertLevel = is_string($alert['alert_level'] ?? '') ? $alert['alert_level'] : 'Unknown';
            $recordedAt = is_string($alert['recorded_at'] ?? '') ? $alert['recorded_at'] : 'N/A';

            $rows[] = [
                $apiKey,
                $currency,
                sprintf('%.4f', $currentBalance),
                sprintf('%.4f', $threshold),
                $alertLevel,
                $recordedAt,
            ];
        }

        $io->table(
            ['API Key', 'Currency', 'Balance', 'Threshold', 'Level', 'Last Update'],
            $rows
        );
    }

    private function exportToCSV(
        SymfonyStyle $io,
        string $filename,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $apiKeyValue,
    ): void {
        $records = [];

        if (is_string($apiKeyValue) && '' !== $apiKeyValue) {
            $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyValue);
            if (null !== $apiKey) {
                $records = $this->balanceHistoryRepository->findByDateRange($startDate, $endDate, $apiKey);
            }
        } else {
            $records = $this->balanceHistoryRepository->findByDateRange($startDate, $endDate);
        }

        if ([] === $records) {
            $io->warning('No records to export');

            return;
        }

        $file = fopen($filename, 'w');
        if (false === $file) {
            $io->error(sprintf('Could not open file %s for writing', $filename));

            return;
        }

        // 写入CSV头
        fputcsv($file, [
            'Date',
            'API Key',
            'Currency',
            'Total Balance',
            'Granted Balance',
            'Topped Up Balance',
            'Change',
            'Change Type',
        ], ',', '"', '\\');

        // 写入数据
        foreach ($records as $record) {
            fputcsv($file, [
                $record->getRecordTime()->format('Y-m-d H:i:s'),
                $record->getApiKey()?->getName() ?? 'Unknown',
                $record->getCurrency(),
                $record->getTotalBalance(),
                $record->getGrantedBalance(),
                $record->getToppedUpBalance(),
                $record->getBalanceChange() ?? '0',
                $record->getChangeType() ?? 'N/A',
            ], ',', '"', '\\');
        }

        fclose($file);

        $io->success(sprintf('Data exported to %s', $filename));
    }
}
