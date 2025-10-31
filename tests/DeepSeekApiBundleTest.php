<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\DeepSeekApiBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekApiBundle::class)]
#[RunTestsInSeparateProcesses]
final class DeepSeekApiBundleTest extends AbstractBundleTestCase
{
}
