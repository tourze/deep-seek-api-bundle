<?php

namespace Tourze\DeepSeekApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\DeepSeekApiBundle\Command\ManageApiKeyCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ManageApiKeyCommand::class)]
#[RunTestsInSeparateProcesses]
class ManageApiKeyCommandTest extends AbstractCommandTestCase
{
    private ManageApiKeyCommand $command;

    private CommandTester $commandTester;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(ManageApiKeyCommand::class, $this->command);
    }

    public function testExecuteListAction(): void
    {
        $exitCode = $this->commandTester->execute(['action' => 'list']);

        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Summary') || str_contains($output, 'No API keys found')
        );
    }

    public function testExecuteAddActionWithoutApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['action' => 'add']);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('API key value is required', $output);
    }

    public function testExecuteEnableActionWithoutApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['action' => 'enable']);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('API key value is required', $output);
    }

    public function testExecuteDisableActionWithoutApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['action' => 'disable']);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('API key value is required', $output);
    }

    public function testExecuteDeleteActionWithoutApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['action' => 'delete']);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('API key value is required', $output);
    }

    public function testExecuteStatsAction(): void
    {
        $exitCode = $this->commandTester->execute(['action' => 'stats']);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Global Statistics') || str_contains($output, 'failed')
        );
    }

    public function testExecuteUnknownAction(): void
    {
        $exitCode = $this->commandTester->execute(['action' => 'unknown']);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Unknown action', $output);
    }

    public function testExecuteEnableActionWithNonexistentKey(): void
    {
        $exitCode = $this->commandTester->execute([
            'action' => 'enable',
            'api-key' => 'nonexistent-key',
        ]);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('API key not found', $output);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function testArgumentAction(): void
    {
        $exitCode = $this->commandTester->execute(['action' => 'list']);

        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Summary') || str_contains($output, 'No API keys found')
        );
    }

    public function testArgumentApiKey(): void
    {
        $exitCode = $this->commandTester->execute([
            'action' => 'add',
            'api-key' => 'sk-test123',
        ]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'added successfully') || str_contains($output, 'already exists')
        );
    }

    public function testOptionName(): void
    {
        $exitCode = $this->commandTester->execute([
            'action' => 'add',
            'api-key' => 'sk-test-with-name',
            '--name' => 'Test API Key',
        ]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Test API Key') || str_contains($output, 'already exists')
        );
    }

    public function testOptionPriority(): void
    {
        $exitCode = $this->commandTester->execute([
            'action' => 'add',
            'api-key' => 'sk-test-priority',
            '--priority' => '10',
        ]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'added successfully') || str_contains($output, 'already exists')
        );
    }

    public function testOptionShowKeys(): void
    {
        $exitCode = $this->commandTester->execute([
            'action' => 'list',
            '--show-keys' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Summary') || str_contains($output, 'No API keys found')
        );
    }

    public function testOptionResetInvalid(): void
    {
        $exitCode = $this->commandTester->execute([
            'action' => 'list',
            '--reset-invalid' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Summary') || str_contains($output, 'No API keys found')
        );
    }

    protected function onSetUp(): void
    {
        $this->command = self::getService(ManageApiKeyCommand::class);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('deep-seek:api-key');
        $this->commandTester = new CommandTester($command);
    }
}
