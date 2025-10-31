<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Command\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\DeepSeekApiBundle\Command\Helper\BalanceDataFormatter;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BalanceDataFormatter::class)]
#[RunTestsInSeparateProcesses]
class BalanceDataFormatterTest extends AbstractIntegrationTestCase
{
    private BalanceDataFormatter $formatter;

    private SymfonyStyle $mockIo;

    protected function onSetUp(): void
    {
        // Required by AbstractIntegrationTestCase
    }

    protected function setUpTest(): void
    {
        $this->formatter = self::getService(BalanceDataFormatter::class);
        $this->mockIo = $this->createMock(SymfonyStyle::class);
    }

    public function testDisplayBalanceTrendWithEmptyArray(): void
    {
        $this->setUpTest();
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('section');
        $this->formatter->displayBalanceTrend($this->mockIo, []);
    }

    public function testDisplayConsumptionStatisticsWithEmptyArray(): void
    {
        $this->setUpTest();
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('section');
        $this->formatter->displayConsumptionStatistics($this->mockIo, []);
    }

    public function testDisplayCurrentBalanceWithNull(): void
    {
        $this->setUpTest();
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('info');
        $this->formatter->displayCurrentBalance($this->mockIo, null);
    }

    public function testDisplayCurrentBalanceWithBalance(): void
    {
        $this->setUpTest();
        $balance = $this->createMock(DeepSeekBalance::class);
        // @phpstan-ignore-next-line
        $balance->method('getTotalBalanceAsFloat')->willReturn(100.5);
        // @phpstan-ignore-next-line
        $balance->method('getCurrency')->willReturn('CNY');

        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->once())
            ->method('info')
            ->with('Current Balance: 100.5000 CNY')
        ;

        $this->formatter->displayCurrentBalance($this->mockIo, $balance);
    }

    public function testDisplayBalanceExhaustionPredictionWithNullBalance(): void
    {
        $this->setUpTest();
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('warning');
        $this->formatter->displayBalanceExhaustionPrediction($this->mockIo, null, []);
    }

    public function testDisplayBalanceExhaustionPredictionWithEmptyConsumption(): void
    {
        $this->setUpTest();
        $balance = $this->createMock(DeepSeekBalance::class);
        // @phpstan-ignore-next-line
        $this->mockIo->expects($this->never())->method('warning');
        $this->formatter->displayBalanceExhaustionPrediction($this->mockIo, $balance, []);
    }
}
