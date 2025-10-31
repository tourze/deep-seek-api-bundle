<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Command\Helper;

use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;

/**
 * 余额分析数据格式化帮助类
 */
class BalanceDataFormatter
{
    /**
     * @param array<int, array<string, mixed>> $trend
     */
    public function displayBalanceTrend(SymfonyStyle $io, array $trend): void
    {
        if ([] === $trend) {
            return;
        }

        $io->section('Balance Trend');
        $rows = [];
        foreach ($trend as $period) {
            $rows[] = $this->buildTrendRow($period);
        }

        $io->table(
            ['Period', 'Start', 'End', 'High', 'Low', 'Change', 'Change %'],
            $rows
        );
    }

    /**
     * @param array<string, mixed> $period
     * @return array{string, string, string, string, string, string, string}
     */
    private function buildTrendRow(array $period): array
    {
        $startBalance = $this->extractNumericValue($period, 'start_balance');
        $endBalance = $this->extractNumericValue($period, 'end_balance');
        $high = $this->extractNumericValue($period, 'high');
        $low = $this->extractNumericValue($period, 'low');
        $change = $this->extractNumericValue($period, 'change');
        $changePercent = $this->extractOptionalNumericValue($period, 'change_percent');

        return [
            $this->extractStringValue($period, 'date', 'N/A'),
            number_format($startBalance, 4),
            number_format($endBalance, 4),
            number_format($high, 4),
            number_format($low, 4),
            number_format($change, 4),
            null !== $changePercent ? sprintf('%.2f%%', $changePercent) : 'N/A',
        ];
    }

    /**
     * @param array<mixed, mixed> $consumption
     */
    public function displayConsumptionStatistics(SymfonyStyle $io, array $consumption): void
    {
        if ([] === $consumption) {
            return;
        }

        $io->section('Consumption Statistics');
        foreach ($consumption as $stats) {
            if (is_array($stats)) {
                /** @var array<string, mixed> $stats */
                $this->displaySingleConsumptionStats($io, $stats);
            }
        }
    }

    /**
     * @param array<string, mixed> $stats
     */
    private function displaySingleConsumptionStats(SymfonyStyle $io, array $stats): void
    {
        $consumptionData = $this->extractConsumptionData($stats);
        $this->displayConsumptionListing($io, $consumptionData);
        $this->displayRecentConsumptionIfAvailable($io, $stats);
    }

    /**
     * @param array<string, mixed> $stats
     * @return array{currency: string, totalConsumed: float, totalToppedUp: float, dailyAverageConsumption: float, dailyAverageTopUp: float, recordCount: int}
     */
    private function extractConsumptionData(array $stats): array
    {
        return [
            'currency' => $this->extractStringValue($stats, 'currency', 'N/A'),
            'totalConsumed' => $this->extractNumericValue($stats, 'total_consumed'),
            'totalToppedUp' => $this->extractNumericValue($stats, 'total_topped_up'),
            'dailyAverageConsumption' => $this->extractNumericValue($stats, 'daily_average_consumption'),
            'dailyAverageTopUp' => $this->extractNumericValue($stats, 'daily_average_top_up'),
            'recordCount' => (int) $this->extractNumericValue($stats, 'record_count'),
        ];
    }

    /**
     * @param array{currency: string, totalConsumed: float, totalToppedUp: float, dailyAverageConsumption: float, dailyAverageTopUp: float, recordCount: int} $data
     */
    private function displayConsumptionListing(SymfonyStyle $io, array $data): void
    {
        $io->listing([
            sprintf('Currency: %s', $data['currency']),
            sprintf('Total Consumed: %.4f', $data['totalConsumed']),
            sprintf('Total Topped Up: %.4f', $data['totalToppedUp']),
            sprintf('Daily Average Consumption: %.4f', $data['dailyAverageConsumption']),
            sprintf('Daily Average Top Up: %.4f', $data['dailyAverageTopUp']),
            sprintf('Record Count: %d', $data['recordCount']),
        ]);
    }

    /**
     * @param array<string, mixed> $stats
     */
    private function displayRecentConsumptionIfAvailable(SymfonyStyle $io, array $stats): void
    {
        if (!isset($stats['decreases']) || !is_array($stats['decreases']) || [] === $stats['decreases']) {
            return;
        }

        /** @var array<mixed, mixed> $decreases */
        $decreases = $stats['decreases'];
        $this->displayRecentConsumption($io, $decreases);
    }

    /**
     * @param array<mixed, mixed> $decreases
     */
    private function displayRecentConsumption(SymfonyStyle $io, array $decreases): void
    {
        $io->comment('Recent Consumption:');
        $consumptionRows = $this->buildConsumptionRows($decreases);
        $io->table(['Date', 'Amount'], $consumptionRows);
    }

    /**
     * @param array<mixed, mixed> $decreases
     * @return array<array{string, string}>
     */
    private function buildConsumptionRows(array $decreases): array
    {
        $rows = [];

        foreach (array_slice($decreases, 0, 5) as $decrease) {
            if (!is_array($decrease)) {
                continue;
            }

            /** @var array<string, mixed> $decrease */
            $date = $this->extractStringValue($decrease, 'date', 'N/A');
            $amount = $this->extractNumericValue($decrease, 'amount');

            $rows[] = [$date, sprintf('%.4f', $amount)];
        }

        return $rows;
    }

    public function displayCurrentBalance(SymfonyStyle $io, ?DeepSeekBalance $currentBalance): void
    {
        if (null === $currentBalance) {
            return;
        }

        $io->info(sprintf(
            'Current Balance: %.4f %s',
            $currentBalance->getTotalBalanceAsFloat(),
            $currentBalance->getCurrency()
        ));
    }

    /**
     * @param array<string, mixed> $consumption
     */
    public function displayBalanceExhaustionPrediction(
        SymfonyStyle $io,
        ?DeepSeekBalance $currentBalance,
        array $consumption,
    ): void {
        if (null === $currentBalance || [] === $consumption) {
            return;
        }

        foreach ($consumption as $stats) {
            if (!is_array($stats)) {
                continue;
            }

            /** @var array<string, mixed> $stats */
            $this->checkAndDisplayExhaustionWarning($io, $currentBalance, $stats);
        }
    }

    /**
     * @param array<string, mixed> $stats
     */
    private function checkAndDisplayExhaustionWarning(
        SymfonyStyle $io,
        DeepSeekBalance $currentBalance,
        array $stats,
    ): void {
        $statsCurrency = $this->extractStringValue($stats, 'currency');
        $dailyAverageConsumption = $this->extractNumericValue($stats, 'daily_average_consumption');

        if ($statsCurrency !== $currentBalance->getCurrency() || $dailyAverageConsumption <= 0) {
            return;
        }

        $daysRemaining = $currentBalance->getTotalBalanceAsFloat() / $dailyAverageConsumption;
        $exhaustDate = (new \DateTimeImmutable())->modify(sprintf('+%d days', (int) $daysRemaining));

        $io->warning(sprintf(
            'At current consumption rate, balance will be exhausted in %.1f days (around %s)',
            $daysRemaining,
            $exhaustDate->format('Y-m-d')
        ));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractNumericValue(array $data, string $key): float
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractOptionalNumericValue(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractStringValue(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
