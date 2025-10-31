<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Exception\ApiRequestException;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Response\DeepSeekModelListResponse;
use Tourze\DeepSeekApiBundle\Service\DeepSeekOpenAiClientProvider;
use Tourze\DeepSeekApiBundle\Service\OpenAiClientFactory;
use Tourze\OpenAiContracts\Client\AbstractOpenAiClient;
use Tourze\OpenAiContracts\DTO\Model;
use Tourze\OpenAiContracts\Provider\OpenAiClientProviderInterface;

/**
 * @internal
 */
#[CoversClass(DeepSeekOpenAiClientProvider::class)]
class DeepSeekOpenAiClientProviderTest extends WebTestCase
{
    private DeepSeekOpenAiClientProvider $provider;

    private DeepSeekApiKeyRepository|MockObject $apiKeyRepository;

    private OpenAiClientFactory|MockObject $clientFactory;

    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKeyRepository = $this->createMock(DeepSeekApiKeyRepository::class);
        $this->clientFactory = $this->createMock(OpenAiClientFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->provider = new DeepSeekOpenAiClientProvider(
            $this->apiKeyRepository,
            $this->clientFactory,
            $this->logger
        );
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(OpenAiClientProviderInterface::class, $this->provider);
    }

    public function testFetchOpenAiClientWithConfig(): void
    {
        $apiKey1 = $this->createApiKeyEntity('key1', 'API Key 1');
        $apiKey2 = $this->createApiKeyEntity('key2', 'API Key 2');

        $client1 = $this->createMock(AbstractOpenAiClient::class);
        $client2 = $this->createMock(AbstractOpenAiClient::class);

        $this->apiKeyRepository->expects($this->once())
            ->method('findActiveAndValidKeys')
            ->willReturn([$apiKey1, $apiKey2])
        ;

        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willReturnMap([
                [$apiKey1, [], $client1],
                [$apiKey2, [], $client2],
            ])
        ;

        $clients = iterator_to_array($this->provider->fetchOpenAiClientWithConfig());

        $this->assertCount(2, $clients);
        $this->assertSame($client1, $clients[0]);
        $this->assertSame($client2, $clients[1]);
    }

    private function createApiKeyEntity(string $apiKey, string $name): DeepSeekApiKey
    {
        $entity = new DeepSeekApiKey();
        $entity->setApiKey($apiKey);
        $entity->setName($name);
        $entity->setIsActive(true);
        $entity->setIsValid(true);

        return $entity;
    }

    public function testFetchOpenAiClientWithConfigSkipsFailedCreation(): void
    {
        $apiKey1 = $this->createApiKeyEntity('key1', 'API Key 1');
        $apiKey2 = $this->createApiKeyEntity('key2', 'API Key 2');

        $client2 = $this->createMock(AbstractOpenAiClient::class);

        $this->apiKeyRepository->expects($this->once())
            ->method('findActiveAndValidKeys')
            ->willReturn([$apiKey1, $apiKey2])
        ;

        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willReturnCallback(function ($key) use ($apiKey1, $client2) {
                if ($key === $apiKey1) {
                    throw new ApiRequestException('Failed to create client');
                }

                return $client2;
            })
        ;

        $clients = iterator_to_array($this->provider->fetchOpenAiClientWithConfig());

        $this->assertCount(1, $clients);
        $this->assertSame($client2, $clients[0]);
    }

    public function testGetFirstAvailableClient(): void
    {
        $apiKey1 = $this->createApiKeyEntity('key1', 'API Key 1');
        $apiKey2 = $this->createApiKeyEntity('key2', 'API Key 2');

        $client1 = $this->createMock(AbstractOpenAiClient::class);
        $client1->method('isAvailable')->willReturn(false);

        $client2 = $this->createMock(AbstractOpenAiClient::class);
        $client2->method('isAvailable')->willReturn(true);

        $this->apiKeyRepository->expects($this->once())
            ->method('findActiveAndValidKeys')
            ->willReturn([$apiKey1, $apiKey2])
        ;

        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willReturnMap([
                [$apiKey1, [], $client1],
                [$apiKey2, [], $client2],
            ])
        ;

        $result = $this->provider->getFirstAvailableClient();

        $this->assertSame($client2, $result);
    }

    public function testGetFirstAvailableClientReturnsNullWhenNoneAvailable(): void
    {
        $this->apiKeyRepository->expects($this->once())
            ->method('findActiveAndValidKeys')
            ->willReturn([])
        ;

        $result = $this->provider->getFirstAvailableClient();

        $this->assertNull($result);
    }

    public function testGetClientForModel(): void
    {
        $apiKey1 = $this->createApiKeyEntity('key1', 'API Key 1');
        $apiKey2 = $this->createApiKeyEntity('key2', 'API Key 2');

        // 创建 Model DTO
        $model1 = new Model('gpt-4', 'model', 1234567890, 'openai');
        $model2 = new Model('deepseek-chat', 'model', 1234567890, 'deepseek');

        $modelList1 = $this->createMock(DeepSeekModelListResponse::class);
        $modelList1->method('getData')->willReturn([$model1]);

        $modelList2 = $this->createMock(DeepSeekModelListResponse::class);
        $modelList2->method('getData')->willReturn([$model1, $model2]);

        $client1 = $this->createMock(AbstractOpenAiClient::class);
        $client1->method('listModels')->willReturn($modelList1);

        $client2 = $this->createMock(AbstractOpenAiClient::class);
        $client2->method('listModels')->willReturn($modelList2);

        $this->apiKeyRepository->expects($this->once())
            ->method('findActiveAndValidKeys')
            ->willReturn([$apiKey1, $apiKey2])
        ;

        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willReturnMap([
                [$apiKey1, [], $client1],
                [$apiKey2, [], $client2],
            ])
        ;

        $result = $this->provider->getClientForModel('deepseek-chat');

        $this->assertSame($client2, $result);
    }

    public function testGetClientByPriority(): void
    {
        $apiKey1 = $this->createApiKeyEntity('key1', 'API Key 1');
        $apiKey2 = $this->createApiKeyEntity('key2', 'API Key 2');

        $client1 = $this->createMock(AbstractOpenAiClient::class);
        $client1->method('isAvailable')->willReturn(false);

        $client2 = $this->createMock(AbstractOpenAiClient::class);
        $client2->method('isAvailable')->willReturn(true);

        $this->apiKeyRepository->expects($this->once())
            ->method('findByPriority')
            ->willReturn([$apiKey1, $apiKey2])
        ;

        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willReturnMap([
                [$apiKey1, [], $client1],
                [$apiKey2, [], $client2],
            ])
        ;

        $result = $this->provider->getClientByPriority();

        $this->assertSame($client2, $result);
    }
}
