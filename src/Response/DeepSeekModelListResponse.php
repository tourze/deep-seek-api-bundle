<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Response;

use Tourze\OpenAiContracts\DTO\Model;
use Tourze\OpenAiContracts\Response\ModelListResponseInterface;

class DeepSeekModelListResponse implements ModelListResponseInterface, \JsonSerializable
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

    public function getObject(): string
    {
        $object = $this->data['object'] ?? 'list';

        return is_string($object) ? $object : 'list';
    }

    public function getCreated(): ?int
    {
        $created = $this->data['created'] ?? null;

        return null !== $created && is_numeric($created) ? (int) $created : null;
    }

    public function hasModel(string $modelId): bool
    {
        foreach ($this->getData() as $model) {
            if ($model->getId() === $modelId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Model[]
     */
    public function getData(): array
    {
        $models = [];
        $dataArray = $this->data['data'] ?? [];
        if (!is_array($dataArray)) {
            return [];
        }

        foreach ($dataArray as $modelData) {
            if (!is_array($modelData)) {
                continue;
            }
            $created = $modelData['created'] ?? 0;

            $models[] = new Model(
                is_string($modelData['id'] ?? null) ? $modelData['id'] : '',
                is_string($modelData['object'] ?? null) ? $modelData['object'] : 'model',
                is_numeric($created) ? (int) $created : 0,
                is_string($modelData['owned_by'] ?? null) ? $modelData['owned_by'] : ''
            );
        }

        return $models;
    }

    public function getId(): ?string
    {
        $id = $this->data['id'] ?? null;

        return is_string($id) ? $id : null;
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
