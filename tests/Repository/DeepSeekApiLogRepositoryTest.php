<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiLog;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiLogRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekApiLogRepository::class)]
#[RunTestsInSeparateProcesses]
final class DeepSeekApiLogRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 这个方法必须实现，但我们可以留空
    }

    /**
     * 获取或创建一个测试用的 ApiKey
     */
    private function getTestApiKey(): DeepSeekApiKey
    {
        $apiKeyRepository = self::getService(DeepSeekApiKeyRepository::class);

        // 尝试查找现有的 ApiKey（DataFixtures 创建的）
        $existingApiKeys = $apiKeyRepository->findAll();
        if (count($existingApiKeys) > 0) {
            return $existingApiKeys[0];
        }

        // 如果没有现有的，创建一个简单的 ApiKey
        $apiKey = new DeepSeekApiKey();
        $apiKey->setName('测试API密钥1');
        $apiKey->setApiKey('sk-test-' . bin2hex(random_bytes(16)));
        $apiKey->setDescription('用于测试环境的DeepSeek API密钥1');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);
        $apiKey->setPriority(1);
        $apiKey->setMetadata([
            'environment' => 'test',
            'created_by' => 'test',
        ]);

        $em = self::getEntityManager();
        $em->persist($apiKey);
        $em->flush();

        return $apiKey;
    }

    /**
     * 获取第二个测试用的 ApiKey（用于区分测试）
     */
    private function getTestApiKey2(): DeepSeekApiKey
    {
        $apiKeyRepository = self::getService(DeepSeekApiKeyRepository::class);

        // 尝试查找现有的 ApiKey（DataFixtures 创建的）
        $existingApiKeys = $apiKeyRepository->findAll();
        if (count($existingApiKeys) >= 2) {
            return $existingApiKeys[1];
        }

        // 如果没有现有的，创建一个简单的 ApiKey
        $apiKey = new DeepSeekApiKey();
        $apiKey->setName('测试API密钥2');
        $apiKey->setApiKey('sk-test-' . bin2hex(random_bytes(16)));
        $apiKey->setDescription('用于测试环境的DeepSeek API密钥2');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);
        $apiKey->setPriority(2);
        $apiKey->setMetadata([
            'environment' => 'test',
            'created_by' => 'test',
        ]);

        $em = self::getEntityManager();
        $em->persist($apiKey);
        $em->flush();

        return $apiKey;
    }

    public function testRepositoryInstance(): void
    {
        $repository = $this->getRepository();
        $this->assertInstanceOf(DeepSeekApiLogRepository::class, $repository);
    }

    public function testSaveAndRemove(): void
    {
        $repository = $this->getRepository();

        // 获取或创建测试用的 ApiKey
        $apiKey = $this->getTestApiKey();

        $log = new DeepSeekApiLog();
        $log->setApiKey($apiKey);
        $log->setEndpoint(DeepSeekApiLog::ENDPOINT_LIST_MODELS);
        $log->setUrl('https://api.deepseek.com/test');
        $log->setRequestBody(['test' => true]);
        $log->setResponseBody(['success' => true]);
        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);
        $log->setResponseTime(0.5);

        // Test save with flush to get ID
        $repository->save($log, true);
        $this->assertNotNull($log->getId());

        // Test remove without flush
        $repository->remove($log, false);
    }

    public function testRemove(): void
    {
        $repository = $this->getRepository();

        // 获取或创建测试用的 ApiKey
        $apiKey = $this->getTestApiKey();

        $log = new DeepSeekApiLog();
        $log->setApiKey($apiKey);
        $log->setEndpoint('/test');
        $log->setUrl('https://api.example.com/test');
        $log->setRequestBody(['test' => true]);
        $log->setResponseBody(['success' => true]);
        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);
        $log->setResponseTime(0.5);

        // Save the entity first
        $repository->save($log, true);
        $id = $log->getId();
        $this->assertNotNull($id);

        // Test remove with flush
        $repository->remove($log, true);

        // Verify the entity is removed
        $found = $repository->find($id);
        $this->assertNull($found);
    }

    public function testFindByApiKey(): void
    {
        $repository = $this->getRepository();

        // 获取或创建测试用的 ApiKey
        $apiKey = $this->getTestApiKey();

        $log = new DeepSeekApiLog();
        $log->setApiKey($apiKey);
        $log->setEndpoint('/chat');
        $log->setUrl('https://api.example.com/chat');
        $log->setRequestBody(['message' => 'hello']);
        $log->setResponseBody(['reply' => 'hi']);
        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);
        $log->setResponseTime(1.2);

        $repository->save($log, true);

        $logs = $repository->findByApiKey($apiKey, 10);
        $this->assertIsArray($logs);
        $this->assertNotEmpty($logs);
        $this->assertInstanceOf(DeepSeekApiLog::class, $logs[0]);
        $this->assertSame($apiKey, $logs[0]->getApiKey());

        // Clean up
        $repository->remove($log, true);
    }

    public function testFindByEndpoint(): void
    {
        $repository = $this->getRepository();

        // 获取或创建测试用的 ApiKey
        $apiKey = $this->getTestApiKey();

        $log = new DeepSeekApiLog();
        $log->setApiKey($apiKey);
        $log->setEndpoint('/models');
        $log->setUrl('https://api.example.com/models');
        $log->setRequestBody([]);
        $log->setResponseBody(['models' => []]);
        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);
        $log->setResponseTime(0.8);

        $repository->save($log, true);

        $logs = $repository->findByEndpoint('/models', 10);
        $this->assertIsArray($logs);
        $this->assertNotEmpty($logs);
        $this->assertSame('/models', $logs[0]->getEndpoint());

        // Clean up
        $repository->remove($log, true);
    }

    public function testFindErrors(): void
    {
        $repository = $this->getRepository();

        // 获取或创建测试用的 ApiKey
        $apiKey = $this->getTestApiKey();

        $log = new DeepSeekApiLog();
        $log->setApiKey($apiKey);
        $log->setEndpoint('/chat');
        $log->setUrl('https://api.example.com/chat');
        $log->setRequestBody(['message' => 'hello']);
        $log->setResponseBody(['error' => 'invalid']);
        $log->setStatus(DeepSeekApiLog::STATUS_ERROR);
        $log->setResponseTime(2.5);

        $repository->save($log, true);

        $errors = $repository->findErrors(10);
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertSame(DeepSeekApiLog::STATUS_ERROR, $errors[0]->getStatus());

        // Clean up
        $repository->remove($log, true);
    }

    public function testFindByDateRange(): void
    {
        $repository = $this->getRepository();

        // 获取或创建测试用的 ApiKey
        $apiKey = $this->getTestApiKey();

        $log = new DeepSeekApiLog();
        $log->setApiKey($apiKey);
        $log->setEndpoint('/chat');
        $log->setUrl('https://api.example.com/chat');
        $log->setRequestBody([]);
        $log->setResponseBody([]);
        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);
        $log->setResponseTime(0.5);

        $repository->save($log, true);

        $startDate = new \DateTimeImmutable('-1 hour');
        $endDate = new \DateTimeImmutable('+1 hour');

        $logs = $repository->findByDateRange($startDate, $endDate, $apiKey);
        $this->assertIsArray($logs);
        $this->assertNotEmpty($logs);

        // Clean up
        $repository->remove($log, true);
    }

    public function testGetStatisticsByEndpoint(): void
    {
        $repository = $this->getRepository();

        $statistics = $repository->getStatisticsByEndpoint();
        $this->assertIsArray($statistics);
        // Even if no logs exist, should return an empty array
        $this->assertIsArray($statistics);
    }

    public function testGetErrorStatistics(): void
    {
        $repository = $this->getRepository();

        $statistics = $repository->getErrorStatistics();
        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_requests', $statistics);
        $this->assertArrayHasKey('success_count', $statistics);
        $this->assertArrayHasKey('error_count', $statistics);
        $this->assertArrayHasKey('timeout_count', $statistics);
        $this->assertArrayHasKey('success_rate', $statistics);
        $this->assertArrayHasKey('error_rate', $statistics);
        $this->assertArrayHasKey('avg_response_time', $statistics);
        $this->assertIsInt($statistics['total_requests']);
        $this->assertIsInt($statistics['success_count']);
        $this->assertIsInt($statistics['error_count']);
        $this->assertIsInt($statistics['timeout_count']);
        $this->assertIsFloat($statistics['success_rate']);
        $this->assertIsFloat($statistics['error_rate']);
        $this->assertIsFloat($statistics['avg_response_time']);
    }

    public function testFindSlowRequests(): void
    {
        $repository = $this->getRepository();

        // 获取或创建第二个测试用的 ApiKey
        $apiKey = $this->getTestApiKey2();

        $log = new DeepSeekApiLog();
        $log->setApiKey($apiKey);
        $log->setEndpoint('/slow');
        $log->setUrl('https://api.example.com/slow');
        $log->setRequestBody([]);
        $log->setResponseBody([]);
        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);
        $log->setResponseTime(10.5); // Slow request

        $repository->save($log, true);

        $slowRequests = $repository->findSlowRequests(5.0, 10);
        $this->assertIsArray($slowRequests);
        $this->assertNotEmpty($slowRequests);
        $this->assertGreaterThan(5.0, $slowRequests[0]->getResponseTime());

        // Clean up
        $repository->remove($log, true);
    }

    public function testGetApiKeyPerformance(): void
    {
        $repository = $this->getRepository();

        $performance = $repository->getApiKeyPerformance();
        $this->assertIsArray($performance);
        // Even if no logs exist, should return an empty array
        $this->assertIsArray($performance);
    }

    public function testCleanOldRecords(): void
    {
        $repository = $this->getRepository();

        // This test ensures the method runs without error
        // Actual deletion would require creating very old records
        $deletedCount = $repository->cleanOldRecords(365);
        $this->assertIsInt($deletedCount);
        $this->assertGreaterThanOrEqual(0, $deletedCount);
    }

    protected function createNewEntity(): object
    {
        // 获取或创建第二个测试用的 ApiKey
        $apiKey = $this->getTestApiKey2();

        $log = new DeepSeekApiLog();
        $log->setApiKey($apiKey);
        $log->setEndpoint('/test');
        $log->setUrl('https://api.example.com/test');
        $log->setRequestBody(['test' => true]);
        $log->setResponseBody(['success' => true]);
        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);
        $log->setResponseTime(0.5);

        return $log;
    }

    protected function getRepository(): DeepSeekApiLogRepository
    {
        return self::getService(DeepSeekApiLogRepository::class);
    }
}
