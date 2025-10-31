<?php

namespace Tourze\DeepSeekApiBundle\DTO;

class BalanceInfo
{
    /**
     * @param CurrencyBalance[] $balanceInfos
     */
    public function __construct(
        private readonly bool $isAvailable,
        private readonly array $balanceInfos,
    ) {
    }

    /**
     * @param array{is_available: bool, balance_infos?: array<array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}>} $data
     */
    public static function fromArray(array $data): self
    {
        $balanceInfos = [];
        foreach ($data['balance_infos'] ?? [] as $balanceData) {
            $balanceInfos[] = CurrencyBalance::fromArray($balanceData);
        }

        return new self(
            isAvailable: $data['is_available'],
            balanceInfos: $balanceInfos,
        );
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * @return CurrencyBalance[]
     */
    public function getBalanceInfos(): array
    {
        return $this->balanceInfos;
    }

    public function getBalanceByCurrency(string $currency): ?CurrencyBalance
    {
        foreach ($this->balanceInfos as $balance) {
            if ($balance->getCurrency() === $currency) {
                return $balance;
            }
        }

        return null;
    }

    public function hasPositiveBalance(): bool
    {
        foreach ($this->balanceInfos as $balance) {
            if ($balance->getTotalBalanceAsFloat() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{is_available: bool, balance_infos: array<array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}>}
     */
    public function toArray(): array
    {
        $balanceInfos = [];
        foreach ($this->balanceInfos as $balance) {
            $balanceInfos[] = $balance->toArray();
        }

        return [
            'is_available' => $this->isAvailable,
            'balance_infos' => $balanceInfos,
        ];
    }
}
