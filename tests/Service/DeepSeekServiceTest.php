<?php

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Service\DeepSeekService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekService::class)]
#[RunTestsInSeparateProcesses]
class DeepSeekServiceTest extends AbstractIntegrationTestCase
{
    private DeepSeekService $service;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(DeepSeekService::class, $this->service);
    }

    public function testListModelsForAllKeysWithNoKeys(): void
    {
        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('No API keys configured');

        $this->service->listModelsForAllKeys();
    }

    public function testGetBalanceForAllKeysWithNoKeys(): void
    {
        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('No API keys configured');

        $this->service->getBalanceForAllKeys();
    }

    public function testGetTotalBalance(): void
    {
        $totalBalance = $this->service->getTotalBalance();

        $this->assertIsArray($totalBalance);
    }

    public function testValidateAllApiKeys(): void
    {
        $result = $this->service->validateAllApiKeys();

        $this->assertIsArray($result);
    }

    public function testValidateApiKeyWithInvalidKey(): void
    {
        $result = $this->service->validateApiKey('invalid-key');

        $this->assertFalse($result);
    }

    public function testSyncAllData(): void
    {
        // 测试空数据同步（没有 API keys）
        $result = $this->service->syncAllData();

        // 验证返回结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('models', $result);
        $this->assertArrayHasKey('balances', $result);
        $this->assertEmpty($result['models']);
        $this->assertEmpty($result['balances']);
    }

    protected function onSetUp(): void
    {
        // 清空可能由 fixtures 注入的 DeepSeekApiKey，确保"无 key"测试的前置条件
        $entityManager = self::getService(EntityManagerInterface::class);
        $repository = self::getService(DeepSeekApiKeyRepository::class);
        foreach ($repository->findAll() as $key) {
            $entityManager->remove($key);
        }
        $entityManager->flush();

        $this->service = self::getService(DeepSeekService::class);
    }
}
