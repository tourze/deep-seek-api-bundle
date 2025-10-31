<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use HttpClientBundle\Client\ApiClient;
use HttpClientBundle\Request\RequestInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiLog;
use Tourze\DeepSeekApiBundle\Exception\ApiRequestException;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;

#[WithMonologChannel(channel: 'deep_seek_api')]
class DeepSeekApiClient extends ApiClient
{
    private const BASE_URL = 'https://api.deepseek.com';

    private string $manualApiKey = '';

    private string $currentRequestApiKey = '';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly LockFactory $lockFactory,
        private readonly CacheInterface $cache,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AsyncInsertService $asyncInsertService,
        private readonly ApiKeyManager $apiKeyManager,
        private readonly DeepSeekApiKeyRepository $apiKeyRepository,
    ) {
    }

    public function setApiKey(string $apiKey): void
    {
        $this->manualApiKey = $apiKey;
    }

    public function getBaseUrl(): string
    {
        return self::BASE_URL;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    protected function getLockFactory(): LockFactory
    {
        return $this->lockFactory;
    }

    protected function getCache(): CacheInterface
    {
        return $this->cache;
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    protected function getAsyncInsertService(): AsyncInsertService
    {
        return $this->asyncInsertService;
    }

    protected function getRequestUrl(RequestInterface $request): string
    {
        return self::BASE_URL . $request->getRequestPath();
    }

    protected function getRequestMethod(RequestInterface $request): string
    {
        return $request->getRequestMethod() ?? 'GET';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getRequestOptions(RequestInterface $request): array
    {
        $options = $request->getRequestOptions() ?? [];
        $headers = $options['headers'] ?? [];

        $options['headers'] = array_merge(
            is_array($headers) ? $headers : [],
            [
                'Authorization' => 'Bearer ' . $this->currentRequestApiKey,
                'Content-Type' => 'application/json',
            ]
        );

        /** @var array<string, mixed> $finalOptions */
        $finalOptions = $options;

        return $finalOptions;
    }

    protected function formatResponse(RequestInterface $request, ResponseInterface $response): mixed
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent();

        if ($statusCode >= 200 && $statusCode < 300) {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        }

        $errorMessage = $this->extractErrorMessage($content);

        if (401 === $statusCode) {
            $this->apiKeyManager->markKeyAsInvalid($this->currentRequestApiKey);
        }

        throw new ApiRequestException(sprintf('DeepSeek API error: %s (HTTP %d)', $errorMessage, $statusCode));
    }

    private function extractErrorMessage(string $content): string
    {
        $errorData = json_decode($content, true);

        if (!is_array($errorData)) {
            return $content;
        }

        if (isset($errorData['error']) && is_array($errorData['error']) && isset($errorData['error']['message'])) {
            return is_string($errorData['error']['message']) ? $errorData['error']['message'] : 'Unknown error';
        }

        if (isset($errorData['message']) && is_string($errorData['message'])) {
            return $errorData['message'];
        }

        return 'Unknown error';
    }

    public function request(RequestInterface $request): mixed
    {
        $this->currentRequestApiKey = '' !== $this->manualApiKey ? $this->manualApiKey : $this->apiKeyManager->getNextAvailableKey();
        $apiKeyEntity = $this->apiKeyRepository->findByApiKey($this->currentRequestApiKey);

        if (null === $apiKeyEntity) {
            throw new InvalidApiKeyException('API key entity not found');
        }

        $endpoint = $this->determineEndpoint($request);
        $requestOptions = $request->getRequestOptions();
        $jsonData = null;
        if (is_array($requestOptions) && isset($requestOptions['json']) && is_array($requestOptions['json'])) {
            /** @var array<string, mixed> $jsonData */
            $jsonData = $requestOptions['json'];
        }

        $apiLog = DeepSeekApiLog::createForRequest(
            $apiKeyEntity,
            $endpoint,
            $this->getRequestMethod($request),
            $this->getRequestUrl($request),
            $jsonData
        );

        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request(
                $this->getRequestMethod($request),
                $this->getRequestUrl($request),
                $this->getRequestOptions($request)
            );

            $statusCode = $response->getStatusCode();
            $responseTime = microtime(true) - $startTime;

            $apiLog->completeWithSuccess($statusCode, $responseTime);

            $this->asyncInsertService->asyncInsert($apiLog);

            return $this->formatResponse($request, $response);
        } catch (\Exception $e) {
            $responseTime = microtime(true) - $startTime;
            $apiLog->completeWithError($e->getMessage(), $responseTime);
            $this->asyncInsertService->asyncInsert($apiLog);

            throw $e;
        }
    }

    private function determineEndpoint(RequestInterface $request): string
    {
        $path = $request->getRequestPath();

        if (str_contains($path, '/chat/completions')) {
            return 'chat/completions';
        }

        if (str_contains($path, '/models')) {
            return 'models';
        }

        if (str_contains($path, '/user/balance')) {
            return 'user/balance';
        }

        return 'unknown';
    }
}
