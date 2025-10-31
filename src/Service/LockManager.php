<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

/**
 * 分布式锁管理器
 */
class LockManager
{
    /** @var array<string, resource> 锁句柄存储 */
    private array $lockHandles = [];

    /**
     * 获取分布式锁
     */
    public function acquireLock(string $key, int $timeout = 30): bool
    {
        $lockFile = sys_get_temp_dir() . '/' . $key . '.lock';
        $lockHandle = fopen($lockFile, 'w');

        if (false === $lockHandle) {
            return false;
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);

            return false;
        }

        $this->lockHandles[$key] = $lockHandle;

        return true;
    }

    /**
     * 释放分布式锁
     */
    public function releaseLock(string $key): void
    {
        if (!isset($this->lockHandles[$key])) {
            return;
        }

        $lockHandle = $this->lockHandles[$key];
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        unset($this->lockHandles[$key]);

        @unlink(sys_get_temp_dir() . '/' . $key . '.lock');
    }

    /**
     * 生成余额锁键值
     */
    public function generateBalanceLockKey(?string $apiKey): string
    {
        return null !== $apiKey ? 'deepseek_balance_' . md5($apiKey) : 'deepseek_balance_default';
    }
}
