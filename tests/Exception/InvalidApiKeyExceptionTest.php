<?php

namespace Tourze\DeepSeekApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Exception\DeepSeekException;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidApiKeyException::class)]
class InvalidApiKeyExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromDeepSeekException(): void
    {
        $exception = new InvalidApiKeyException('Invalid API key');
        $this->assertInstanceOf(DeepSeekException::class, $exception);
        $this->assertSame('Invalid API key', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new InvalidApiKeyException('API key expired', 401);
        $this->assertSame(401, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Key validation failed');
        $exception = new InvalidApiKeyException('Invalid API key', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }
}
