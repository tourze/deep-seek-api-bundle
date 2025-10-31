<?php

namespace Tourze\DeepSeekApiBundle\DTO;

class CurrencyBalance
{
    public function __construct(
        private readonly string $currency,
        private readonly string $totalBalance,
        private readonly string $grantedBalance,
        private readonly string $toppedUpBalance,
    ) {
    }

    /**
     * @param array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'],
            totalBalance: $data['total_balance'],
            grantedBalance: $data['granted_balance'],
            toppedUpBalance: $data['topped_up_balance'],
        );
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getTotalBalance(): string
    {
        return $this->totalBalance;
    }

    public function getGrantedBalance(): string
    {
        return $this->grantedBalance;
    }

    public function getToppedUpBalance(): string
    {
        return $this->toppedUpBalance;
    }

    public function getTotalBalanceAsFloat(): float
    {
        return (float) $this->totalBalance;
    }

    public function getGrantedBalanceAsFloat(): float
    {
        return (float) $this->grantedBalance;
    }

    public function getToppedUpBalanceAsFloat(): float
    {
        return (float) $this->toppedUpBalance;
    }

    /**
     * @return array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'total_balance' => $this->totalBalance,
            'granted_balance' => $this->grantedBalance,
            'topped_up_balance' => $this->toppedUpBalance,
        ];
    }
}
