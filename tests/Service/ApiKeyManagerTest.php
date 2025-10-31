<?php

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Service\ApiKeyManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ApiKeyManager::class)]
#[RunTestsInSeparateProcesses]
class ApiKeyManagerTest extends AbstractIntegrationTestCase
{
    private ApiKeyManager $manager;

    protected function onSetUp(): void
    {
        $this->manager = self::getService(ApiKeyManager::class);

        // 清理数据库中的所有键
        $entityManager = self::getService(EntityManagerInterface::class);
        $repository = self::getService(DeepSeekApiKeyRepository::class);
        $allKeys = $repository->findAll();
        foreach ($allKeys as $key) {
            $entityManager->remove($key);
        }
        $entityManager->flush();
    }

    public function testSetApiKeys(): void
    {
        $keys = ['key1', 'key2', 'key3'];
        $this->manager->setApiKeys($keys);

        $this->assertEquals($keys, $this->manager->getAllKeys());
        $this->assertEquals(3, $this->manager->getKeyCount());
    }

    public function testAddApiKey(): void
    {
        $this->manager->setApiKeys(['key1']);
        $this->manager->addApiKey('key2');

        $this->assertCount(2, $this->manager->getAllKeys());
        $this->assertContains('key2', $this->manager->getAllKeys());
    }

    public function testAddDuplicateApiKey(): void
    {
        $this->manager->setApiKeys(['key1']);
        $this->manager->addApiKey('key1');

        $this->assertCount(1, $this->manager->getAllKeys());
    }

    public function testGetNextAvailableKeyThrowsExceptionWhenNoKeys(): void
    {
        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('No valid API keys available');

        $this->manager->getNextAvailableKey();
    }

    public function testGetNextAvailableKeyReturnsFirstValidKey(): void
    {
        $this->manager->setApiKeys(['key1', 'key2']);

        $this->assertEquals('key1', $this->manager->getNextAvailableKey());
    }

    public function testGetNextAvailableKeyIncreasesUsageCount(): void
    {
        $this->manager->setApiKeys(['key1', 'key2', 'key3']);

        // 第一次调用应该返回key1
        $this->assertEquals('key1', $this->manager->getNextAvailableKey());

        // 第二次调用应该返回key2（因为key1的使用次数增加了）
        $this->assertEquals('key2', $this->manager->getNextAvailableKey());
    }

    public function testLogKeyRotation(): void
    {
        // 验证日志记录方法不抛出异常
        $previousKey = 'sk-1234567890abcdef';
        $newKey = 'sk-abcdef1234567890';

        // 使用 expectNotToPerformAssertions() 明确说明这个测试不需要断言
        // 因为我们只是验证方法能够正常执行而不抛出异常
        $this->expectNotToPerformAssertions();

        $this->manager->logKeyRotation($previousKey, $newKey);
    }

    public function testMarkKeyAsInvalid(): void
    {
        $this->manager->setApiKeys(['key1', 'key2']);
        $this->manager->markKeyAsInvalid('key1');

        $this->assertFalse($this->manager->isKeyValid('key1'));
        $this->assertEquals('key2', $this->manager->getNextAvailableKey());
    }

    public function testMarkKeyAsValid(): void
    {
        $this->manager->setApiKeys(['key1']);
        $this->manager->markKeyAsInvalid('key1');
        $this->manager->markKeyAsValid('key1');

        $this->assertTrue($this->manager->isKeyValid('key1'));
    }

    public function testGetValidKeys(): void
    {
        $this->manager->setApiKeys(['key1', 'key2', 'key3']);
        $this->manager->markKeyAsInvalid('key2');

        $validKeys = $this->manager->getValidKeys();

        $this->assertCount(2, $validKeys);
        $this->assertContains('key1', $validKeys);
        $this->assertContains('key3', $validKeys);
        $this->assertNotContains('key2', $validKeys);
    }

    public function testHasValidKeys(): void
    {
        $this->manager->setApiKeys(['key1', 'key2']);
        $this->assertTrue($this->manager->hasValidKeys());

        $this->manager->markKeyAsInvalid('key1');
        $this->manager->markKeyAsInvalid('key2');

        $this->assertFalse($this->manager->hasValidKeys());
    }

    public function testResetAllKeys(): void
    {
        $this->manager->setApiKeys(['key1', 'key2']);
        $this->assertTrue($this->manager->isKeyValid('key1'), 'key1 should be valid after setApiKeys');
        $this->assertTrue($this->manager->isKeyValid('key2'), 'key2 should be valid after setApiKeys');

        $this->manager->markKeyAsInvalid('key1');
        $this->manager->markKeyAsInvalid('key2');
        $this->assertFalse($this->manager->isKeyValid('key1'), 'key1 should be invalid after marking');
        $this->assertFalse($this->manager->isKeyValid('key2'), 'key2 should be invalid after marking');

        $this->manager->resetAllKeys();

        // Clear entity manager to ensure we get fresh data from DB
        self::getService(EntityManagerInterface::class)->clear();

        $this->assertTrue($this->manager->isKeyValid('key1'), 'key1 should be valid after reset');
        $this->assertTrue($this->manager->isKeyValid('key2'), 'key2 should be valid after reset');
        $this->assertEquals('key1', $this->manager->getNextAvailableKey());
    }

    public function testGetValidKeyCount(): void
    {
        $this->manager->setApiKeys(['key1', 'key2', 'key3']);
        $this->assertEquals(3, $this->manager->getValidKeyCount());

        $this->manager->markKeyAsInvalid('key1');
        $this->assertEquals(2, $this->manager->getValidKeyCount());
    }

    public function testGetNextAvailableKeyThrowsExceptionWhenAllKeysInvalid(): void
    {
        $this->manager->setApiKeys(['key1', 'key2']);
        $this->manager->markKeyAsInvalid('key1');
        $this->manager->markKeyAsInvalid('key2');

        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('No valid API keys available');

        $this->manager->getNextAvailableKey();
    }
}
