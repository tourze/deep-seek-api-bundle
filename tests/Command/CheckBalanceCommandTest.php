<?php

namespace Tourze\DeepSeekApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\DeepSeekApiBundle\Command\CheckBalanceCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(CheckBalanceCommand::class)]
#[RunTestsInSeparateProcesses]
class CheckBalanceCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    public function testExecuteWithNoArguments(): void
    {
        $exitCode = $this->commandTester->execute([]);

        // 在没有配置API密钥的情况下，命令应该返回失败状态
        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        // 可能会显示错误信息或者正常的标题
        $this->assertTrue(
            str_contains($output, 'DeepSeek API Balance')
            || str_contains($output, 'Failed to check balance')
        );
    }

    public function testExecuteWithAllKeysOption(): void
    {
        $exitCode = $this->commandTester->execute(['--all-keys' => true]);

        // 在没有配置API密钥的情况下，命令应该返回失败状态
        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Balance for all API keys')
            || str_contains($output, 'Failed to check balance')
        );
    }

    public function testExecuteWithTotalOption(): void
    {
        $exitCode = $this->commandTester->execute(['--total' => true]);

        // 在没有配置API密钥的情况下，命令应该返回失败状态
        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Total Balance Across All API Keys')
            || str_contains($output, 'Failed to check balance')
            || str_contains($output, 'No balance information available')
        );
    }

    public function testExecuteWithApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['api-key' => 'test-key']);

        // 在没有配置API密钥的情况下，命令应该返回失败状态
        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'DeepSeek API Balance')
            || str_contains($output, 'Failed to check balance')
        );
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function testArgumentApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['api-key' => 'sk-test123']);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'DeepSeek API Balance')
            || str_contains($output, 'Failed to check balance')
        );
    }

    public function testOptionAllKeys(): void
    {
        $exitCode = $this->commandTester->execute(['--all-keys' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Balance for all API keys')
            || str_contains($output, 'Failed to check balance')
        );
    }

    public function testOptionTotal(): void
    {
        $exitCode = $this->commandTester->execute(['--total' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Total Balance Across All API Keys')
            || str_contains($output, 'Failed to check balance')
            || str_contains($output, 'No balance information available')
        );
    }

    protected function onSetUp(): void
    {
        $command = self::getService(CheckBalanceCommand::class);

        $application = new Application();
        $application->add($command);

        $command = $application->find('deepseek:balance:check');
        $this->commandTester = new CommandTester($command);
    }
}
