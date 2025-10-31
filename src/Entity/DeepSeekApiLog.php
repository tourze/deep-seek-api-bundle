<?php

namespace Tourze\DeepSeekApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiLogRepository;

#[ORM\Entity(repositoryClass: DeepSeekApiLogRepository::class)]
#[ORM\Table(name: 'deep_seek_api_logs', options: ['comment' => 'DeepSeek API 调用日志表'])]
class DeepSeekApiLog implements \Stringable
{
    public const ENDPOINT_LIST_MODELS = 'list_models';
    public const ENDPOINT_GET_BALANCE = 'get_balance';
    public const ENDPOINT_CHAT_COMPLETION = 'chat_completion';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_TIMEOUT = 'timeout';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeepSeekApiKey::class, inversedBy: 'apiLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DeepSeekApiKey $apiKey = null;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => 'API端点'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Assert\Choice(choices: [
        self::ENDPOINT_LIST_MODELS,
        self::ENDPOINT_GET_BALANCE,
        self::ENDPOINT_CHAT_COMPLETION,
    ])]
    private string $endpoint;

    #[ORM\Column(type: Types::STRING, length: 10, options: ['comment' => 'HTTP请求方法'])]
    #[Assert\Length(max: 10)]
    private string $method = 'GET';

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '请求URL'])]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private string $url;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '请求头'])]
    #[Assert\Type(type: 'array')]
    private ?array $requestHeaders = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '请求体'])]
    #[Assert\Type(type: 'array')]
    private ?array $requestBody = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => 'HTTP状态码'])]
    #[Assert\Range(min: 100, max: 599)]
    private ?int $statusCode = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '请求状态'])]
    #[Assert\Length(max: 20)]
    private string $status = self::STATUS_SUCCESS;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '响应头'])]
    #[Assert\Type(type: 'array')]
    private ?array $responseHeaders = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '响应体'])]
    #[Assert\Type(type: 'array')]
    private ?array $responseBody = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '错误信息'])]
    #[Assert\Length(max: 65535)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '错误代码'])]
    #[Assert\Length(max: 100)]
    private ?string $errorCode = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['comment' => '响应时间(秒)'])]
    #[Assert\PositiveOrZero]
    private ?float $responseTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '请求时间'])]
    #[Assert\NotNull]
    private \DateTimeImmutable $requestTime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '响应时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $respondTime = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '客户端IP地址'])]
    #[Assert\Length(max: 45)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '用户代理'])]
    #[Assert\Length(max: 500)]
    private ?string $userAgent = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '附加元数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->requestTime = new \DateTimeImmutable();
    }

    /**
     * @param array<string, mixed>|null $requestBody
     */
    public static function createForRequest(
        DeepSeekApiKey $apiKey,
        string $endpoint,
        string $method,
        string $url,
        ?array $requestBody = null,
    ): self {
        $log = new self();
        $log->setApiKey($apiKey);
        $log->setEndpoint($endpoint);
        $log->setMethod($method);
        $log->setUrl($url);
        $log->setRequestBody($requestBody);

        return $log;
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

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRequestHeaders(): ?array
    {
        return $this->requestHeaders;
    }

    /**
     * @param array<string, mixed>|null $requestHeaders
     */
    public function setRequestHeaders(?array $requestHeaders): void
    {
        $this->requestHeaders = $requestHeaders;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRequestBody(): ?array
    {
        return $this->requestBody;
    }

    /**
     * @param array<string, mixed>|null $requestBody
     */
    public function setRequestBody(?array $requestBody): void
    {
        $this->requestBody = $requestBody;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(?int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseHeaders(): ?array
    {
        return $this->responseHeaders;
    }

    /**
     * @param array<string, mixed>|null $responseHeaders
     */
    public function setResponseHeaders(?array $responseHeaders): void
    {
        $this->responseHeaders = $responseHeaders;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function setResponseBody(?array $responseBody): void
    {
        $this->responseBody = $responseBody;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function getResponseTime(): ?float
    {
        return $this->responseTime;
    }

    public function setResponseTime(?float $responseTime): void
    {
        $this->responseTime = $responseTime;
    }

    public function getRequestTime(): \DateTimeImmutable
    {
        return $this->requestTime;
    }

    public function getRespondTime(): ?\DateTimeImmutable
    {
        return $this->respondTime;
    }

    public function setRespondTime(?\DateTimeImmutable $respondTime): void
    {
        $this->respondTime = $respondTime;

        if (null !== $respondTime) {
            $this->responseTime = (float) $respondTime->format('U.u') - (float) $this->requestTime->format('U.u');
        }
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
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

    public function isSuccess(): bool
    {
        return self::STATUS_SUCCESS === $this->status;
    }

    public function isError(): bool
    {
        return self::STATUS_ERROR === $this->status;
    }

    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function markAsSuccess(int $statusCode, ?array $responseBody = null): self
    {
        $this->status = self::STATUS_SUCCESS;
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->respondTime = new \DateTimeImmutable();
        $this->responseTime = (float) $this->respondTime->format('U.u') - (float) $this->requestTime->format('U.u');

        return $this;
    }

    public function markAsError(string $errorMessage, ?string $errorCode = null, ?int $statusCode = null): self
    {
        $this->status = self::STATUS_ERROR;
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->respondTime = new \DateTimeImmutable();
        $this->responseTime = (float) $this->respondTime->format('U.u') - (float) $this->requestTime->format('U.u');

        return $this;
    }

    public function markAsTimeout(): self
    {
        $this->status = self::STATUS_TIMEOUT;
        $this->errorMessage = 'Request timeout';
        $this->respondTime = new \DateTimeImmutable();
        $this->responseTime = (float) $this->respondTime->format('U.u') - (float) $this->requestTime->format('U.u');

        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'DeepSeekApiLog #%d: %s %s [%s]',
            $this->id ?? 0,
            $this->method,
            $this->endpoint,
            $this->status
        );
    }

    public function completeWithSuccess(int $statusCode, float $responseTime): void
    {
        $this->statusCode = $statusCode;
        $this->status = self::STATUS_SUCCESS;
        $this->responseTime = $responseTime;
        $this->respondTime = new \DateTimeImmutable();
    }

    public function completeWithError(string $errorMessage, float $responseTime): void
    {
        $this->status = self::STATUS_ERROR;
        $this->errorMessage = $errorMessage;
        $this->responseTime = $responseTime;
        $this->respondTime = new \DateTimeImmutable();
    }
}
