<?php

namespace Tourze\DeepSeekApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\DeepSeekApiBundle\Command\ValidateApiKeyCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ValidateApiKeyCommand::class)]
#[RunTestsInSeparateProcesses]
class ValidateApiKeyCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    public function testExecuteWithApiKey(): void
    {
        $exitCode = $this->commandTester->execute(['api-key' => 'test-key']);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Validating API key', $output);
    }

    public function testExecuteWithAllKeysOption(): void
    {
        $exitCode = $this->commandTester->execute(['--all-keys' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Validating all configured API keys', $output);
    }

    public function testExecuteWithoutRequiredArgument(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Please provide an API key or use --all-keys option', $output);
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
        $this->assertStringContainsString('Validating API key', $output);
        $this->assertTrue(
            str_contains($output, 'ending with t123 is valid')
            || str_contains($output, 'ending with t123 is invalid')
            || str_contains($output, 'Validation failed')
        );
    }

    public function testOptionAllKeys(): void
    {
        $exitCode = $this->commandTester->execute(['--all-keys' => true]);

        $this->assertContains($exitCode, [0, 1]);
        $output = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, 'Validating all configured API keys')
            || str_contains($output, 'No API keys configured')
            || str_contains($output, 'API keys are valid')
            || str_contains($output, 'Validation failed')
        );
    }

    protected function onSetUp(): void
    {
        $command = self::getService(ValidateApiKeyCommand::class);

        $application = new Application();
        $application->add($command);

        $command = $application->find('deepseek:api-key:validate');
        $this->commandTester = new CommandTester($command);
    }
}
