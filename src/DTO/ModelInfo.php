<?php

namespace Tourze\DeepSeekApiBundle\DTO;

class ModelInfo
{
    public function __construct(
        private readonly string $id,
        private readonly string $object,
        private readonly string $ownedBy,
    ) {
    }

    /**
     * @param array{id: string, object: string, owned_by: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            object: $data['object'],
            ownedBy: $data['owned_by'],
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getObject(): string
    {
        return $this->object;
    }

    public function getOwnedBy(): string
    {
        return $this->ownedBy;
    }

    /**
     * @return array{id: string, object: string, owned_by: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'owned_by' => $this->ownedBy,
        ];
    }
}
