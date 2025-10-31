<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Response;

use Tourze\OpenAiContracts\DTO\Balance;
use Tourze\OpenAiContracts\Response\BalanceResponseInterface;

class DeepSeekBalanceResponse implements BalanceResponseInterface, \JsonSerializable
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
        $object = $this->data['object'] ?? 'balance';

        return is_string($object) ? $object : 'balance';
    }

    public function getCreated(): ?int
    {
        $created = $this->data['created'] ?? null;

        return null !== $created && is_numeric($created) ? (int) $created : null;
    }

    public function getBalance(): Balance
    {
        $totalBalanceRaw = $this->data['total_balance'] ?? $this->data['total_granted'] ?? 0.0;
        $totalBalance = is_numeric($totalBalanceRaw) ? (float) $totalBalanceRaw : 0.0;

        $usedBalanceRaw = $this->data['used_balance'] ?? $this->data['total_used'] ?? 0.0;
        $usedBalance = is_numeric($usedBalanceRaw) ? (float) $usedBalanceRaw : 0.0;

        $remainingBalanceRaw = $this->data['remaining_balance'] ?? $this->data['total_available'] ?? ($totalBalance - $usedBalance);
        $remainingBalance = is_numeric($remainingBalanceRaw) ? (float) $remainingBalanceRaw : 0.0;

        $currency = $this->data['currency'] ?? 'USD';

        return new Balance(
            $totalBalance,
            $usedBalance,
            $remainingBalance,
            is_string($currency) ? $currency : 'USD'
        );
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
