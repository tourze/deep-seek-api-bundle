<?php

namespace Tourze\DeepSeekApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Exception\ApiRequestException;
use Tourze\DeepSeekApiBundle\Exception\DeepSeekException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ApiRequestException::class)]
class ApiRequestExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromDeepSeekException(): void
    {
        $exception = new ApiRequestException('API request failed');
        $this->assertInstanceOf(DeepSeekException::class, $exception);
        $this->assertSame('API request failed', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new ApiRequestException('Network error', 500);
        $this->assertSame(500, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Connection timeout');
        $exception = new ApiRequestException('API request failed', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }
}
