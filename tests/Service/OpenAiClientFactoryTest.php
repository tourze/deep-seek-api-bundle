<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Exception\ApiRequestException;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\DeepSeekApiBundle\Service\OpenAiClientFactory;
use Tourze\OpenAiContracts\Authentication\AuthenticationStrategyInterface;
use Tourze\OpenAiContracts\Client\AbstractOpenAiClient;

/**
 * @internal
 */
#[CoversClass(OpenAiClientFactory::class)]
class OpenAiClientFactoryTest extends WebTestCase
{
    private OpenAiClientFactory $factory;

    private MockHttpClient $mockHttpClient;

    public function testCreateClientWithDefaultConfig(): void
    {
        $apiKey = $this->createApiKeyEntity();
        $client = $this->factory->createClient($apiKey);

        $this->assertInstanceOf(AbstractOpenAiClient::class, $client);
        $this->assertEquals('sk-test', $client->getApiKey());
        $this->assertEquals('Test Key', $client->getName());
        $this->assertNull($client->getAuthenticationStrategy());
    }

    private function createApiKeyEntity(string $apiKey = 'sk-test', string $name = 'Test Key', bool $active = true, bool $valid = true): DeepSeekApiKey
    {
        $entity = new DeepSeekApiKey();
        $entity->setApiKey($apiKey);
        $entity->setName($name);
        $entity->setIsActive($active);
        $entity->setIsValid($valid);

        return $entity;
    }

    public function testCreateClientWithInactiveApiKey(): void
    {
        $apiKey = $this->createApiKeyEntity('sk-test', 'Inactive Key', false, true);

        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('API key "Inactive Key" cannot be used');

        $this->factory->createClient($apiKey);
    }

    public function testCreateClientWithInvalidApiKey(): void
    {
        $apiKey = $this->createApiKeyEntity('sk-test', 'Invalid Key', true, false);

        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('API key "Invalid Key" cannot be used');

        $this->factory->createClient($apiKey);
    }

    public function testCreateClientWithCustomConfig(): void
    {
        $apiKey = $this->createApiKeyEntity('sk-custom', 'Custom Key');
        $config = [
            'base_uri' => 'https://custom.deepseek.com',
            'timeout' => 60,
            'max_retries' => 5,
            'proxy' => 'http://proxy.example.com:8080',
            'verify_peer' => false,
            'verify_host' => false,
        ];

        $client = $this->factory->createClient($apiKey, $config);

        $this->assertInstanceOf(AbstractOpenAiClient::class, $client);
        $this->assertEquals('sk-custom', $client->getApiKey());
        $this->assertEquals('Custom Key', $client->getName());
    }

    public function testCreateClientFromApiKeyStringWithDefaultConfig(): void
    {
        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('sk-test-string');
        $apiKey->setName('Test Key String');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);

        $client = $this->factory->createClient($apiKey);

        $this->assertInstanceOf(AbstractOpenAiClient::class, $client);
        $this->assertEquals('sk-test-string', $client->getApiKey());
        $this->assertNull($client->getAuthenticationStrategy());
    }

    public function testCreateClientFromApiKeyStringWithCustomConfig(): void
    {
        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('sk-custom-string');
        $apiKey->setName('Custom Key String');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);

        $config = [
            'base_uri' => 'https://custom.deepseek.com',
            'timeout' => 60,
            'max_retries' => 5,
            'proxy' => 'http://proxy.example.com:8080',
            'verify_peer' => false,
            'verify_host' => false,
        ];

        $client = $this->factory->createClient($apiKey, $config);

        $this->assertInstanceOf(AbstractOpenAiClient::class, $client);
        $this->assertEquals('sk-custom-string', $client->getApiKey());
    }

    public function testCreateClientFromApiKeyStringWithAuthenticationStrategy(): void
    {
        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('sk-test');
        $apiKey->setName('Auth Test Key');
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);

        $strategy = $this->createMock(AuthenticationStrategyInterface::class);
        $config = [
            'authentication_strategy' => $strategy,
        ];

        $client = $this->factory->createClient($apiKey, $config);

        $this->assertInstanceOf(AbstractOpenAiClient::class, $client);
        $this->assertSame($strategy, $client->getAuthenticationStrategy());
    }

    public function testClientCanMakeRequests(): void
    {
        $responseData = [
            'object' => 'list',
            'data' => [
                ['id' => 'deepseek-chat', 'object' => 'model', 'created' => 1234567890, 'owned_by' => 'deepseek'],
                ['id' => 'deepseek-coder', 'object' => 'model', 'created' => 1234567891, 'owned_by' => 'deepseek'],
            ],
        ];

        $jsonData = json_encode($responseData);
        $this->assertNotFalse($jsonData, 'JSON encoding should not fail');

        $this->mockHttpClient->setResponseFactory([
            new MockResponse($jsonData, ['http_code' => 200]),
        ]);

        $apiKey = $this->createApiKeyEntity();
        $client = $this->factory->createClient($apiKey);
        $response = $client->listModels();

        $this->assertEquals('list', $response->getObject());
        $this->assertCount(2, $response->getData());
    }

    public function testClientHandlesErrors(): void
    {
        $errorResponse = [
            'error' => [
                'message' => 'Invalid API key',
                'type' => 'authentication_error',
                'code' => 'invalid_api_key',
            ],
        ];

        $jsonError = json_encode($errorResponse);
        $this->assertNotFalse($jsonError, 'JSON encoding should not fail');

        $this->mockHttpClient->setResponseFactory([
            new MockResponse($jsonError, ['http_code' => 401]),
        ]);

        $apiKey = $this->createApiKeyEntity();
        $client = $this->factory->createClient($apiKey);

        $this->expectException(ApiRequestException::class);
        $this->expectExceptionMessage('Invalid API key');

        $client->listModels();
    }

    public function testClientRetriesOnTransportError(): void
    {
        $successResponse = [
            'object' => 'list',
            'data' => [],
        ];

        $jsonSuccess = json_encode($successResponse);
        $this->assertNotFalse($jsonSuccess, 'JSON encoding should not fail');

        $responses = [
            new MockResponse('', ['error' => 'Network error']),
            new MockResponse('', ['error' => 'Network error']),
            new MockResponse($jsonSuccess, ['http_code' => 200]),
        ];

        $this->mockHttpClient->setResponseFactory($responses);

        $apiKey = $this->createApiKeyEntity();
        $client = $this->factory->createClient($apiKey, ['max_retries' => 3]);
        $response = $client->listModels();

        $this->assertEquals('list', $response->getObject());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockHttpClient = new MockHttpClient();
        $this->factory = new OpenAiClientFactory($this->mockHttpClient);
    }
}
