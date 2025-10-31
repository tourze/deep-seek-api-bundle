<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Service\LockManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(LockManager::class)]
#[RunTestsInSeparateProcesses]
class LockManagerTest extends AbstractIntegrationTestCase
{
    private LockManager $lockManager;

    protected function onSetUp(): void
    {
        $this->lockManager = self::getService(LockManager::class);
    }

    public function testAcquireLockSuccess(): void
    {
        $key = 'test_lock_' . uniqid();

        $result = $this->lockManager->acquireLock($key);

        self::assertTrue($result, '应该成功获取锁');

        // 清理
        $this->lockManager->releaseLock($key);
    }

    public function testAcquireLockConflict(): void
    {
        $key = 'test_lock_conflict_' . uniqid();

        // 首次获取锁
        $firstResult = $this->lockManager->acquireLock($key);
        self::assertTrue($firstResult, '首次获取锁应该成功');

        // 尝试再次获取同一把锁
        $secondResult = $this->lockManager->acquireLock($key);
        self::assertFalse($secondResult, '重复获取锁应该失败');

        // 清理
        $this->lockManager->releaseLock($key);
    }

    public function testReleaseLockSuccess(): void
    {
        $key = 'test_release_' . uniqid();

        // 获取锁
        $result = $this->lockManager->acquireLock($key);
        self::assertTrue($result, '获取锁应该成功');

        // 释放锁
        $this->lockManager->releaseLock($key);

        // 再次获取锁应该成功
        $secondResult = $this->lockManager->acquireLock($key);
        self::assertTrue($secondResult, '释放锁后应该能再次获取');

        // 清理
        $this->lockManager->releaseLock($key);
    }

    public function testReleaseNonExistentLock(): void
    {
        $key = 'non_existent_lock_' . uniqid();

        // 释放不存在的锁不应该抛出异常
        $this->lockManager->releaseLock($key);

        // 验证能够正常获取锁，说明释放操作没有影响到锁状态
        $canAcquire = $this->lockManager->acquireLock($key);
        self::assertTrue($canAcquire, '释放不存在的锁后应该能正常获取锁');

        // 清理
        $this->lockManager->releaseLock($key);
    }

    public function testGenerateBalanceLockKeyWithApiKey(): void
    {
        $apiKey = 'test_api_key_123';
        $expectedKey = 'deepseek_balance_' . md5($apiKey);

        $result = $this->lockManager->generateBalanceLockKey($apiKey);

        self::assertSame($expectedKey, $result, 'API密钥锁键值应该正确生成');
    }

    public function testGenerateBalanceLockKeyWithNullApiKey(): void
    {
        $expectedKey = 'deepseek_balance_default';

        $result = $this->lockManager->generateBalanceLockKey(null);

        self::assertSame($expectedKey, $result, '空API密钥应该生成默认锁键值');
    }

    public function testLockKeyConsistency(): void
    {
        $apiKey = 'consistency_test_key';

        $key1 = $this->lockManager->generateBalanceLockKey($apiKey);
        $key2 = $this->lockManager->generateBalanceLockKey($apiKey);

        self::assertSame($key1, $key2, '相同API密钥应该生成相同的锁键值');
    }

    public function testMultipleLockManagement(): void
    {
        $key1 = 'multi_test_1_' . uniqid();
        $key2 = 'multi_test_2_' . uniqid();

        // 获取多个锁
        $result1 = $this->lockManager->acquireLock($key1);
        $result2 = $this->lockManager->acquireLock($key2);

        self::assertTrue($result1, '第一个锁应该获取成功');
        self::assertTrue($result2, '第二个锁应该获取成功');

        // 释放锁
        $this->lockManager->releaseLock($key1);
        $this->lockManager->releaseLock($key2);

        // 验证锁已释放
        $result3 = $this->lockManager->acquireLock($key1);
        $result4 = $this->lockManager->acquireLock($key2);

        self::assertTrue($result3, '第一个锁释放后应该能重新获取');
        self::assertTrue($result4, '第二个锁释放后应该能重新获取');

        // 清理
        $this->lockManager->releaseLock($key1);
        $this->lockManager->releaseLock($key2);
    }
}
