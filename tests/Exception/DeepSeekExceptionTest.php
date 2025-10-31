<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Exception\DeepSeekException;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekException::class)]
class DeepSeekExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromBaseException(): void
    {
        $exception = new TestableDeepSeekException('Test message');
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new TestableDeepSeekException('Test message', 400);
        $this->assertSame(400, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new TestableDeepSeekException('Test message', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConcreteSubclassWorks(): void
    {
        $exception = new InvalidApiKeyException('Invalid API key');
        $this->assertInstanceOf(DeepSeekException::class, $exception);
        $this->assertSame('Invalid API key', $exception->getMessage());
    }
}
