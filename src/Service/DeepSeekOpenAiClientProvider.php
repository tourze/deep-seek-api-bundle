<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;
use Tourze\OpenAiContracts\Provider\OpenAiClientProviderInterface;
use Tourze\OpenAiContracts\Response\ModelListResponseInterface;

#[WithMonologChannel(channel: 'deepseek-api')]
#[AutoconfigureTag(name: OpenAiClientProviderInterface::TAG_NAME)]
readonly class DeepSeekOpenAiClientProvider implements OpenAiClientProviderInterface
{
    public function __construct(
        private DeepSeekApiKeyRepository $apiKeyRepository,
        private OpenAiClientFactory $clientFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @return iterable<OpenAiCompatibleClientInterface>
     */
    public function fetchOpenAiClientWithConfig(array $config = []): iterable
    {
        $apiKeys = $this->apiKeyRepository->findActiveAndValidKeys();

        foreach ($apiKeys as $apiKey) {
            try {
                $client = $this->clientFactory->createClient($apiKey, $config);
                yield $client;
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    /**
     * @return OpenAiCompatibleClientInterface|null
     */
    public function getFirstAvailableClient(): ?OpenAiCompatibleClientInterface
    {
        foreach ($this->fetchOpenAiClient() as $client) {
            if ($client->isAvailable()) {
                return $client;
            }
        }

        return null;
    }

    /**
     * @return iterable<OpenAiCompatibleClientInterface>
     */
    public function fetchOpenAiClient(): iterable
    {
        $apiKeys = $this->apiKeyRepository->findActiveAndValidKeys();

        foreach ($apiKeys as $apiKey) {
            try {
                $client = $this->clientFactory->createClient($apiKey);
                yield $client;
            } catch (\Exception $e) {
                $this->logger->error('Error while creating OpenAI client', [
                    'error' => $e,
                    'key' => $apiKey,
                ]);
                continue;
            }
        }
    }

    /**
     * @param string $modelId
     * @return OpenAiCompatibleClientInterface|null
     */
    public function getClientForModel(string $modelId): ?OpenAiCompatibleClientInterface
    {
        foreach ($this->fetchOpenAiClient() as $client) {
            try {
                $models = $client->listModels();
                // ModelListResponseInterface 并不保证 hasModel，改为遍历判断
                if ($this->responseContainsModel($models, $modelId)) {
                    return $client;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    private function responseContainsModel(ModelListResponseInterface $models, string $modelId): bool
    {
        foreach ($models->getData() as $model) {
            if ($model->getId() === $modelId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return OpenAiCompatibleClientInterface|null
     */
    public function getClientByPriority(): ?OpenAiCompatibleClientInterface
    {
        $apiKeys = $this->apiKeyRepository->findByPriority();

        foreach ($apiKeys as $apiKey) {
            try {
                $client = $this->clientFactory->createClient($apiKey);
                if ($client->isAvailable()) {
                    return $client;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }
}
