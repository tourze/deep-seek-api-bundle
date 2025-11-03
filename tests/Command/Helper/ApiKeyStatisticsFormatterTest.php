<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Command\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\DeepSeekApiBundle\Command\Helper\ApiKeyStatisticsFormatter;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiLogRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekModelRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ApiKeyStatisticsFormatter::class)]
#[RunTestsInSeparateProcesses]
class ApiKeyStatisticsFormatterTest extends AbstractIntegrationTestCase
{
    private ApiKeyStatisticsFormatter $formatter;

    private SymfonyStyle $mockIo;

    private DeepSeekApiKeyRepository $mockApiKeyRepository;

    private DeepSeekModelRepository $mockModelRepository;

    private DeepSeekBalanceRepository $mockBalanceRepository;

    private DeepSeekApiLogRepository $mockApiLogRepository;

    protected function onSetUp(): void
    {
        $this->mockIo = $this->createMock(SymfonyStyle::class);
        $this->mockApiKeyRepository = $this->createMock(DeepSeekApiKeyRepository::class);
        $this->mockModelRepository = $this->createMock(DeepSeekModelRepository::class);
        $this->mockBalanceRepository = $this->createMock(DeepSeekBalanceRepository::class);
        $this->mockApiLogRepository = $this->createMock(DeepSeekApiLogRepository::class);

        // 将mock注入到容器中
        self::getContainer()->set(DeepSeekApiKeyRepository::class, $this->mockApiKeyRepository);
        self::getContainer()->set(DeepSeekModelRepository::class, $this->mockModelRepository);
        self::getContainer()->set(DeepSeekBalanceRepository::class, $this->mockBalanceRepository);
        self::getContainer()->set(DeepSeekApiLogRepository::class, $this->mockApiLogRepository);

        // 从容器获取服务（现在会使用我们的mock）
        $this->formatter = self::getService(ApiKeyStatisticsFormatter::class);
    }

    public function testDisplayGlobalKeyStatistics(): void
    {
        $stats = [
            'total' => 10,
            'active' => 8,
            'valid' => 9,
            'usable' => 7,
            'inactive' => 2,
            'invalid' => 1,
        ];

        // @phpstan-ignore-next-line
        $this->mockApiKeyRepository->method('getStatistics')->willReturn($stats);
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('section')->with('API Keys');
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('listing');

        $this->formatter->displayGlobalKeyStatistics($this->mockIo);
    }

    public function testDisplayBasicKeyInformation(): void
    {
        // 使用真实实例而非 Mock 来避免 final 方法配置问题
        // DeepSeekApiKey 使用 TimestampableAware trait，提供了 final 的 getCreateTime() 方法
        $apiKey = new DeepSeekApiKey();
        $apiKey->setName('Test Key');
        $apiKey->setApiKey('sk-test-xxxx'); // 满足 @Assert\NotBlank 约束
        $apiKey->setPriority(5);
        $apiKey->setIsActive(true);
        $apiKey->setIsValid(true);

        // 设置使用次数为 10（与原 Mock 行为一致）
        for ($i = 0; $i < 10; ++$i) {
            $apiKey->incrementUsageCount();
        }
        // 通过 trait 提供的 setter 设置时间戳
        $apiKey->setCreateTime(new \DateTimeImmutable());
        // lastUseTime 保持为 null 即可
        $apiKey->setUpdateTime(new \DateTimeImmutable());

        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('section')->with('Basic Information');
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('listing');

        $this->formatter->displayBasicKeyInformation($this->mockIo, $apiKey);
    }

    public function testDisplayKeyModelsWithEmptyModels(): void
    {
        $apiKey = $this->createMock(DeepSeekApiKey::class);
        // @phpstan-ignore-next-line
        $this->mockModelRepository->method('findByApiKey')->willReturn([]);

        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('section');
        $this->formatter->displayKeyModels($this->mockIo, $apiKey);
    }

    public function testDisplayKeyBalanceWithNullBalance(): void
    {
        $apiKey = $this->createMock(DeepSeekApiKey::class);
        // @phpstan-ignore-next-line
        $this->mockBalanceRepository->method('findLatestByApiKey')->willReturn(null);

        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('section');
        $this->formatter->displayKeyBalance($this->mockIo, $apiKey);
    }

    public function testDisplayKeyApiLogsWithEmptyLogs(): void
    {
        $apiKey = $this->createMock(DeepSeekApiKey::class);
        // @phpstan-ignore-next-line
        $this->mockApiLogRepository->method('findByApiKey')->willReturn([]);

        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('section');
        $this->formatter->displayKeyApiLogs($this->mockIo, $apiKey);
    }

    public function testDisplayApiKeyPerformanceWithEmptyPerformance(): void
    {
        // @phpstan-ignore-next-line
        $this->mockApiLogRepository->method('getApiKeyPerformance')->willReturn([]);

        // 当性能数据为空时，不应该显示任何内容
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('section');
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('table');

        $this->formatter->displayApiKeyPerformance($this->mockIo);

        // 验证方法正常返回，没有异常
        $this->assertTrue(true);
    }

    public function testDisplayGlobalApiStatistics(): void
    {
        $stats = [
            'total_requests' => 100,
            'success_count' => 85,
            'success_rate' => 85.0,
            'error_count' => 15,
            'error_rate' => 15.0,
            'timeout_count' => 5,
            'avg_response_time' => 1.25,
        ];

        // @phpstan-ignore-next-line
        $this->mockApiLogRepository->method('getErrorStatistics')->willReturn($stats);
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('section')->with('API Call Statistics');
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('listing');

        $this->formatter->displayGlobalApiStatistics($this->mockIo);
    }

    public function testDisplayGlobalBalanceStatistics(): void
    {
        $balanceStats = [
            'total_records' => 50,
            'average_balance_cny' => 100.5,
            'average_balance_usd' => 15.2,
            'low_balance_count' => 3,
        ];

        $totals = [
            'CNY' => 5000.0,
            'USD' => 750.0,
        ];

        // @phpstan-ignore-next-line
        $this->mockBalanceRepository->method('getBalanceStatistics')->willReturn($balanceStats);
        // @phpstan-ignore-next-line
        $this->mockBalanceRepository->method('getTotalBalanceByCurrency')->willReturn($totals);

        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->exactly(2))->method('section');
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('listing');
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('table');

        $this->formatter->displayGlobalBalanceStatistics($this->mockIo);
    }

    public function testDisplayGlobalModelStatistics(): void
    {
        $modelStats = [
            'total' => 20,
            'active' => 18,
            'chat_models' => 12,
            'reasoner_models' => 8,
        ];

        // @phpstan-ignore-next-line
        $this->mockModelRepository->method('getModelStatistics')->willReturn($modelStats);
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('section')->with('Models');
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())->method('listing');

        $this->formatter->displayGlobalModelStatistics($this->mockIo);
    }
}
