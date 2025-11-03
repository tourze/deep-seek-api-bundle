<?php

namespace Tourze\DeepSeekApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory;
use Tourze\DeepSeekApiBundle\Exception\BalanceException;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DeepSeekBalanceHistory>
 */
#[AsRepository(entityClass: DeepSeekBalanceHistory::class)]
class DeepSeekBalanceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeepSeekBalanceHistory::class);
    }

    public function remove(DeepSeekBalanceHistory $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 记录余额变化
     */
    public function recordBalanceChange(DeepSeekBalance $balance): DeepSeekBalanceHistory
    {
        // 查找最近一条历史记录
        $apiKey = $balance->getApiKey();
        if (null === $apiKey) {
            throw new BalanceException('Balance must have an associated API key');
        }
        $previousRecord = $this->findLatestByApiKeyAndCurrency(
            $apiKey,
            $balance->getCurrency()
        );

        // 创建新的历史记录
        $history = DeepSeekBalanceHistory::createFromBalance($balance);
        $history->calculateChange($previousRecord);

        $this->save($history, true);

        return $history;
    }

    /**
     * 查找某个API密钥和币种的最新历史记录
     */
    public function findLatestByApiKeyAndCurrency(DeepSeekApiKey $apiKey, string $currency): ?DeepSeekBalanceHistory
    {
        $result = $this->createQueryBuilder('h')
            ->andWhere('h.apiKey = :apiKey')
            ->andWhere('h.currency = :currency')
            ->setParameter('apiKey', $apiKey)
            ->setParameter('currency', $currency)
            ->orderBy('h.recordTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        /** @var DeepSeekBalanceHistory|null $result */
        return $result;
    }

    public function save(DeepSeekBalanceHistory $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 批量记录余额变化
     *
     * @param DeepSeekBalance[] $balances
     * @return DeepSeekBalanceHistory[]
     */
    public function recordBalanceChanges(array $balances): array
    {
        $histories = [];

        foreach ($balances as $balance) {
            $apiKey = $balance->getApiKey();
            if (null === $apiKey) {
                continue; // 跳过没有API key的余额记录
            }
            $previousRecord = $this->findLatestByApiKeyAndCurrency(
                $apiKey,
                $balance->getCurrency()
            );

            $history = DeepSeekBalanceHistory::createFromBalance($balance);
            $history->calculateChange($previousRecord);

            $this->save($history, false);
            $histories[] = $history;
        }

        $this->getEntityManager()->flush();

        return $histories;
    }

    /**
     * 获取某个API密钥的历史记录
     *
     * @return DeepSeekBalanceHistory[]
     */
    public function findByApiKey(DeepSeekApiKey $apiKey, int $limit = 100): array
    {
        $result = $this->createQueryBuilder('h')
            ->andWhere('h.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey)
            ->orderBy('h.recordTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var DeepSeekBalanceHistory[] $result */
        return $result;
    }

    /**
     * 获取余额变化趋势
     *
     * @return list<array{date: string, records: list<DeepSeekBalanceHistory>, start_balance: float|null, end_balance: float|null, high: float|null, low: float|null, change: float, change_percent?: float}>
     */
    public function getBalanceTrend(
        DeepSeekApiKey $apiKey,
        string $currency,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $interval = 'daily',
    ): array {
        $records = $this->findByDateRange($startDate, $endDate, $apiKey, $currency);

        if ([] === $records) {
            return [];
        }

        $dateFormat = $this->getDateFormat($interval);
        $trend = $this->groupRecordsByDate($records, $dateFormat);
        $trend = $this->calculateTrendStatistics($trend);

        return array_values($trend);
    }

    /**
     * 获取日期格式
     */
    private function getDateFormat(string $interval): string
    {
        return match ($interval) {
            'hourly' => 'Y-m-d H:00',
            'daily' => 'Y-m-d',
            'weekly' => 'Y-W',
            'monthly' => 'Y-m',
            default => 'Y-m-d',
        };
    }

    /**
     * 按日期分组记录
     *
     * @param DeepSeekBalanceHistory[] $records
     * @return array<string, array{date: string, records: list<DeepSeekBalanceHistory>, start_balance: float|null, end_balance: float|null, high: float|null, low: float|null, change: float}>
     */
    private function groupRecordsByDate(array $records, string $dateFormat): array
    {
        $trend = [];

        foreach ($records as $record) {
            $key = $record->getRecordTime()->format($dateFormat);

            if (!isset($trend[$key])) {
                $trend[$key] = $this->createEmptyTrendPeriod($key);
            }

            $trend[$key] = $this->updateTrendPeriod($trend[$key], $record);
        }

        return $trend;
    }

    /**
     * 创建空的趋势周期
     *
     * @return array{date: string, records: list<DeepSeekBalanceHistory>, start_balance: float|null, end_balance: float|null, high: float|null, low: float|null, change: float}
     */
    private function createEmptyTrendPeriod(string $date): array
    {
        return [
            'date' => $date,
            'records' => [],
            'start_balance' => null,
            'end_balance' => null,
            'high' => null,
            'low' => null,
            'change' => 0,
        ];
    }

    /**
     * 更新趋势周期数据
     *
     * @param array{date: string, records: list<DeepSeekBalanceHistory>, start_balance: float|null, end_balance: float|null, high: float|null, low: float|null, change: float} $period
     * @return array{date: string, records: list<DeepSeekBalanceHistory>, start_balance: float|null, end_balance: float|null, high: float|null, low: float|null, change: float}
     */
    private function updateTrendPeriod(array $period, DeepSeekBalanceHistory $record): array
    {
        $balance = $record->getTotalBalanceAsFloat();
        $period['records'][] = $record;

        // 设置开始余额（仅第一次）
        if (null === $period['start_balance']) {
            $period['start_balance'] = $balance;
        }

        // 更新结束余额
        $period['end_balance'] = $balance;

        // 更新最高值
        if (null === $period['high'] || $balance > $period['high']) {
            $period['high'] = $balance;
        }

        // 更新最低值
        if (null === $period['low'] || $balance < $period['low']) {
            $period['low'] = $balance;
        }

        return $period;
    }

    /**
     * 计算趋势统计
     *
     * @param array<string, array{date: string, records: list<DeepSeekBalanceHistory>, start_balance: float|null, end_balance: float|null, high: float|null, low: float|null, change: float}> $trend
     * @return array<string, array{date: string, records: list<DeepSeekBalanceHistory>, start_balance: float|null, end_balance: float|null, high: float|null, low: float|null, change: float, change_percent?: float}>
     */
    private function calculateTrendStatistics(array $trend): array
    {
        foreach ($trend as $key => $period) {
            if (null === $period['start_balance'] || null === $period['end_balance']) {
                continue;
            }

            $period['change'] = $period['end_balance'] - $period['start_balance'];
            $period['change_percent'] = $period['start_balance'] > 0
                ? ($period['change'] / $period['start_balance']) * 100
                : 0;
            $trend[$key] = $period;
        }

        return $trend;
    }

    /**
     * 获取某个时间段的历史记录
     *
     * @return DeepSeekBalanceHistory[]
     */
    public function findByDateRange(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?DeepSeekApiKey $apiKey = null,
        ?string $currency = null,
    ): array {
        $qb = $this->createQueryBuilder('h')
            ->andWhere('h.recordTime >= :startDate')
            ->andWhere('h.recordTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        if (null !== $apiKey) {
            $qb->andWhere('h.apiKey = :apiKey')
                ->setParameter('apiKey', $apiKey)
            ;
        }

        if (null !== $currency) {
            $qb->andWhere('h.currency = :currency')
                ->setParameter('currency', $currency)
            ;
        }

        $result = $qb->orderBy('h.recordTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        /** @var DeepSeekBalanceHistory[] $result */
        return $result;
    }

    /**
     * 获取消费统计
     *
     * @return array<string, mixed>
     */
    public function getConsumptionStatistics(
        DeepSeekApiKey $apiKey,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        $records = $this->findByDateRange($startDate, $endDate, $apiKey);

        $stats = [];
        foreach ($records as $record) {
            $currency = $record->getCurrency();

            if (!isset($stats[$currency])) {
                $stats[$currency] = [
                    'currency' => $currency,
                    'total_consumed' => 0,
                    'total_topped_up' => 0,
                    'record_count' => 0,
                    'decreases' => [],
                    'increases' => [],
                ];
            }

            ++$stats[$currency]['record_count'];

            if ('decrease' === $record->getChangeType()) {
                $change = abs($record->getBalanceChangeAsFloat());
                $stats[$currency]['total_consumed'] += $change;
                $stats[$currency]['decreases'][] = [
                    'amount' => $change,
                    'date' => $record->getRecordTime()->format('Y-m-d H:i:s'),
                ];
            } elseif ('increase' === $record->getChangeType()) {
                $change = $record->getBalanceChangeAsFloat();
                $stats[$currency]['total_topped_up'] += $change;
                $stats[$currency]['increases'][] = [
                    'amount' => $change,
                    'date' => $record->getRecordTime()->format('Y-m-d H:i:s'),
                ];
            }
        }

        // 计算日均消费
        $days = false !== $startDate->diff($endDate)->days ? $startDate->diff($endDate)->days : 1;
        foreach ($stats as $currency => $stat) {
            $stat['daily_average_consumption'] = $stat['total_consumed'] / $days;
            $stat['daily_average_top_up'] = $stat['total_topped_up'] / $days;
            $stats[$currency] = $stat;
        }

        return $stats;
    }

    /**
     * 清理旧的历史记录
     */
    public function cleanOldRecords(int $daysToKeep = 90): int
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $daysToKeep));

        $result = $this->createQueryBuilder('h')
            ->delete()
            ->where('h.recordTime < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
        assert(is_int($result));

        return $result;
    }

    /**
     * 获取余额预警信息
     *
     * @return list<array{api_key: string, currency: string, current_balance: float, threshold: float, recorded_at: string, alert_level: string}>
     */
    public function getBalanceAlerts(float $threshold = 10.0): array
    {
        $latestRecords = $this->findLatestForAllKeys();

        $alerts = [];
        foreach ($latestRecords as $record) {
            $apiKey = $record->getApiKey();
            if (null === $apiKey) {
                continue; // 跳过没有API key的记录
            }

            if ($record->getTotalBalanceAsFloat() < $threshold) {
                $alerts[] = [
                    'api_key' => $apiKey->getName(),
                    'currency' => $record->getCurrency(),
                    'current_balance' => $record->getTotalBalanceAsFloat(),
                    'threshold' => $threshold,
                    'recorded_at' => $record->getRecordTime()->format('Y-m-d H:i:s'),
                    'alert_level' => $record->getTotalBalanceAsFloat() < ($threshold * 0.5) ? 'critical' : 'warning',
                ];
            }
        }

        return $alerts;
    }

    /**
     * 获取所有API密钥的最新余额快照
     *
     * @return DeepSeekBalanceHistory[]
     */
    public function findLatestForAllKeys(): array
    {
        $subQuery = $this->createQueryBuilder('h2')
            ->select('MAX(h2.recordTime)')
            ->where('h2.apiKey = h.apiKey')
            ->andWhere('h2.currency = h.currency')
            ->getDQL()
        ;

        $result = $this->createQueryBuilder('h')
            ->where('h.recordTime = (' . $subQuery . ')')
            ->getQuery()
            ->getResult()
        ;

        /** @var DeepSeekBalanceHistory[] $result */
        return $result;
    }
}
