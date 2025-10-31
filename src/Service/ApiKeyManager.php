<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;

class ApiKeyManager
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DeepSeekApiKeyRepository $apiKeyRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param string[] $apiKeys
     */
    public function setApiKeys(array $apiKeys): void
    {
        foreach ($apiKeys as $apiKeyString) {
            $existingKey = $this->apiKeyRepository->findByApiKey($apiKeyString);

            if (null === $existingKey) {
                $apiKey = new DeepSeekApiKey();
                $apiKey->setApiKey($apiKeyString);
                $apiKey->setName(sprintf('Key-%s', substr($apiKeyString, -4)));
                $this->apiKeyRepository->save($apiKey, false);
            } else {
                $existingKey->setIsActive(true);
            }
        }

        $this->entityManager->flush();

        $this->logger->info('API keys configured', [
            'count' => count($apiKeys),
        ]);
    }

    public function addApiKey(string $apiKeyString): void
    {
        $existingKey = $this->apiKeyRepository->findByApiKey($apiKeyString);

        if (null === $existingKey) {
            $apiKey = new DeepSeekApiKey();
            $apiKey->setApiKey($apiKeyString);
            $apiKey->setName(sprintf('Key-%s', substr($apiKeyString, -4)));
            $this->apiKeyRepository->save($apiKey, true);

            $this->logger->info('API key added', [
                'key_suffix' => substr($apiKeyString, -4),
            ]);
        }
    }

    public function getNextAvailableKey(): string
    {
        $key = $this->apiKeyRepository->findNextAvailableKey();

        if (null === $key) {
            throw new InvalidApiKeyException('No valid API keys available');
        }

        $key->incrementUsageCount();
        $key->setLastUseTime(new \DateTimeImmutable());
        $this->apiKeyRepository->save($key, true);

        return $key->getApiKey();
    }

    public function logKeyRotation(string $previousApiKey, string $newApiKey): void
    {
        $this->logger->info('API key rotated', [
            'previous_key' => substr($previousApiKey, -4),
            'new_key' => substr($newApiKey, -4),
        ]);
    }

    public function markKeyAsInvalid(string $apiKeyString): void
    {
        $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyString);

        if (null !== $apiKey) {
            $apiKey->setIsValid(false);
            $apiKey->setLastErrorTime(new \DateTimeImmutable());
            $apiKey->setLastErrorMessage('API key invalid');
            $this->apiKeyRepository->save($apiKey, true);

            $this->logger->warning('API key marked as invalid', [
                'key_suffix' => substr($apiKeyString, -4),
            ]);
        }
    }

    public function markKeyAsValid(string $apiKeyString): void
    {
        $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyString);

        if (null !== $apiKey) {
            $apiKey->setIsValid(true);
            $apiKey->setLastErrorTime(null);
            $apiKey->setLastErrorMessage(null);
            $apiKey->setUpdateTime(new \DateTimeImmutable());
            $this->apiKeyRepository->save($apiKey, true);

            $this->logger->info('API key marked as valid', [
                'key_suffix' => substr($apiKeyString, -4),
            ]);
        }
    }

    /**
     * @return string[]
     */
    public function getAllKeys(): array
    {
        $keys = $this->apiKeyRepository->findAll();

        return array_map(static fn (DeepSeekApiKey $key): string => $key->getApiKey(), $keys);
    }

    public function isKeyValid(string $apiKeyString): bool
    {
        $apiKey = $this->apiKeyRepository->findByApiKey($apiKeyString);

        return null !== $apiKey && $apiKey->isValid();
    }

    public function hasValidKeys(): bool
    {
        $activeKeys = $this->apiKeyRepository->findActiveKeys();

        return count($activeKeys) > 0;
    }

    public function resetAllKeys(): void
    {
        $this->apiKeyRepository->markAllKeysAsValid();

        $this->logger->info('All API keys reset to valid state');
    }

    public function getKeyCount(): int
    {
        $stats = $this->apiKeyRepository->getStatistics();

        return is_int($stats['total']) ? $stats['total'] : 0;
    }

    public function getValidKeyCount(): int
    {
        $stats = $this->apiKeyRepository->getStatistics();

        return is_int($stats['usable']) ? $stats['usable'] : 0;
    }

    /**
     * @return string[]
     */
    public function getValidKeys(): array
    {
        $validKeys = $this->apiKeyRepository->findActiveKeys();

        return array_map(fn (DeepSeekApiKey $key) => $key->getApiKey(), $validKeys);
    }
}
