<?php

namespace Tourze\DeepSeekApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceRepository;

#[ORM\Entity(repositoryClass: DeepSeekBalanceRepository::class)]
#[ORM\Table(name: 'deep_seek_balances', options: ['comment' => 'DeepSeek 余额快照表'])]
class DeepSeekBalance implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeepSeekApiKey::class, inversedBy: 'balances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DeepSeekApiKey $apiKey = null;

    #[ORM\Column(type: Types::STRING, length: 10, options: ['comment' => '币种'])]
    #[Assert\Length(max: 10)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['CNY', 'USD', 'EUR', 'GBP', 'JPY'])]
    private string $currency;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, options: ['comment' => '总余额'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 25)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,4})?$/')]
    private string $totalBalance = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, options: ['comment' => '授予余额'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 25)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,4})?$/')]
    private string $grantedBalance = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, options: ['comment' => '充值余额'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 25)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,4})?$/')]
    private string $toppedUpBalance = '0.0000';

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否可用'])]
    #[Assert\NotNull]
    private bool $isAvailable = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '记录时间'])]
    #[Assert\NotNull]
    private \DateTimeImmutable $recordTime;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, nullable: true, options: ['comment' => '上一笔总余额'])]
    #[Assert\Length(max: 25)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,4})?$/')]
    private ?string $previousTotalBalance = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, nullable: true, options: ['comment' => '余额变化'])]
    #[Assert\Length(max: 25)]
    #[Assert\Regex(pattern: '/^-?\d+(\.\d{1,4})?$/')]
    private ?string $balanceChange = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '扩展数据'])]
    #[Assert\Valid]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->recordTime = new \DateTimeImmutable();
    }

    /**
     * @param array<string, mixed> $response
     * @return array<self>
     */
    public static function createFromApiResponse(array $response, DeepSeekApiKey $apiKey): array
    {
        $balances = [];

        $isAvailable = (bool) ($response['is_available'] ?? false);
        $balanceInfos = $response['balance_infos'] ?? [];

        if (!is_array($balanceInfos)) {
            return $balances;
        }

        foreach ($balanceInfos as $balanceInfo) {
            if (!is_array($balanceInfo)) {
                continue;
            }
            /** @var array<string, mixed> $balanceInfo */
            $balance = self::fromApiResponse($balanceInfo, $apiKey);
            $balance->setIsAvailable($isAvailable);
            $balances[] = $balance;
        }

        return $balances;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<self>
     */
    public static function fromArrayData(array $data, DeepSeekApiKey $apiKey): array
    {
        return self::createFromApiResponse($data, $apiKey);
    }

    /**
     * @param array<string, mixed> $balanceData
     */
    public static function fromApiResponse(array $balanceData, DeepSeekApiKey $apiKey): self
    {
        $balance = new self();
        $balance->setApiKey($apiKey);

        $currency = $balanceData['currency'] ?? 'CNY';
        $totalBalance = $balanceData['total_balance'] ?? '0.0000';
        $grantedBalance = $balanceData['granted_balance'] ?? '0.0000';
        $toppedUpBalance = $balanceData['topped_up_balance'] ?? '0.0000';

        $balance->setCurrency(is_string($currency) ? $currency : 'CNY');
        $balance->setTotalBalance(is_string($totalBalance) ? $totalBalance : '0.0000');
        $balance->setGrantedBalance(is_string($grantedBalance) ? $grantedBalance : '0.0000');
        $balance->setToppedUpBalance(is_string($toppedUpBalance) ? $toppedUpBalance : '0.0000');

        return $balance;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApiKey(): ?DeepSeekApiKey
    {
        return $this->apiKey;
    }

    public function setApiKey(?DeepSeekApiKey $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getGrantedBalance(): string
    {
        return $this->grantedBalance;
    }

    public function setGrantedBalance(string $grantedBalance): void
    {
        $this->grantedBalance = $grantedBalance;
    }

    public function getToppedUpBalance(): string
    {
        return $this->toppedUpBalance;
    }

    public function setToppedUpBalance(string $toppedUpBalance): void
    {
        $this->toppedUpBalance = $toppedUpBalance;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): void
    {
        $this->isAvailable = $isAvailable;
    }

    public function getRecordTime(): \DateTimeImmutable
    {
        return $this->recordTime;
    }

    public function setRecordTime(\DateTimeImmutable $recordTime): void
    {
        $this->recordTime = $recordTime;
    }

    public function getPreviousTotalBalance(): ?string
    {
        return $this->previousTotalBalance;
    }

    public function setPreviousTotalBalance(?string $previousTotalBalance): void
    {
        $this->previousTotalBalance = $previousTotalBalance;
    }

    public function getBalanceChange(): ?string
    {
        return $this->balanceChange;
    }

    public function setBalanceChange(?string $balanceChange): void
    {
        $this->balanceChange = $balanceChange;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getGrantedBalanceAsFloat(): float
    {
        return (float) $this->grantedBalance;
    }

    public function getToppedUpBalanceAsFloat(): float
    {
        return (float) $this->toppedUpBalance;
    }

    public function hasPositiveBalance(): bool
    {
        return $this->getTotalBalanceAsFloat() > 0;
    }

    public function isPositiveBalance(): bool
    {
        return $this->getTotalBalanceAsFloat() > 0;
    }

    public function getUsedBalance(): float
    {
        // Used balance = granted - (total - topped_up)
        $grantedBalance = $this->getGrantedBalanceAsFloat();
        $totalBalance = $this->getTotalBalanceAsFloat();
        $toppedUpBalance = $this->getToppedUpBalanceAsFloat();

        return $grantedBalance - ($totalBalance - $toppedUpBalance);
    }

    public function getTotalBalanceAsFloat(): float
    {
        return (float) $this->totalBalance;
    }

    public function calculateBalanceChange(?self $previousBalance): void
    {
        if (null !== $previousBalance && $previousBalance->getCurrency() === $this->currency) {
            $this->previousTotalBalance = $previousBalance->getTotalBalance();
            $change = $this->getTotalBalanceAsFloat() - $previousBalance->getTotalBalanceAsFloat();
            $this->balanceChange = number_format($change, 4, '.', '');
        }
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getTotalBalance(): string
    {
        return $this->totalBalance;
    }

    public function setTotalBalance(string $totalBalance): void
    {
        $this->totalBalance = $totalBalance;
    }

    public function isLowBalance(float $threshold = 10.0): bool
    {
        return $this->getTotalBalanceAsFloat() < $threshold;
    }

    public function getBalanceChangeAsFloat(): float
    {
        return (float) ($this->balanceChange ?? 0);
    }

    public function __toString(): string
    {
        return sprintf('%s: %s', $this->currency, $this->totalBalance);
    }

    public function setCalculatedChange(string $change): void
    {
        $this->balanceChange = $change;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'total_balance' => $this->totalBalance,
            'granted_balance' => $this->grantedBalance,
            'topped_up_balance' => $this->toppedUpBalance,
            'is_available' => $this->isAvailable,
            'recorded_at' => $this->recordTime->format('Y-m-d H:i:s'),
            'balance_change' => $this->balanceChange,
        ];
    }
}
