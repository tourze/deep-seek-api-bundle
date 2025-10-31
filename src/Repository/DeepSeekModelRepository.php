<?php

namespace Tourze\DeepSeekApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekModel;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DeepSeekModel>
 */
#[AsRepository(entityClass: DeepSeekModel::class)]
class DeepSeekModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeepSeekModel::class);
    }

    /**
     * @return DeepSeekModel[]
     */
    public function findActiveModels(): array
    {
        /** @var list<DeepSeekModel> $models */
        $models = $this->createQueryBuilder('m')
            ->andWhere('m.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('m.modelId', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $models;
    }

    /**
     * @return DeepSeekModel[]
     */
    public function findChatModels(): array
    {
        /** @var list<DeepSeekModel> $models */
        $models = $this->createQueryBuilder('m')
            ->andWhere('m.modelId LIKE :pattern')
            ->setParameter('pattern', '%chat%')
            ->orderBy('m.modelId', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $models;
    }

    /**
     * @return DeepSeekModel[]
     */
    public function findReasonerModels(): array
    {
        /** @var list<DeepSeekModel> $models */
        $models = $this->createQueryBuilder('m')
            ->andWhere('m.modelId LIKE :pattern')
            ->setParameter('pattern', '%reasoner%')
            ->orderBy('m.modelId', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $models;
    }

    /**
     * 为API密钥同步模型
     *
     * @param DeepSeekApiKey $apiKey
     * @param array<array{id: string, object: string, owned_by: string}> $modelsData
     */
    public function syncModelsForApiKey(DeepSeekApiKey $apiKey, array $modelsData): void
    {
        $existingModels = $this->findByApiKey($apiKey);
        $existingModelIds = array_map(fn ($m) => $m->getModelId(), $existingModels);
        $newModelIds = array_map(fn ($d) => $d['id'], $modelsData);

        // Remove models that no longer exist
        foreach ($existingModels as $model) {
            if (!in_array($model->getModelId(), $newModelIds, true)) {
                $this->remove($model);
            }
        }

        // Add or update models
        foreach ($modelsData as $modelData) {
            $model = $this->findOneByModelIdAndApiKey($modelData['id'], $apiKey);

            if (null === $model) {
                $model = DeepSeekModel::fromApiResponse($modelData, $apiKey);
                $this->save($model);
            } else {
                $model->setObject($modelData['object']);
                $model->setOwnedBy($modelData['owned_by']);
            }
        }

        $this->getEntityManager()->flush();
    }

    /**
     * @return DeepSeekModel[]
     */
    public function findByApiKey(DeepSeekApiKey $apiKey): array
    {
        /** @var list<DeepSeekModel> $models */
        $models = $this->createQueryBuilder('m')
            ->andWhere('m.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey)
            ->orderBy('m.modelId', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $models;
    }

    public function remove(DeepSeekModel $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByModelIdAndApiKey(string $modelId, DeepSeekApiKey $apiKey): ?DeepSeekModel
    {
        /** @var DeepSeekModel|null $model */
        $model = $this->createQueryBuilder('m')
            ->andWhere('m.modelId = :modelId')
            ->andWhere('m.apiKey = :apiKey')
            ->setParameter('modelId', $modelId)
            ->setParameter('apiKey', $apiKey)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $model;
    }

    public function save(DeepSeekModel $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<string, int>
     */
    public function getModelStatistics(): array
    {
        $total = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $active = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $chatModels = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.modelId LIKE :pattern')
            ->setParameter('pattern', '%chat%')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $reasonerModels = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.modelId LIKE :pattern')
            ->setParameter('pattern', '%reasoner%')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'chat_models' => (int) $chatModels,
            'reasoner_models' => (int) $reasonerModels,
        ];
    }
}
