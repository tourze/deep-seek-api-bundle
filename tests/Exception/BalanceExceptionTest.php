<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Exception\BalanceException;
use Tourze\DeepSeekApiBundle\Exception\DeepSeekException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(BalanceException::class)]
final class BalanceExceptionTest extends AbstractExceptionTestCase
{
    public function testCanBeInstantiated(): void
    {
        $exception = new BalanceException('test message');

        $this->assertInstanceOf(BalanceException::class, $exception);
        $this->assertSame('test message', $exception->getMessage());
    }

    public function testInheritsFromDeepSeekException(): void
    {
        $exception = new BalanceException('test');

        $this->assertInstanceOf(DeepSeekException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
