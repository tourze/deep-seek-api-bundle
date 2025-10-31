<?php

namespace Tourze\DeepSeekApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceHistoryRepository;

#[ORM\Entity(repositoryClass: DeepSeekBalanceHistoryRepository::class)]
#[ORM\Table(name: 'deep_seek_balance_history', options: ['comment' => 'DeepSeek 余额历史表'])]
#[ORM\Index(name: 'deep_seek_balance_history_idx_balance_history_api_key_currency', columns: ['api_key_id', 'currency'])]
class DeepSeekBalanceHistory implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeepSeekApiKey::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DeepSeekApiKey $apiKey = null;

    #[ORM\Column(type: Types::STRING, length: 10, options: ['comment' => '币种'])]
    #[Assert\Length(max: 10)]
    private string $currency = 'CNY';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, options: ['comment' => '总余额'])]
    #[Assert\Length(max: 25)]
    #[Assert\NotBlank]
    private string $totalBalance = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, options: ['comment' => '授予余额'])]
    #[Assert\Length(max: 25)]
    #[Assert\NotBlank]
    private string $grantedBalance = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, options: ['comment' => '充值余额'])]
    #[Assert\Length(max: 25)]
    #[Assert\NotBlank]
    private string $toppedUpBalance = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, nullable: true, options: ['comment' => '余额变化'])]
    #[Assert\Length(max: 25)]
    private ?string $balanceChange = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['comment' => '变化类型'])]
    #[Assert\Length(max: 20)]
    private ?string $changeType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '变化原因'])]
    #[Assert\Length(max: 65535)]
    private ?string $changeReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '记录时间'])]
    #[Assert\NotNull]
    #[Assert\DateTime]
    private \DateTimeImmutable $recordTime;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '数据来源'])]
    #[Assert\Length(max: 50)]
    private ?string $dataSource = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '扩展数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->recordTime = new \DateTimeImmutable();
    }

    /**
     * 从API响应创建历史记录
     */
    public static function createFromBalance(DeepSeekBalance $balance): self
    {
        $history = new self();
        $history->setApiKey($balance->getApiKey());
        $history->setCurrency($balance->getCurrency());
        $history->setTotalBalance($balance->getTotalBalance());
        $history->setGrantedBalance($balance->getGrantedBalance());
        $history->setToppedUpBalance($balance->getToppedUpBalance());
        $history->setDataSource('api_sync');

        return $history;
    }

    public function getApiKey(): ?DeepSeekApiKey
    {
        return $this->apiKey;
    }

    public function setApiKey(?DeepSeekApiKey $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getTotalBalance(): string
    {
        return $this->totalBalance;
    }

    public function setTotalBalance(string $totalBalance): void
    {
        $this->totalBalance = $totalBalance;
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBalanceChange(): ?string
    {
        return $this->balanceChange;
    }

    public function setBalanceChange(?string $balanceChange): void
    {
        $this->balanceChange = $balanceChange;
    }

    public function getChangeType(): ?string
    {
        return $this->changeType;
    }

    public function setChangeType(?string $changeType): void
    {
        $this->changeType = $changeType;
    }

    public function getChangeReason(): ?string
    {
        return $this->changeReason;
    }

    public function setChangeReason(?string $changeReason): void
    {
        $this->changeReason = $changeReason;
    }

    public function getRecordTime(): \DateTimeImmutable
    {
        return $this->recordTime;
    }

    public function setRecordTime(\DateTimeImmutable $recordTime): void
    {
        $this->recordTime = $recordTime;
    }

    public function getDataSource(): ?string
    {
        return $this->dataSource;
    }

    public function setDataSource(?string $dataSource): void
    {
        $this->dataSource = $dataSource;
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

    public function getBalanceChangeAsFloat(): float
    {
        return (float) ($this->balanceChange ?? 0);
    }

    public function __toString(): string
    {
        return sprintf('%s %.4f @%s', $this->currency, (float) $this->totalBalance, $this->recordTime->format('Y-m-d H:i:s'));
    }

    /**
     * 计算与上一条记录的余额变化
     */
    public function calculateChange(?self $previousRecord): void
    {
        if (null !== $previousRecord && $previousRecord->getCurrency() === $this->currency) {
            $change = $this->getTotalBalanceAsFloat() - $previousRecord->getTotalBalanceAsFloat();
            $this->balanceChange = number_format($change, 4, '.', '');

            if ($change > 0) {
                $this->changeType = 'increase';
            } elseif ($change < 0) {
                $this->changeType = 'decrease';
            } else {
                $this->changeType = 'no_change';
            }
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

    public function getTotalBalanceAsFloat(): float
    {
        return (float) $this->totalBalance;
    }

    /**
     * 获取时间段分析数据
     */
    /**
     * @return array<string, mixed>
     */
    public function getPeriodAnalysis(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $period = $startDate->diff($endDate);

        return [
            'currency' => $this->currency,
            'start_balance' => $this->totalBalance,
            'end_balance' => $this->totalBalance,
            'change' => $this->balanceChange,
            'change_type' => $this->changeType,
            'period_days' => $period->days,
            'daily_average' => null !== $this->balanceChange ?
                number_format((float) $this->balanceChange / max($period->days, 1), 4, '.', '') : '0.0000',
        ];
    }
}
