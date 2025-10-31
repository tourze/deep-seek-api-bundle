<?php

namespace Tourze\DeepSeekApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiLog;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DeepSeekApiLog>
 */
#[AsRepository(entityClass: DeepSeekApiLog::class)]
class DeepSeekApiLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeepSeekApiLog::class);
    }

    public function save(DeepSeekApiLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DeepSeekApiLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return DeepSeekApiLog[]
     */
    public function findByApiKey(DeepSeekApiKey $apiKey, int $limit = 100): array
    {
        /** @var list<DeepSeekApiLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->andWhere('l.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey)
            ->orderBy('l.requestTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $logs;
    }

    /**
     * @return DeepSeekApiLog[]
     */
    public function findByEndpoint(string $endpoint, int $limit = 100): array
    {
        /** @var list<DeepSeekApiLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->andWhere('l.endpoint = :endpoint')
            ->setParameter('endpoint', $endpoint)
            ->orderBy('l.requestTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $logs;
    }

    /**
     * @return DeepSeekApiLog[]
     */
    public function findErrors(int $limit = 100): array
    {
        /** @var list<DeepSeekApiLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->andWhere('l.status = :status')
            ->setParameter('status', DeepSeekApiLog::STATUS_ERROR)
            ->orderBy('l.requestTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $logs;
    }

    /**
     * @return DeepSeekApiLog[]
     */
    public function findByDateRange(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?DeepSeekApiKey $apiKey = null,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.requestTime >= :startDate')
            ->andWhere('l.requestTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        if (null !== $apiKey) {
            $qb->andWhere('l.apiKey = :apiKey')
                ->setParameter('apiKey', $apiKey)
            ;
        }

        /** @var list<DeepSeekApiLog> $logs */
        $logs = $qb->orderBy('l.requestTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $logs;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatisticsByEndpoint(): array
    {
        /** @var list<array{endpoint: string, count: string, avg_response_time: string|null}> $results */
        $results = $this->createQueryBuilder('l')
            ->select('l.endpoint, COUNT(l.id) as count, AVG(l.responseTime) as avg_response_time')
            ->groupBy('l.endpoint')
            ->getQuery()
            ->getResult()
        ;

        $statistics = [];
        foreach ($results as $result) {
            $statistics[$result['endpoint']] = [
                'count' => (int) $result['count'],
                'avg_response_time' => null !== $result['avg_response_time'] ? (float) $result['avg_response_time'] : 0.0,
            ];
        }

        return $statistics;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrorStatistics(): array
    {
        $qb = $this->createQueryBuilder('l');

        $totalRequests = $qb->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $errorCount = $qb->select('COUNT(l.id)')
            ->where('l.status = :status')
            ->setParameter('status', DeepSeekApiLog::STATUS_ERROR)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $timeoutCount = $qb->select('COUNT(l.id)')
            ->where('l.status = :status')
            ->setParameter('status', DeepSeekApiLog::STATUS_TIMEOUT)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $successCount = $qb->select('COUNT(l.id)')
            ->where('l.status = :status')
            ->setParameter('status', DeepSeekApiLog::STATUS_SUCCESS)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        // 需要创建新的 QueryBuilder 以避免参数绑定冲突
        $avgResponseTime = $this->createQueryBuilder('l')
            ->select('AVG(l.responseTime)')
            ->where('l.responseTime IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return [
            'total_requests' => $totalRequests,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'timeout_count' => $timeoutCount,
            'success_rate' => (int) $totalRequests > 0 ? ((int) $successCount / (int) $totalRequests) * 100 : 0,
            'error_rate' => (int) $totalRequests > 0 ? ((int) $errorCount / (int) $totalRequests) * 100 : 0,
            'avg_response_time' => null !== $avgResponseTime ? (float) $avgResponseTime : 0.0,
        ];
    }

    /**
     * @return DeepSeekApiLog[]
     */
    public function findSlowRequests(float $threshold = 5.0, int $limit = 100): array
    {
        /** @var list<DeepSeekApiLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->andWhere('l.responseTime > :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('l.responseTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $logs;
    }

    /**
     * 清理旧的日志记录
     */
    public function cleanOldRecords(int $daysToKeep = 7): int
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $daysToKeep));

        /** @var int $affectedRows */
        $affectedRows = $this->createQueryBuilder('l')
            ->delete()
            ->where('l.requestTime < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;

        return $affectedRows;
    }

    /**
     * @return array<string, mixed>
     */
    public function getApiKeyPerformance(): array
    {
        /** @var list<array{key_id: int, key_name: string, request_count: string, avg_response_time: string|null}> $results */
        $results = $this->createQueryBuilder('l')
            ->select('k.id as key_id, k.name as key_name, COUNT(l.id) as request_count, AVG(l.responseTime) as avg_response_time')
            ->join('l.apiKey', 'k')
            ->groupBy('k.id, k.name')
            ->getQuery()
            ->getResult()
        ;

        $performance = [];
        foreach ($results as $result) {
            $performance[$result['key_name']] = [
                'request_count' => (int) $result['request_count'],
                'avg_response_time' => null !== $result['avg_response_time'] ? (float) $result['avg_response_time'] : 0.0,
            ];
        }

        return $performance;
    }
}
