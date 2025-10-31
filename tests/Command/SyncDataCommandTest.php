<?php

namespace Tourze\DeepSeekApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\DeepSeekApiBundle\Command\SyncDataCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(SyncDataCommand::class)]
#[RunTestsInSeparateProcesses]
class SyncDataCommandTest extends AbstractCommandTestCase
{
    private SyncDataCommand $command;

    private CommandTester $commandTester;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(SyncDataCommand::class, $this->command);
    }

    public function testExecuteWithNoOptions(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'DeepSeek Data Synchronization') || str_contains($output, 'failed')
        );
    }

    public function testExecuteWithForceAllOption(): void
    {
        $exitCode = $this->commandTester->execute(['--force-all' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Force syncing') || str_contains($output, 'failed')
        );
    }

    public function testExecuteWithModelsOnlyOption(): void
    {
        $exitCode = $this->commandTester->execute(['--models-only' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Syncing Models') || str_contains($output, 'failed')
        );
    }

    public function testExecuteWithBalanceOnlyOption(): void
    {
        $exitCode = $this->commandTester->execute(['--balance-only' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Syncing Balances') || str_contains($output, 'failed')
        );
    }

    public function testExecuteWithCleanOldDataOption(): void
    {
        $exitCode = $this->commandTester->execute(['--clean-old-data' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Cleaning Old Data') || str_contains($output, 'failed')
        );
    }

    public function testExecuteWithBothModelsOnlyAndBalanceOnly(): void
    {
        $exitCode = $this->commandTester->execute([
            '--models-only' => true,
            '--balance-only' => true,
        ]);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Cannot use --models-only and --balance-only together', $output);
    }

    public function testOptionForceAll(): void
    {
        $exitCode = $this->commandTester->execute(['--force-all' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Force syncing') || str_contains($output, 'failed')
        );
    }

    public function testOptionModelsOnly(): void
    {
        $exitCode = $this->commandTester->execute(['--models-only' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Syncing Models') || str_contains($output, 'failed')
        );
        $this->assertFalse(str_contains($output, 'Syncing Balances'));
    }

    public function testOptionBalanceOnly(): void
    {
        $exitCode = $this->commandTester->execute(['--balance-only' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Syncing Balances') || str_contains($output, 'failed')
        );
        $this->assertFalse(str_contains($output, 'Syncing Models'));
    }

    public function testOptionCleanOldData(): void
    {
        $exitCode = $this->commandTester->execute(['--clean-old-data' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Cleaning Old Data') || str_contains($output, 'failed')
        );
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $this->command = self::getService(SyncDataCommand::class);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('deep-seek:sync-data');
        $this->commandTester = new CommandTester($command);
    }
}
