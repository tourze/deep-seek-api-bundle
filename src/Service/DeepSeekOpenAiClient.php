<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\DeepSeekApiBundle\Exception\ApiRequestException;
use Tourze\DeepSeekApiBundle\Response\DeepSeekBalanceResponse;
use Tourze\DeepSeekApiBundle\Response\DeepSeekChatCompletionResponse;
use Tourze\DeepSeekApiBundle\Response\DeepSeekModelListResponse;
use Tourze\OpenAiContracts\Client\AbstractOpenAiClient;
use Tourze\OpenAiContracts\Response\BalanceResponseInterface;
use Tourze\OpenAiContracts\Response\ChatCompletionResponseInterface;
use Tourze\OpenAiContracts\Response\ModelListResponseInterface;

class DeepSeekOpenAiClient extends AbstractOpenAiClient
{
    private string $baseUrl = 'https://api.deepseek.com';

    private string $name = 'DeepSeek';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly int $maxRetries = 3,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function doRequest(string $endpoint, string $method, array $data = []): array
    {
        $requestOptions = $this->buildRequestOptions($method, $data);

        return $this->executeRequestWithRetry($endpoint, $method, $requestOptions);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildRequestOptions(string $method, array $data): array
    {
        $headers = [];
        $options = [];

        $authResult = $this->applyAuthentication($headers, $options);
        $headers = $authResult['headers'];
        $options = $authResult['options'];

        $requestOptions = ['headers' => $headers];
        $requestOptions = $this->addDataToRequest($requestOptions, $method, $data);

        return array_merge($requestOptions, $options);
    }

    /**
     * @param array<string, mixed> $requestOptions
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function addDataToRequest(array $requestOptions, string $method, array $data): array
    {
        if ([] === $data) {
            return $requestOptions;
        }

        if ('GET' === $method) {
            $requestOptions['query'] = $data;
        } else {
            $requestOptions['json'] = $data;
        }

        return $requestOptions;
    }

    /**
     * @param array<string, mixed> $requestOptions
     * @return array<string, mixed>
     */
    private function executeRequestWithRetry(string $endpoint, string $method, array $requestOptions): array
    {
        $lastException = null;

        for ($attempt = 0; $attempt < $this->maxRetries; ++$attempt) {
            try {
                return $this->executeSingleRequest($endpoint, $method, $requestOptions);
            } catch (TransportExceptionInterface $e) {
                $lastException = $e;
                $this->handleRetryDelay($attempt);
            }
        }

        throw new ApiRequestException(sprintf('Request failed after %d attempts: %s', $this->maxRetries, $lastException?->getMessage() ?? 'Unknown error'), 0, $lastException);
    }

    /**
     * @param array<string, mixed> $requestOptions
     * @return array<string, mixed>
     */
    private function executeSingleRequest(string $endpoint, string $method, array $requestOptions): array
    {
        $response = $this->httpClient->request($method, $endpoint, $requestOptions);
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if (200 <= $statusCode && $statusCode < 300) {
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                throw new ApiRequestException('Invalid JSON response format');
            }

            // 确保返回 array<string, mixed>
            $result = [];
            foreach ($decoded as $key => $value) {
                if (is_string($key)) {
                    $result[$key] = $value;
                }
            }

            return $result;
        }

        $this->handleErrorResponse($statusCode, $content);
    }

    private function handleErrorResponse(int $statusCode, string $content): never
    {
        $errorData = json_decode($content, true);

        if (!is_array($errorData)) {
            $errorData = [];
        }

        // 确保 errorData 是 array<string, mixed>
        $processedErrorData = [];
        foreach ($errorData as $key => $value) {
            if (is_string($key)) {
                $processedErrorData[$key] = $value;
            }
        }

        if (isset($processedErrorData['error'])) {
            throw new ApiRequestException($this->parseError($processedErrorData));
        }

        throw new ApiRequestException(sprintf('HTTP %d: %s', $statusCode, $content));
    }

    private function handleRetryDelay(int $attempt): void
    {
        if ($attempt < $this->maxRetries - 1) {
            usleep((int) (pow(2, $attempt) * 1000000));
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function parseError(array $response): string
    {
        // 检查标准的错误消息格式
        if (isset($response['error']) && is_array($response['error'])) {
            $error = $response['error'];
            if (isset($error['message']) && is_string($error['message'])) {
                return $error['message'];
            }
        }

        // 检查简单的字符串错误格式
        if (isset($response['error']) && is_string($response['error'])) {
            return $response['error'];
        }

        // 如果没有标准错误格式，返回JSON字符串
        $encoded = json_encode($response, JSON_UNESCAPED_UNICODE);

        return false !== $encoded ? $encoded : 'Unknown error';
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function createChatCompletionResponse(array $data): ChatCompletionResponseInterface
    {
        return new DeepSeekChatCompletionResponse($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function createModelListResponse(array $data): ModelListResponseInterface
    {
        return new DeepSeekModelListResponse($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function createBalanceResponse(array $data): BalanceResponseInterface
    {
        return new DeepSeekBalanceResponse($data);
    }
}
