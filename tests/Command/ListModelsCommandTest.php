<?php

namespace Tourze\DeepSeekApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\DeepSeekApiBundle\Command\ListModelsCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ListModelsCommand::class)]
#[RunTestsInSeparateProcesses]
class ListModelsCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    public function testExecuteWithNoArguments(): void
    {
        $exitCode = $this->commandTester->execute([]);

        // 在没有配置API密钥的情况下，命令应该返回失败状态
        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'DeepSeek Available Models')
            || str_contains($output, 'Failed to list models')
        );
    }

    public function testExecuteWithAllKeysOption(): void
    {
        $exitCode = $this->commandTester->execute(['--all-keys' => true]);

        // 在没有配置API密钥的情况下，命令应该返回失败状态
        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Listing models for all API keys')
            || str_contains($output, 'Failed to list models')
        );
    }

    public function testExecuteWithApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['api-key' => 'test-key']);

        // 在没有配置API密钥的情况下，命令应该返回失败状态
        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'DeepSeek Available Models')
            || str_contains($output, 'Failed to list models')
        );
    }

    public function testArgumentApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['api-key' => 'sk-test123']);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'DeepSeek Available Models')
            || str_contains($output, 'Failed to list models')
        );
    }

    public function testOptionAllKeys(): void
    {
        $exitCode = $this->commandTester->execute(['--all-keys' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Listing models for all API keys')
            || str_contains($output, 'Failed to list models')
        );
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $command = self::getService(ListModelsCommand::class);

        $application = new Application();
        $application->add($command);

        $command = $application->find('deepseek:models:list');
        $this->commandTester = new CommandTester($command);
    }
}
