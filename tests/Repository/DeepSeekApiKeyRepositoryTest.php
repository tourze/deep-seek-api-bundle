<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekApiKeyRepository::class)]
#[RunTestsInSeparateProcesses]
final class DeepSeekApiKeyRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 为了确保testFindAllWhenNoRecordsExistShouldReturnEmptyArray测试的兼容性
        // 我们让测试数据通过DataFixtures提供，而不在onSetUp中额外创建
        //
        // 注意：AbstractRepositoryTestCase::testFindAllWhenNoRecordsExistShouldReturnEmptyArray
        // 中的remove调用没有flush参数，这是基类的设计问题，但我们不能修改基类
    }

    public function testRepositoryInstance(): void
    {
        $repository = $this->getRepository();
        $this->assertInstanceOf(DeepSeekApiKeyRepository::class, $repository);
    }

    public function testSaveAndRemove(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('test-key-123');
        $apiKey->setName('Test Key');

        // Test save without flush
        $repository->save($apiKey, false);
        // 手动flush以获取ID
        self::getEntityManager()->flush();
        $this->assertNotNull($apiKey->getId());

        // Test remove without flush
        $repository->remove($apiKey, false);
    }

    public function testRemove(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('remove-test-key');
        $apiKey->setName('Remove Test Key');

        // Save the entity first
        $repository->save($apiKey, true);
        $id = $apiKey->getId();
        $this->assertNotNull($id);

        // Test remove with flush
        $repository->remove($apiKey, true);

        // Verify the entity is removed
        $found = $repository->find($id);
        $this->assertNull($found);
    }

    public function testFindByApiKey(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('unique-test-key');
        $apiKey->setName('Unique Test Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);

        $repository->save($apiKey, true);

        $found = $repository->findByApiKey('unique-test-key');
        $this->assertInstanceOf(DeepSeekApiKey::class, $found);
        $this->assertSame('unique-test-key', $found->getApiKey());

        // Clean up
        $repository->remove($apiKey, true);
    }

    public function testFindActiveKeys(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('active-key');
        $apiKey->setName('Active Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);

        $repository->save($apiKey, true);

        $activeKeys = $repository->findActiveKeys();
        $this->assertIsArray($activeKeys);
        $this->assertNotEmpty($activeKeys);

        // Clean up
        $repository->remove($apiKey, true);
    }

    public function testFindActiveAndValidKeys(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('valid-key');
        $apiKey->setName('Valid Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);

        $repository->save($apiKey, true);

        $validKeys = $repository->findActiveAndValidKeys();
        $this->assertIsArray($validKeys);
        $this->assertNotEmpty($validKeys);

        // Clean up
        $repository->remove($apiKey, true);
    }

    public function testFindNextAvailableKey(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('available-key');
        $apiKey->setName('Available Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);
        $apiKey->setPriority(100);

        $repository->save($apiKey, true);

        $availableKey = $repository->findNextAvailableKey();
        $this->assertInstanceOf(DeepSeekApiKey::class, $availableKey);

        // Clean up
        $repository->remove($apiKey, true);
    }

    public function testGetStatistics(): void
    {
        $repository = $this->getRepository();

        $statistics = $repository->getStatistics();
        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total', $statistics);
        $this->assertArrayHasKey('active', $statistics);
        $this->assertArrayHasKey('valid', $statistics);
        $this->assertArrayHasKey('usable', $statistics);
        $this->assertArrayHasKey('inactive', $statistics);
        $this->assertArrayHasKey('invalid', $statistics);
        $this->assertIsInt($statistics['total']);
        $this->assertIsInt($statistics['active']);
        $this->assertIsInt($statistics['valid']);
        $this->assertIsInt($statistics['usable']);
        $this->assertIsInt($statistics['inactive']);
        $this->assertIsInt($statistics['invalid']);
    }

    public function testFindInvalidKeys(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('invalid-key');
        $apiKey->setName('Invalid Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(false);

        $repository->save($apiKey, true);

        $invalidKeys = $repository->findInvalidKeys();
        $this->assertIsArray($invalidKeys);
        $this->assertNotEmpty($invalidKeys);

        // Clean up
        $repository->remove($apiKey, true);
    }

    public function testMarkAllKeysAsValid(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('to-validate-key');
        $apiKey->setName('To Validate Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(false);

        $repository->save($apiKey, true);

        $updatedCount = $repository->markAllKeysAsValid();
        $this->assertIsInt($updatedCount);
        $this->assertGreaterThan(0, $updatedCount);

        // Clean up
        $repository->remove($apiKey, true);
    }

    public function testFindByPriority(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('priority-key');
        $apiKey->setName('Priority Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);
        $apiKey->setPriority(100);

        $repository->save($apiKey, true);

        $priorityKeys = $repository->findByPriority();
        $this->assertIsArray($priorityKeys);
        $this->assertNotEmpty($priorityKeys);

        // Clean up
        $repository->remove($apiKey, true);
    }

    public function testFindKeysNeedingBalanceSync(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('sync-balance-key');
        $apiKey->setName('Sync Balance Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);
        // 设置一个很久以前的同步时间，确保需要同步
        $apiKey->setLastBalanceSyncTime(new \DateTimeImmutable('-2 hours'));

        $repository->save($apiKey, true);

        $keysNeedingSync = $repository->findKeysNeedingBalanceSync();
        $this->assertIsArray($keysNeedingSync);
        $this->assertNotEmpty($keysNeedingSync);

        // Clean up
        $repository->remove($apiKey, true);
    }

    public function testFindKeysNeedingModelsSync(): void
    {
        $repository = $this->getRepository();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('sync-models-key');
        $apiKey->setName('Sync Models Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);
        // 设置一个很久以前的同步时间，确保需要同步
        $apiKey->setLastModelsSyncTime(new \DateTimeImmutable('-25 hours'));

        $repository->save($apiKey, true);

        $keysNeedingSync = $repository->findKeysNeedingModelsSync();
        $this->assertIsArray($keysNeedingSync);
        $this->assertNotEmpty($keysNeedingSync);

        // Clean up
        $repository->remove($apiKey, true);
    }

    protected function createNewEntity(): object
    {
        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('test-key-' . uniqid());
        $apiKey->setName('Test Key ' . uniqid());
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);
        $apiKey->setPriority(100);

        return $apiKey;
    }

    protected function getRepository(): DeepSeekApiKeyRepository
    {
        return self::getService(DeepSeekApiKeyRepository::class);
    }
}
