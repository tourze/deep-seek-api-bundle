<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekModel;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekModelRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekModelRepository::class)]
#[RunTestsInSeparateProcesses]
class DeepSeekModelRepositoryTest extends AbstractRepositoryTestCase
{
    private DeepSeekModelRepository $repository;

    private DeepSeekApiKey $apiKey;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(DeepSeekModelRepository::class);

        // 创建测试用的 API Key
        $this->apiKey = new DeepSeekApiKey();
        $this->apiKey->setName('Test API Key for Repository Test');
        $this->apiKey->setApiKey('sk-test-repository-' . bin2hex(random_bytes(8)));

        $entityManager = static::getEntityManager();
        $entityManager->persist($this->apiKey);
        $entityManager->flush();
    }

    protected function createNewEntity(): DeepSeekModel
    {
        return $this->createModel('test-model-' . bin2hex(random_bytes(4)));
    }

    protected function getRepository(): DeepSeekModelRepository
    {
        return $this->repository;
    }

    public function testSaveAndFind(): void
    {
        $model = $this->createModel('test-deepseek-chat');

        $this->repository->save($model, true);

        $found = $this->repository->find($model->getId());
        $this->assertInstanceOf(DeepSeekModel::class, $found);
        $this->assertEquals('test-deepseek-chat', $found->getModelId());
        $this->assertEquals('deepseek', $found->getOwnedBy());
    }

    public function testFindActiveModels(): void
    {
        // 获取当前活跃模型数量
        $initialActiveCount = count($this->repository->findActiveModels());

        $activeModel = $this->createModel('test-deepseek-chat');
        $activeModel->setIsActive(true);

        $inactiveModel = $this->createModel('test-deepseek-coder');
        $inactiveModel->setIsActive(false);

        $this->repository->save($activeModel, false);
        $this->repository->save($inactiveModel, true);

        $activeModels = $this->repository->findActiveModels();

        // 应该比初始数量多1个（新增的活跃模型）
        $this->assertCount($initialActiveCount + 1, $activeModels);

        // 检查我们创建的活跃模型是否在列表中
        $testModelIds = array_map(fn ($m) => $m->getModelId(), $activeModels);
        $this->assertContains('test-deepseek-chat', $testModelIds);

        // 找到我们创建的模型并验证其状态
        $testModel = null;
        foreach ($activeModels as $model) {
            if ('test-deepseek-chat' === $model->getModelId()) {
                $testModel = $model;
                break;
            }
        }
        $this->assertNotNull($testModel);
        $this->assertTrue($testModel->isActive());
    }

    public function testFindChatModels(): void
    {
        // 获取当前聊天模型数量
        $initialChatCount = count($this->repository->findChatModels());

        $chatModel = $this->createModel('test-deepseek-chat');
        $coderModel = $this->createModel('test-deepseek-coder');
        $reasonerModel = $this->createModel('test-deepseek-reasoner');

        $this->repository->save($chatModel, false);
        $this->repository->save($coderModel, false);
        $this->repository->save($reasonerModel, true);

        $chatModels = $this->repository->findChatModels();

        // 应该比初始数量多1个（新增的聊天模型）
        $this->assertCount($initialChatCount + 1, $chatModels);

        // 检查我们创建的聊天模型是否在列表中
        $testModelIds = array_map(fn ($m) => $m->getModelId(), $chatModels);
        $this->assertContains('test-deepseek-chat', $testModelIds);
    }

    public function testFindReasonerModels(): void
    {
        // 获取当前推理模型数量
        $initialReasonerCount = count($this->repository->findReasonerModels());

        $chatModel = $this->createModel('test-deepseek-chat');
        $reasonerModel = $this->createModel('test-deepseek-reasoner');
        $reasonerV2Model = $this->createModel('test-deepseek-reasoner-v2');

        $this->repository->save($chatModel, false);
        $this->repository->save($reasonerModel, false);
        $this->repository->save($reasonerV2Model, true);

        $reasonerModels = $this->repository->findReasonerModels();

        // 应该比初始数量多2个（新增的推理模型）
        $this->assertCount($initialReasonerCount + 2, $reasonerModels);

        // 检查我们创建的推理模型是否在列表中
        $modelIds = array_map(fn ($m) => $m->getModelId(), $reasonerModels);
        $this->assertContains('test-deepseek-reasoner', $modelIds);
        $this->assertContains('test-deepseek-reasoner-v2', $modelIds);
    }

    public function testFindByApiKey(): void
    {
        // 创建另一个API Key
        $otherApiKey = new DeepSeekApiKey();
        $otherApiKey->setName('Other API Key');
        $otherApiKey->setApiKey('sk-other-123456789');

        $entityManager = static::getEntityManager();
        $entityManager->persist($otherApiKey);
        $entityManager->flush();

        $model1 = $this->createModel('test-deepseek-chat');
        $model2 = $this->createModel('test-deepseek-coder');

        $otherModel = new DeepSeekModel();
        $otherModel->setModelId('test-other-model');
        $otherModel->setApiKey($otherApiKey);
        $otherModel->setOwnedBy('other');

        $this->repository->save($model1, false);
        $this->repository->save($model2, false);
        $this->repository->save($otherModel, true);

        $modelsForApiKey = $this->repository->findByApiKey($this->apiKey);
        $this->assertCount(2, $modelsForApiKey);

        $otherModels = $this->repository->findByApiKey($otherApiKey);
        $this->assertCount(1, $otherModels);
        $this->assertEquals('test-other-model', $otherModels[0]->getModelId());
    }

    public function testFindOneByModelIdAndApiKey(): void
    {
        $model = $this->createModel('test-deepseek-chat');
        $this->repository->save($model, true);

        $found = $this->repository->findOneByModelIdAndApiKey('test-deepseek-chat', $this->apiKey);
        $this->assertInstanceOf(DeepSeekModel::class, $found);
        $this->assertEquals('test-deepseek-chat', $found->getModelId());

        $notFound = $this->repository->findOneByModelIdAndApiKey('non-existent', $this->apiKey);
        $this->assertNull($notFound);
    }

    public function testSyncModelsForApiKey(): void
    {
        // 先保存一些现有模型
        $existingModel1 = $this->createModel('test-deepseek-chat');
        $existingModel2 = $this->createModel('test-deepseek-coder');
        $this->repository->save($existingModel1, false);
        $this->repository->save($existingModel2, true);

        // 模拟API返回的新模型数据
        $modelsData = [
            [
                'id' => 'test-deepseek-chat',
                'object' => 'model',
                'owned_by' => 'deepseek',
            ],
            [
                'id' => 'test-deepseek-reasoner',
                'object' => 'model',
                'owned_by' => 'deepseek',
            ],
        ];

        $this->repository->syncModelsForApiKey($this->apiKey, $modelsData);

        $allModels = $this->repository->findByApiKey($this->apiKey);
        $this->assertCount(2, $allModels);

        $modelIds = array_map(fn ($m) => $m->getModelId(), $allModels);
        $this->assertContains('test-deepseek-chat', $modelIds);
        $this->assertContains('test-deepseek-reasoner', $modelIds);
        $this->assertNotContains('test-deepseek-coder', $modelIds); // 应该被删除
    }

    public function testSyncModelsForApiKeyUpdatesExisting(): void
    {
        $existingModel = $this->createModel('test-deepseek-chat');
        $existingModel->setOwnedBy('old-owner');
        $this->repository->save($existingModel, true);

        $modelsData = [
            [
                'id' => 'test-deepseek-chat',
                'object' => 'model',
                'owned_by' => 'deepseek',
            ],
        ];

        $this->repository->syncModelsForApiKey($this->apiKey, $modelsData);

        $updatedModel = $this->repository->findOneByModelIdAndApiKey('test-deepseek-chat', $this->apiKey);
        $this->assertInstanceOf(DeepSeekModel::class, $updatedModel);
        $this->assertEquals('deepseek', $updatedModel->getOwnedBy()); // 应该被更新
    }

    public function testGetModelStatistics(): void
    {
        // 获取当前统计数据作为基准
        $initialStats = $this->repository->getModelStatistics();

        $chatModel = $this->createModel('test-deepseek-chat');
        $chatModel->setIsActive(true);

        $reasonerModel = $this->createModel('test-deepseek-reasoner');
        $reasonerModel->setIsActive(true);

        $inactiveModel = $this->createModel('test-deepseek-coder');
        $inactiveModel->setIsActive(false);

        $this->repository->save($chatModel, false);
        $this->repository->save($reasonerModel, false);
        $this->repository->save($inactiveModel, true);

        $stats = $this->repository->getModelStatistics();

        // 验证统计数据的增量是否正确
        $this->assertEquals($initialStats['total'] + 3, $stats['total']);
        $this->assertEquals($initialStats['active'] + 2, $stats['active']);
        $this->assertEquals($initialStats['chat_models'] + 1, $stats['chat_models']);
        $this->assertEquals($initialStats['reasoner_models'] + 1, $stats['reasoner_models']);
    }

    public function testRemove(): void
    {
        $model = $this->createModel('test-deepseek-chat');
        $this->repository->save($model, true);

        $modelId = $model->getId();
        $this->assertNotNull($modelId);

        $this->repository->remove($model, true);

        $found = $this->repository->find($modelId);
        $this->assertNull($found);
    }

    public function testModelFromApiResponse(): void
    {
        $apiData = [
            'id' => 'test-deepseek-chat',
            'object' => 'model',
            'owned_by' => 'deepseek',
            'capabilities' => ['chat', 'completion'],
            'pricing' => ['input' => 0.14, 'output' => 0.28],
        ];

        $model = DeepSeekModel::fromApiResponse($apiData, $this->apiKey);

        $this->assertEquals('test-deepseek-chat', $model->getModelId());
        $this->assertEquals('model', $model->getObject());
        $this->assertEquals('deepseek', $model->getOwnedBy());
        $this->assertEquals(['chat', 'completion'], $model->getCapabilities());
        $this->assertEquals(['input' => 0.14, 'output' => 0.28], $model->getPricing());
        $this->assertSame($this->apiKey, $model->getApiKey());
    }

    public function testModelMethods(): void
    {
        $chatModel = $this->createModel('test-deepseek-chat');
        $reasonerModel = $this->createModel('test-deepseek-reasoner');
        $coderModel = $this->createModel('test-deepseek-coder');

        $this->assertTrue($chatModel->isChat());
        $this->assertFalse($chatModel->isReasoner());

        $this->assertFalse($reasonerModel->isChat());
        $this->assertTrue($reasonerModel->isReasoner());

        $this->assertFalse($coderModel->isChat());
        $this->assertFalse($coderModel->isReasoner());
    }

    private function createModel(string $modelId): DeepSeekModel
    {
        $model = new DeepSeekModel();
        $model->setModelId($modelId);
        $model->setApiKey($this->apiKey);
        $model->setOwnedBy('deepseek');
        $model->setObject('model');

        return $model;
    }

    protected function onTearDown(): void
    {
        // 清理测试数据
        $entityManager = static::getEntityManager();
        $entityManager->clear();
    }
}
