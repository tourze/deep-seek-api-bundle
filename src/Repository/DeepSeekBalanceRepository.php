<?php

namespace Tourze\DeepSeekApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DeepSeekBalance>
 */
#[AsRepository(entityClass: DeepSeekBalance::class)]
class DeepSeekBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeepSeekBalance::class);
    }

    public function remove(DeepSeekBalance $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findLatestByApiKey(DeepSeekApiKey $apiKey): ?DeepSeekBalance
    {
        /** @var DeepSeekBalance|null $balance */
        $balance = $this->createQueryBuilder('b')
            ->andWhere('b.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey)
            ->orderBy('b.recordTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $balance;
    }

    /**
     * @return DeepSeekBalance[]
     */
    public function findByApiKeyAndCurrency(DeepSeekApiKey $apiKey, string $currency): array
    {
        /** @var list<DeepSeekBalance> $balances */
        $balances = $this->createQueryBuilder('b')
            ->andWhere('b.apiKey = :apiKey')
            ->andWhere('b.currency = :currency')
            ->setParameter('apiKey', $apiKey)
            ->setParameter('currency', $currency)
            ->orderBy('b.recordTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $balances;
    }

    /**
     * @return DeepSeekBalance[]
     */
    public function findLatestForAllKeys(): array
    {
        $subQuery = $this->createQueryBuilder('b2')
            ->select('MAX(b2.recordTime)')
            ->where('b2.apiKey = b.apiKey')
            ->andWhere('b2.currency = b.currency')
            ->getDQL()
        ;

        /** @var list<DeepSeekBalance> $balances */
        $balances = $this->createQueryBuilder('b')
            ->where('b.recordTime = (' . $subQuery . ')')
            ->getQuery()
            ->getResult()
        ;

        return $balances;
    }

    /**
     * @return DeepSeekBalance[]
     */
    public function findLowBalances(float $threshold = 10.0): array
    {
        /** @var list<DeepSeekBalance> $balances */
        $balances = $this->createQueryBuilder('b')
            ->andWhere('b.totalBalance < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('b.totalBalance', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $balances;
    }

    /**
     * @return DeepSeekBalance[]
     */
    public function findBalanceHistory(
        DeepSeekApiKey $apiKey,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        /** @var list<DeepSeekBalance> $balances */
        $balances = $this->createQueryBuilder('b')
            ->andWhere('b.apiKey = :apiKey')
            ->andWhere('b.recordTime >= :startDate')
            ->andWhere('b.recordTime <= :endDate')
            ->setParameter('apiKey', $apiKey)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.recordTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $balances;
    }

    /**
     * 保存新的余额记录并计算变化
     *
     * @param DeepSeekApiKey $apiKey
     * @param DeepSeekBalance[] $balances
     */
    public function saveBalances(DeepSeekApiKey $apiKey, array $balances): void
    {
        foreach ($balances as $balance) {
            // Find previous balance for the same currency
            /** @var DeepSeekBalance|null $previousBalance */
            $previousBalance = $this->createQueryBuilder('b')
                ->andWhere('b.apiKey = :apiKey')
                ->andWhere('b.currency = :currency')
                ->setParameter('apiKey', $apiKey)
                ->setParameter('currency', $balance->getCurrency())
                ->orderBy('b.recordTime', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult()
            ;

            if (null !== $previousBalance) {
                $balance->calculateBalanceChange($previousBalance);
            }

            $this->save($balance);
        }

        $this->getEntityManager()->flush();
    }

    public function save(DeepSeekBalance $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<string, float>
     */
    public function getTotalBalanceByCurrency(): array
    {
        /** @var list<array{currency: string, total: string}> $results */
        $results = $this->createQueryBuilder('b')
            ->select('b.currency, SUM(b.totalBalance) as total')
            ->join('b.apiKey', 'k')
            ->where('k.isActive = :active')
            ->andWhere('k.isValid = :valid')
            ->setParameter('active', true)
            ->setParameter('valid', true)
            ->groupBy('b.currency')
            ->getQuery()
            ->getResult()
        ;

        $totals = [];
        foreach ($results as $result) {
            $totals[$result['currency']] = (float) $result['total'];
        }

        return $totals;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBalanceStatistics(): array
    {
        $totalRecords = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $avgCNY = $this->createQueryBuilder('b')
            ->select('AVG(b.totalBalance)')
            ->where('b.currency = :currency')
            ->setParameter('currency', 'CNY')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $avgUSD = $this->createQueryBuilder('b')
            ->select('AVG(b.totalBalance)')
            ->where('b.currency = :currency')
            ->setParameter('currency', 'USD')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $lowBalanceCount = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.totalBalance < :threshold')
            ->setParameter('threshold', 10.0)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return [
            'total_records' => $totalRecords,
            'average_balance_cny' => null !== $avgCNY ? (float) $avgCNY : 0.0,
            'average_balance_usd' => null !== $avgUSD ? (float) $avgUSD : 0.0,
            'low_balance_count' => $lowBalanceCount,
        ];
    }

    /**
     * 清理旧的余额记录
     */
    public function cleanOldRecords(int $daysToKeep = 30): int
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $daysToKeep));

        /** @var int $affectedRows */
        $affectedRows = $this->createQueryBuilder('b')
            ->delete()
            ->where('b.recordTime < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;

        return $affectedRows;
    }
}
