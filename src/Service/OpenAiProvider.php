<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;

readonly class OpenAiProvider
{
    public function __construct(private DeepSeekApiKeyRepository $apiKeyRepository)
    {
    }

    /**
     * @return iterable<string, string>
     */
    public function retrieveAuthorization(): iterable
    {
        /** @var DeepSeekApiKey[] $apiKeys */
        $apiKeys = $this->apiKeyRepository->findAll();
        foreach ($apiKeys as $apiKey) {
            yield $apiKey->getApiKey() => $apiKey->getApiKey();
        }
    }
}
