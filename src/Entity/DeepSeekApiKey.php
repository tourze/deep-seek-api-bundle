<?php

namespace Tourze\DeepSeekApiBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: DeepSeekApiKeyRepository::class)]
#[ORM\Table(name: 'deep_seek_api_keys', options: ['comment' => 'DeepSeek API密钥管理表'])]
#[ORM\Index(name: 'deep_seek_api_keys_idx_api_key_status', columns: ['is_active', 'is_valid'])]
class DeepSeekApiKey implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, options: ['comment' => 'API密钥'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $apiKey;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '密钥名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '密钥描述'])]
    #[Assert\Length(max: 65535)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\Type(type: 'bool')]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否有效'])]
    #[Assert\Type(type: 'bool')]
    private bool $isValid = true;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '使用次数'])]
    #[Assert\PositiveOrZero]
    private int $usageCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '错误次数'])]
    #[Assert\PositiveOrZero]
    private int $errorCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后使用时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $lastUseTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后错误时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $lastErrorTime = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '最后错误信息'])]
    #[Assert\Length(max: 500)]
    private ?string $lastErrorMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后模型同步时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $lastModelsSyncTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后余额同步时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $lastBalanceSyncTime = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '优先级'])]
    #[Assert\Type(type: 'int')]
    private int $priority = 0;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $metadata = null;

    /**
     * @var Collection<int, DeepSeekModel>
     */
    #[ORM\OneToMany(targetEntity: DeepSeekModel::class, mappedBy: 'apiKey', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $models;

    /**
     * @var Collection<int, DeepSeekBalance>
     */
    #[ORM\OneToMany(targetEntity: DeepSeekBalance::class, mappedBy: 'apiKey', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(value: ['recordTime' => 'DESC'])]
    private Collection $balances;

    /**
     * @var Collection<int, DeepSeekApiLog>
     */
    #[ORM\OneToMany(targetEntity: DeepSeekApiLog::class, mappedBy: 'apiKey', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(value: ['requestTime' => 'DESC'])]
    private Collection $apiLogs;

    public function __construct()
    {
        $this->models = new ArrayCollection();
        $this->balances = new ArrayCollection();
        $this->apiLogs = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s (...%s)', $this->name, $this->getApiKeySuffix());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setActive(bool $active): void
    {
        $this->setIsActive($active);
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function setIsValid(bool $isValid): void
    {
        $this->isValid = $isValid;
    }

    public function setValid(bool $valid): void
    {
        $this->setIsValid($valid);
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function incrementUsageCount(): self
    {
        ++$this->usageCount;
        $this->lastUseTime = new \DateTimeImmutable();

        return $this;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function incrementErrorCount(string $errorMessage): self
    {
        ++$this->errorCount;
        $this->lastErrorTime = new \DateTimeImmutable();
        $this->lastErrorMessage = $errorMessage;

        return $this;
    }

    public function getLastUseTime(): ?\DateTimeImmutable
    {
        return $this->lastUseTime;
    }

    public function setLastUseTime(?\DateTimeImmutable $lastUseTime): void
    {
        $this->lastUseTime = $lastUseTime;
    }

    public function getLastErrorTime(): ?\DateTimeImmutable
    {
        return $this->lastErrorTime;
    }

    public function setLastErrorTime(?\DateTimeImmutable $lastErrorTime): void
    {
        $this->lastErrorTime = $lastErrorTime;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function setLastErrorMessage(?string $lastErrorMessage): void
    {
        $this->lastErrorMessage = $lastErrorMessage;
    }

    public function getLastModelsSyncTime(): ?\DateTimeImmutable
    {
        return $this->lastModelsSyncTime;
    }

    public function setLastModelsSyncTime(\DateTimeImmutable $lastModelsSyncTime): void
    {
        $this->lastModelsSyncTime = $lastModelsSyncTime;
    }

    public function getLastBalanceSyncTime(): ?\DateTimeImmutable
    {
        return $this->lastBalanceSyncTime;
    }

    public function setLastBalanceSyncTime(\DateTimeImmutable $lastBalanceSyncTime): void
    {
        $this->lastBalanceSyncTime = $lastBalanceSyncTime;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
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

    /**
     * @return Collection<int, DeepSeekModel>
     */
    public function getModels(): Collection
    {
        return $this->models;
    }

    public function addModel(DeepSeekModel $model): self
    {
        if (!$this->models->contains($model)) {
            $this->models->add($model);
            $model->setApiKey($this);
        }

        return $this;
    }

    public function removeModel(DeepSeekModel $model): self
    {
        if ($this->models->removeElement($model)) {
            if ($model->getApiKey() === $this) {
                $model->setApiKey(null);
            }
        }

        return $this;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return Collection<int, DeepSeekBalance>
     */
    public function getBalances(): Collection
    {
        return $this->balances;
    }

    public function getLatestBalance(): ?DeepSeekBalance
    {
        $first = $this->balances->first();

        return false !== $first ? $first : null;
    }

    public function addBalance(DeepSeekBalance $balance): self
    {
        if (!$this->balances->contains($balance)) {
            $this->balances->add($balance);
            $balance->setApiKey($this);
        }

        return $this;
    }

    public function removeBalance(DeepSeekBalance $balance): self
    {
        if ($this->balances->removeElement($balance)) {
            if ($balance->getApiKey() === $this) {
                $balance->setApiKey(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DeepSeekApiLog>
     */
    public function getApiLogs(): Collection
    {
        return $this->apiLogs;
    }

    public function addApiLog(DeepSeekApiLog $apiLog): self
    {
        if (!$this->apiLogs->contains($apiLog)) {
            $this->apiLogs->add($apiLog);
            $apiLog->setApiKey($this);
        }

        return $this;
    }

    public function removeApiLog(DeepSeekApiLog $apiLog): self
    {
        if ($this->apiLogs->removeElement($apiLog)) {
            if ($apiLog->getApiKey() === $this) {
                $apiLog->setApiKey(null);
            }
        }

        return $this;
    }

    public function getApiKeySuffix(): string
    {
        return substr($this->apiKey, -4);
    }

    public function canBeUsed(): bool
    {
        return $this->isActive && $this->isValid;
    }

    public function needsModelsSync(): bool
    {
        if (null === $this->lastModelsSyncTime) {
            return true;
        }

        $now = new \DateTimeImmutable();
        $diffSeconds = $now->getTimestamp() - $this->lastModelsSyncTime->getTimestamp();

        return $diffSeconds >= 24 * 60 * 60;
    }

    public function needsBalanceSync(): bool
    {
        if (null === $this->lastBalanceSyncTime) {
            return true;
        }

        $now = new \DateTimeImmutable();
        $diffSeconds = $now->getTimestamp() - $this->lastBalanceSyncTime->getTimestamp();

        return $diffSeconds >= 60 * 60;
    }
}
