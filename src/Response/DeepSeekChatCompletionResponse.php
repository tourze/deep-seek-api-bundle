<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Response;

use Tourze\OpenAiContracts\DTO\ChatChoice;
use Tourze\OpenAiContracts\DTO\ChatMessage;
use Tourze\OpenAiContracts\DTO\Usage;
use Tourze\OpenAiContracts\Response\ChatCompletionResponseInterface;

class DeepSeekChatCompletionResponse implements ChatCompletionResponseInterface, \JsonSerializable
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        /** @phpstan-ignore-next-line */
        return new static($data);
    }

    public function getId(): ?string
    {
        $id = $this->data['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function getObject(): string
    {
        $object = $this->data['object'] ?? 'chat.completion';

        return is_string($object) ? $object : 'chat.completion';
    }

    public function getCreated(): ?int
    {
        $created = $this->data['created'] ?? null;

        return null !== $created && is_numeric($created) ? (int) $created : null;
    }

    public function getModel(): ?string
    {
        $model = $this->data['model'] ?? null;

        return is_string($model) ? $model : null;
    }

    /**
     * @return ChatChoice[]
     */
    public function getChoices(): array
    {
        $choices = [];
        $choicesData = $this->data['choices'] ?? [];
        if (!is_array($choicesData)) {
            return [];
        }

        foreach ($choicesData as $choiceData) {
            if (!is_array($choiceData)) {
                continue;
            }
            $messageData = $choiceData['message'] ?? [];
            if (!is_array($messageData)) {
                continue;
            }
            $message = new ChatMessage(
                is_string($messageData['role'] ?? null) ? $messageData['role'] : 'assistant',
                is_string($messageData['content'] ?? null) ? $messageData['content'] : ''
            );
            $index = $choiceData['index'] ?? 0;
            $finishReason = $choiceData['finish_reason'] ?? null;

            $choices[] = new ChatChoice(
                is_numeric($index) ? (int) $index : 0,
                $message,
                null,
                is_string($finishReason) ? $finishReason : null
            );
        }

        return $choices;
    }

    public function getUsage(): ?Usage
    {
        if (!isset($this->data['usage']) || !is_array($this->data['usage'])) {
            return null;
        }

        $usageData = $this->data['usage'];

        $promptTokens = $usageData['prompt_tokens'] ?? 0;
        $completionTokens = $usageData['completion_tokens'] ?? 0;
        $totalTokens = $usageData['total_tokens'] ?? 0;

        return new Usage(
            is_numeric($promptTokens) ? (int) $promptTokens : 0,
            is_numeric($completionTokens) ? (int) $completionTokens : 0,
            is_numeric($totalTokens) ? (int) $totalTokens : 0
        );
    }

    public function getSystemFingerprint(): ?string
    {
        $fingerprint = $this->data['system_fingerprint'] ?? null;

        return is_string($fingerprint) ? $fingerprint : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }
}
