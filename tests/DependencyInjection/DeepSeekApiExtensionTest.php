<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\DependencyInjection\DeepSeekApiExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekApiExtension::class)]
final class DeepSeekApiExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    public function testExtensionCanBeInstantiated(): void
    {
        $extension = new DeepSeekApiExtension();

        $this->assertInstanceOf(DeepSeekApiExtension::class, $extension);
        $this->assertEquals('deep_seek_api', $extension->getAlias());
    }

    public function testGetConfigDir(): void
    {
        $extension = new DeepSeekApiExtension();

        $configDir = $this->getPrivateMethod($extension, 'getConfigDir');
        $result = $configDir->invoke($extension);

        $this->assertIsString($result);
        $this->assertStringEndsWith('/Resources/config', $result);
        $this->assertDirectoryExists($result);
    }

    private function getPrivateMethod(object $object, string $methodName): \ReflectionMethod
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method;
    }
}
