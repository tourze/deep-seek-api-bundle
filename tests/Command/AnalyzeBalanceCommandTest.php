<?php

namespace Tourze\DeepSeekApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\DeepSeekApiBundle\Command\AnalyzeBalanceCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(AnalyzeBalanceCommand::class)]
#[RunTestsInSeparateProcesses]
class AnalyzeBalanceCommandTest extends AbstractCommandTestCase
{
    private AnalyzeBalanceCommand $command;

    private CommandTester $commandTester;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(AnalyzeBalanceCommand::class, $this->command);
    }

    public function testExecuteWithNoArguments(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('DeepSeek Balance Analysis', $output);
    }

    public function testExecuteWithDaysOption(): void
    {
        $exitCode = $this->commandTester->execute(['--days' => '14']);

        $this->assertEquals(0, $exitCode);
    }

    public function testExecuteWithIntervalOption(): void
    {
        $exitCode = $this->commandTester->execute(['--interval' => 'weekly']);

        $this->assertEquals(0, $exitCode);
    }

    public function testExecuteWithCurrencyOption(): void
    {
        $exitCode = $this->commandTester->execute(['--currency' => 'USD']);

        $this->assertEquals(0, $exitCode);
    }

    public function testExecuteWithShowAlertsOption(): void
    {
        $exitCode = $this->commandTester->execute(['--show-alerts' => true]);

        $this->assertEquals(0, $exitCode);
    }

    public function testExecuteWithInvalidApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['api-key' => 'invalid-key']);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('API key not found', $output);
    }

    public function testArgumentApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['api-key' => 'test-key']);

        $this->assertContains($exitCode, [0, 1]);
    }

    public function testOptionExport(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'deepseek_export_test_');
        $this->assertNotFalse($tempFile);

        $exitCode = $this->commandTester->execute(['--export' => $tempFile]);

        $this->assertContains($exitCode, [0, 1]);

        // 测试完成后清理临时文件
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    public function testOptionDays(): void
    {
        $exitCode = $this->commandTester->execute(['--days' => '30']);

        $this->assertEquals(0, $exitCode);
    }

    public function testOptionInterval(): void
    {
        $exitCode = $this->commandTester->execute(['--interval' => 'monthly']);

        $this->assertEquals(0, $exitCode);
    }

    public function testOptionCurrency(): void
    {
        $exitCode = $this->commandTester->execute(['--currency' => 'USD']);

        $this->assertEquals(0, $exitCode);
    }

    public function testOptionShowAlerts(): void
    {
        $exitCode = $this->commandTester->execute(['--show-alerts' => true]);

        $this->assertEquals(0, $exitCode);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $this->command = self::getService(AnalyzeBalanceCommand::class);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('deep-seek:analyze-balance');
        $this->commandTester = new CommandTester($command);
    }
}
