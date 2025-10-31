<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Command\Helper;

use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiLogRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekModelRepository;

/**
 * API密钥统计数据和详情格式化帮助类
 */
class ApiKeyStatisticsFormatter
{
    public function __construct(
        private readonly DeepSeekApiKeyRepository $apiKeyRepository,
        private readonly DeepSeekModelRepository $modelRepository,
        private readonly DeepSeekBalanceRepository $balanceRepository,
        private readonly DeepSeekApiLogRepository $apiLogRepository,
    ) {
    }

    public function displayGlobalKeyStatistics(SymfonyStyle $io): void
    {
        $stats = $this->apiKeyRepository->getStatistics();
        $io->section('API Keys');
        $io->listing([
            sprintf('Total: %d', is_int($stats['total']) ? $stats['total'] : 0),
            sprintf('Active: %d', is_int($stats['active']) ? $stats['active'] : 0),
            sprintf('Valid: %d', is_int($stats['valid']) ? $stats['valid'] : 0),
            sprintf('Usable: %d', is_int($stats['usable']) ? $stats['usable'] : 0),
            sprintf('Inactive: %d', is_int($stats['inactive']) ? $stats['inactive'] : 0),
            sprintf('Invalid: %d', is_int($stats['invalid']) ? $stats['invalid'] : 0),
        ]);
    }

    public function displayGlobalModelStatistics(SymfonyStyle $io): void
    {
        $modelStats = $this->modelRepository->getModelStatistics();
        $io->section('Models');
        $io->listing([
            sprintf('Total: %d', is_int($modelStats['total']) ? $modelStats['total'] : 0),
            sprintf('Active: %d', is_int($modelStats['active']) ? $modelStats['active'] : 0),
            sprintf('Chat Models: %d', is_int($modelStats['chat_models']) ? $modelStats['chat_models'] : 0),
            sprintf('Reasoner Models: %d', is_int($modelStats['reasoner_models']) ? $modelStats['reasoner_models'] : 0),
        ]);
    }

    public function displayGlobalBalanceStatistics(SymfonyStyle $io): void
    {
        $balanceStats = $this->balanceRepository->getBalanceStatistics();
        $io->section('Balances');
        $io->listing([
            sprintf('Total Records: %d', is_int($balanceStats['total_records']) ? $balanceStats['total_records'] : 0),
            sprintf('Average CNY: %.4f', is_float($balanceStats['average_balance_cny']) ? $balanceStats['average_balance_cny'] : 0.0),
            sprintf('Average USD: %.4f', is_float($balanceStats['average_balance_usd']) ? $balanceStats['average_balance_usd'] : 0.0),
            sprintf('Low Balance Count: %d', is_int($balanceStats['low_balance_count']) ? $balanceStats['low_balance_count'] : 0),
        ]);

        $totals = $this->balanceRepository->getTotalBalanceByCurrency();
        if ([] !== $totals) {
            $io->section('Total Balance by Currency');
            $rows = [];
            foreach ($totals as $currency => $total) {
                $totalValue = is_numeric($total) ? (float) $total : 0.0;
                $rows[] = [$currency, number_format($totalValue, 4)];
            }
            $io->table(['Currency', 'Total'], $rows);
        }
    }

    public function displayGlobalApiStatistics(SymfonyStyle $io): void
    {
        $apiStats = $this->apiLogRepository->getErrorStatistics();
        $io->section('API Call Statistics');
        $io->listing([
            sprintf('Total Requests: %d', is_int($apiStats['total_requests']) ? $apiStats['total_requests'] : 0),
            sprintf(
                'Success: %d (%.1f%%)',
                is_int($apiStats['success_count']) ? $apiStats['success_count'] : 0,
                is_float($apiStats['success_rate']) ? $apiStats['success_rate'] : 0.0
            ),
            sprintf(
                'Errors: %d (%.1f%%)',
                is_int($apiStats['error_count']) ? $apiStats['error_count'] : 0,
                is_float($apiStats['error_rate']) ? $apiStats['error_rate'] : 0.0
            ),
            sprintf('Timeouts: %d', is_int($apiStats['timeout_count']) ? $apiStats['timeout_count'] : 0),
            sprintf('Avg Response Time: %.2fs', is_float($apiStats['avg_response_time']) ? $apiStats['avg_response_time'] : 0.0),
        ]);
    }

    public function displayApiKeyPerformance(SymfonyStyle $io): void
    {
        $performance = $this->apiLogRepository->getApiKeyPerformance();
        if ([] === $performance) {
            return;
        }

        $io->section('API Key Performance');
        $rows = $this->buildPerformanceRows($performance);
        $io->table(['Key Name', 'Requests', 'Avg Time'], $rows);
    }

    public function displayBasicKeyInformation(SymfonyStyle $io, DeepSeekApiKey $apiKey): void
    {
        $io->section('Basic Information');
        $createTime = $apiKey->getCreateTime();
        $lastUseTime = $apiKey->getLastUseTime();
        $updateTime = $apiKey->getUpdateTime();

        $io->listing([
            sprintf('ID: %d', $apiKey->getId()),
            sprintf('Name: %s', $apiKey->getName()),
            sprintf('Priority: %d', $apiKey->getPriority()),
            sprintf('Active: %s', $apiKey->isActive() ? 'Yes' : 'No'),
            sprintf('Valid: %s', $apiKey->isValid() ? 'Yes' : 'No'),
            sprintf('Usage Count: %d', $apiKey->getUsageCount()),
            sprintf('Created: %s', null !== $createTime ? $createTime->format('Y-m-d H:i:s') : 'Unknown'),
            sprintf('Last Used: %s', null !== $lastUseTime ? $lastUseTime->format('Y-m-d H:i:s') : 'Never'),
            sprintf('Last Updated: %s', null !== $updateTime ? $updateTime->format('Y-m-d H:i:s') : 'Unknown'),
        ]);
    }

    public function displayKeyModels(SymfonyStyle $io, DeepSeekApiKey $apiKey): void
    {
        $models = $this->modelRepository->findByApiKey($apiKey);
        if ([] === $models) {
            return;
        }

        $io->section('Models');
        $modelList = array_map(fn ($m) => $m->getModelId(), $models);
        $io->listing($modelList);
    }

    public function displayKeyBalance(SymfonyStyle $io, DeepSeekApiKey $apiKey): void
    {
        $balance = $this->balanceRepository->findLatestByApiKey($apiKey);
        if (null === $balance) {
            return;
        }

        $io->section('Latest Balance');
        $recordTime = $balance->getRecordTime();
        $recordTimeDisplay = $recordTime->format('Y-m-d H:i:s');

        $currency = $balance->getCurrency();
        $totalBalance = $balance->getTotalBalanceAsFloat();
        $grantedBalance = $balance->getGrantedBalanceAsFloat();
        $toppedUpBalance = $balance->getToppedUpBalanceAsFloat();

        $io->listing([
            sprintf('Currency: %s', $currency),
            sprintf('Total: %.4f', $totalBalance),
            sprintf('Granted: %.4f', $grantedBalance),
            sprintf('Topped Up: %.4f', $toppedUpBalance),
            sprintf('Recorded: %s', $recordTimeDisplay),
        ]);
    }

    public function displayKeyApiLogs(SymfonyStyle $io, DeepSeekApiKey $apiKey): void
    {
        $logs = $this->apiLogRepository->findByApiKey($apiKey, 10);
        if ([] === $logs) {
            return;
        }

        $io->section('Recent API Logs');
        $rows = [];
        foreach ($logs as $log) {
            $rows[] = $this->buildApiLogRow($log);
        }
        $io->table(['Endpoint', 'Status', 'Code', 'Time', 'Date'], $rows);
    }

    /**
     * @param array<string, mixed> $performance
     * @return array<array{string, int, string}>
     */
    private function buildPerformanceRows(array $performance): array
    {
        $rows = [];

        foreach ($performance as $keyName => $data) {
            if (!is_array($data)) {
                continue;
            }

            /** @var array<string, mixed> $data */
            $requestCount = $this->extractIntValue($data, 'request_count');
            $avgResponseTime = $this->extractFloatValue($data, 'avg_response_time');

            $rows[] = [
                $keyName,
                $requestCount,
                sprintf('%.2fs', $avgResponseTime),
            ];
        }

        return $rows;
    }

    /**
     * @param object $log
     * @return array{string, string, string, string, string}
     */
    private function buildApiLogRow(object $log): array
    {
        /** @var string $endpoint */
        // @phpstan-ignore-next-line
        $endpoint = $log->getEndpoint();
        /** @var string $status */
        // @phpstan-ignore-next-line
        $status = $log->getStatus();

        return [
            $endpoint,
            $status,
            $this->formatStatusCode($log),
            $this->formatResponseTime($log),
            $this->formatRequestTime($log),
        ];
    }

    private function formatStatusCode(object $log): string
    {
        // @phpstan-ignore-next-line
        $statusCode = $log->getStatusCode();

        if (null === $statusCode || (!is_int($statusCode) && !is_string($statusCode))) {
            return 'N/A';
        }

        return (string) $statusCode;
    }

    private function formatResponseTime(object $log): string
    {
        // @phpstan-ignore-next-line
        $responseTime = $log->getResponseTime();

        if (null === $responseTime || !is_float($responseTime)) {
            return 'N/A';
        }

        return sprintf('%.2fs', $responseTime);
    }

    private function formatRequestTime(object $log): string
    {
        /** @var \DateTimeInterface|null $requestTime */
        // @phpstan-ignore-next-line
        $requestTime = $log->getRequestTime();

        return null !== $requestTime
            ? $requestTime->format('Y-m-d H:i:s')
            : 'Unknown';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractIntValue(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractFloatValue(array $data, string $key): float
    {
        $value = $data[$key] ?? 0.0;

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
