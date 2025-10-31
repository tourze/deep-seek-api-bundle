<?php

namespace Tourze\DeepSeekApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DeepSeekApiKey>
 */
#[AsRepository(entityClass: DeepSeekApiKey::class)]
class DeepSeekApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeepSeekApiKey::class);
    }

    public function save(DeepSeekApiKey $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DeepSeekApiKey $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<DeepSeekApiKey>
     */
    public function findActiveKeys(): array
    {
        /** @var list<DeepSeekApiKey> $keys */
        $keys = $this->createQueryBuilder('k')
            ->andWhere('k.isActive = :active')
            ->andWhere('k.isValid = :valid')
            ->setParameter('active', true)
            ->setParameter('valid', true)
            ->orderBy('k.priority', 'DESC')
            ->addOrderBy('k.usageCount', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $keys;
    }

    /**
     * @return DeepSeekApiKey[]
     */
    public function findActiveAndValidKeys(): array
    {
        return $this->findActiveKeys();
    }

    /**
     * @return array<DeepSeekApiKey>
     */
    public function findByPriority(): array
    {
        /** @var list<DeepSeekApiKey> $keys */
        $keys = $this->createQueryBuilder('k')
            ->andWhere('k.isActive = :active')
            ->andWhere('k.isValid = :valid')
            ->setParameter('active', true)
            ->setParameter('valid', true)
            ->orderBy('k.priority', 'DESC')
            ->addOrderBy('k.usageCount', 'ASC')
            ->addOrderBy('k.lastUseTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $keys;
    }

    /**
     * @return array<DeepSeekApiKey>
     */
    public function findKeysNeedingModelsSync(): array
    {
        $threshold = new \DateTimeImmutable('-24 hours');

        /** @var list<DeepSeekApiKey> $keys */
        $keys = $this->createQueryBuilder('k')
            ->andWhere('k.isActive = :active')
            ->andWhere('k.isValid = :valid')
            ->andWhere('k.lastModelsSyncTime IS NULL OR k.lastModelsSyncTime < :threshold')
            ->setParameter('active', true)
            ->setParameter('valid', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult()
        ;

        return $keys;
    }

    /**
     * @return array<DeepSeekApiKey>
     */
    public function findKeysNeedingBalanceSync(): array
    {
        $threshold = new \DateTimeImmutable('-1 hour');

        /** @var list<DeepSeekApiKey> $keys */
        $keys = $this->createQueryBuilder('k')
            ->andWhere('k.isActive = :active')
            ->andWhere('k.isValid = :valid')
            ->andWhere('k.lastBalanceSyncTime IS NULL OR k.lastBalanceSyncTime < :threshold')
            ->setParameter('active', true)
            ->setParameter('valid', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult()
        ;

        return $keys;
    }

    public function findByApiKey(string $apiKey): ?DeepSeekApiKey
    {
        /** @var DeepSeekApiKey|null $key */
        $key = $this->createQueryBuilder('k')
            ->andWhere('k.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $key;
    }

    /**
     * @return DeepSeekApiKey|null
     */
    public function findNextAvailableKey(): ?DeepSeekApiKey
    {
        /** @var DeepSeekApiKey|null $key */
        $key = $this->createQueryBuilder('k')
            ->andWhere('k.isActive = :active')
            ->andWhere('k.isValid = :valid')
            ->setParameter('active', true)
            ->setParameter('valid', true)
            ->orderBy('k.priority', 'DESC')
            ->addOrderBy('k.usageCount', 'ASC')
            ->addOrderBy('k.lastUseTime', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $key;
    }

    /**
     * @return array<DeepSeekApiKey>
     */
    public function findInvalidKeys(): array
    {
        /** @var list<DeepSeekApiKey> $keys */
        $keys = $this->createQueryBuilder('k')
            ->andWhere('k.isValid = :valid')
            ->setParameter('valid', false)
            ->orderBy('k.lastErrorTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $keys;
    }

    public function markAllKeysAsValid(): int
    {
        /** @var int $affectedRows */
        $affectedRows = $this->createQueryBuilder('k')
            ->update()
            ->set('k.isValid', ':valid')
            ->setParameter('valid', true)
            ->getQuery()
            ->execute()
        ;

        return $affectedRows;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $total = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $active = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $valid = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.isValid = :valid')
            ->setParameter('valid', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $usable = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.isActive = :active')
            ->andWhere('k.isValid = :valid')
            ->setParameter('active', true)
            ->setParameter('valid', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        // 确保所有变量都是整数类型
        $total = (int) $total;
        $active = (int) $active;
        $valid = (int) $valid;
        $usable = (int) $usable;

        return [
            'total' => $total,
            'active' => $active,
            'valid' => $valid,
            'usable' => $usable,
            'inactive' => $total - $active,
            'invalid' => $total - $valid,
        ];
    }
}
