<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\OpenAiContracts\Authentication\AuthenticationStrategyInterface;
use Tourze\OpenAiContracts\Client\AbstractOpenAiClient;

class OpenAiClientFactory
{
    private HttpClientInterface $httpClient;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function createClient(DeepSeekApiKey $apiKey, array $config = []): AbstractOpenAiClient
    {
        $this->validateApiKey($apiKey);

        $clientConfig = $this->parseClientConfig($config);
        $httpClientConfig = $this->buildHttpClientConfig($clientConfig, $config);
        $httpClient = $this->httpClient->withOptions($httpClientConfig);

        $client = $this->createOpenAiClient($httpClient, $clientConfig, $apiKey);
        $this->configureAuthenticationStrategy($client, $config);

        return $client;
    }

    private function validateApiKey(DeepSeekApiKey $apiKey): void
    {
        if (!$apiKey->canBeUsed()) {
            throw new InvalidApiKeyException(sprintf('API key "%s" cannot be used (active: %s, valid: %s)', $apiKey->getName(), $apiKey->isActive() ? 'yes' : 'no', $apiKey->isValid() ? 'yes' : 'no'));
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array{base_uri: string, timeout: int, max_retries: int}
     */
    private function parseClientConfig(array $config): array
    {
        return [
            'base_uri' => is_string($config['base_uri'] ?? null) ? $config['base_uri'] : 'https://api.deepseek.com',
            'timeout' => is_int($config['timeout'] ?? null) ? $config['timeout'] : 30,
            'max_retries' => is_int($config['max_retries'] ?? null) ? $config['max_retries'] : 3,
        ];
    }

    /**
     * @param array{base_uri: string, timeout: int, max_retries: int} $clientConfig
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildHttpClientConfig(array $clientConfig, array $config): array
    {
        return $this->addOptionalConfigValues([
            'base_uri' => $clientConfig['base_uri'],
            'timeout' => $clientConfig['timeout'],
            'max_redirects' => 0,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ], $config);
    }

    /**
     * @param array<string, mixed> $httpClientConfig
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function addOptionalConfigValues(array $httpClientConfig, array $config): array
    {
        $optionalKeys = ['proxy', 'verify_peer', 'verify_host'];

        foreach ($optionalKeys as $key) {
            if (isset($config[$key])) {
                $httpClientConfig[$key] = $config[$key];
            }
        }

        return $httpClientConfig;
    }

    /**
     * @param array{base_uri: string, timeout: int, max_retries: int} $clientConfig
     */
    private function createOpenAiClient(HttpClientInterface $httpClient, array $clientConfig, DeepSeekApiKey $apiKey): DeepSeekOpenAiClient
    {
        $client = new DeepSeekOpenAiClient($httpClient, $clientConfig['max_retries']);
        $client->setName($apiKey->getName());
        $client->setBaseUrl($clientConfig['base_uri']);
        $client->setApiKey($apiKey->getApiKey());

        return $client;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureAuthenticationStrategy(DeepSeekOpenAiClient $client, array $config): void
    {
        if (!isset($config['authentication_strategy'])) {
            return;
        }

        $strategy = $config['authentication_strategy'];
        if ($strategy instanceof AuthenticationStrategyInterface) {
            $client->setAuthenticationStrategy($strategy);
        }
    }
}
