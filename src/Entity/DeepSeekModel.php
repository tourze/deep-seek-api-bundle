<?php

namespace Tourze\DeepSeekApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekModelRepository;

#[ORM\Entity(repositoryClass: DeepSeekModelRepository::class)]
#[ORM\Table(name: 'deep_seek_models', options: ['comment' => 'DeepSeek 模型表'])]
#[ORM\UniqueConstraint(name: 'uniq_model_api_key', columns: ['model_id', 'api_key_id'])]
// 使用 AsEntityListener 替代生命周期回调，避免不必要的全局开销
class DeepSeekModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '模型ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $modelId;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '对象类型'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $object = 'model';

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '所属方'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $ownedBy;

    #[ORM\ManyToOne(targetEntity: DeepSeekApiKey::class, inversedBy: 'models')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DeepSeekApiKey $apiKey = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否可用'])]
    #[Assert\NotNull]
    #[Assert\Type(type: 'bool')]
    private bool $isActive = true;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '能力集'])]
    #[Assert\Type(type: 'array')]
    private ?array $capabilities = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '定价信息'])]
    #[Assert\Type(type: 'array')]
    private ?array $pricing = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '描述'])]
    #[Assert\Length(max: 65535)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '发现时间'])]
    #[Assert\NotNull]
    #[Assert\DateTime]
    private \DateTimeImmutable $discoverTime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '更新时间'])]
    private \DateTimeImmutable $updateTime;

    public function __construct()
    {
        $this->discoverTime = new \DateTimeImmutable();
        $this->updateTime = new \DateTimeImmutable();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data, DeepSeekApiKey $apiKey): self
    {
        $model = new self();

        $modelId = $data['id'] ?? '';
        $object = $data['object'] ?? 'model';
        $ownedBy = $data['owned_by'] ?? 'deepseek';

        $model->setModelId(is_string($modelId) ? $modelId : '');
        $model->setObject(is_string($object) ? $object : 'model');
        $model->setOwnedBy(is_string($ownedBy) ? $ownedBy : 'deepseek');
        $model->setApiKey($apiKey);

        if (isset($data['capabilities']) && is_array($data['capabilities'])) {
            /** @var array<string, mixed> $capabilities */
            $capabilities = $data['capabilities'];
            $model->setCapabilities($capabilities);
        }

        if (isset($data['pricing']) && is_array($data['pricing'])) {
            /** @var array<string, mixed> $pricing */
            $pricing = $data['pricing'];
            $model->setPricing($pricing);
        }

        return $model;
    }

    public function touchUpdateTime(): void
    {
        $this->updateTime = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }

    public function setModelId(string $modelId): void
    {
        $this->modelId = $modelId;
    }

    public function getObject(): string
    {
        return $this->object;
    }

    public function setObject(string $object): void
    {
        $this->object = $object;
    }

    public function getOwnedBy(): string
    {
        return $this->ownedBy;
    }

    public function setOwnedBy(string $ownedBy): void
    {
        $this->ownedBy = $ownedBy;
    }

    public function getApiKey(): ?DeepSeekApiKey
    {
        return $this->apiKey;
    }

    public function setApiKey(?DeepSeekApiKey $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCapabilities(): ?array
    {
        return $this->capabilities;
    }

    /**
     * @param array<string, mixed>|null $capabilities
     */
    public function setCapabilities(?array $capabilities): void
    {
        $this->capabilities = $capabilities;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPricing(): ?array
    {
        return $this->pricing;
    }

    /**
     * @param array<string, mixed>|null $pricing
     */
    public function setPricing(?array $pricing): void
    {
        $this->pricing = $pricing;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDiscoverTime(): \DateTimeImmutable
    {
        return $this->discoverTime;
    }

    public function getUpdateTime(): \DateTimeImmutable
    {
        return $this->updateTime;
    }

    public function isChat(): bool
    {
        return str_contains($this->modelId, 'chat');
    }

    public function isReasoner(): bool
    {
        return str_contains($this->modelId, 'reasoner');
    }

    public function __toString(): string
    {
        return $this->modelId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->modelId,
            'object' => $this->object,
            'owned_by' => $this->ownedBy,
            'capabilities' => $this->capabilities,
            'pricing' => $this->pricing,
            'description' => $this->description,
        ];
    }
}
