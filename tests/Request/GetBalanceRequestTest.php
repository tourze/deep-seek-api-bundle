<?php

namespace Tourze\DeepSeekApiBundle\Tests\Request;

use HttpClientBundle\Tests\Request\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Request\GetBalanceRequest;

/**
 * @internal
 */
#[CoversClass(GetBalanceRequest::class)]
class GetBalanceRequestTest extends RequestTestCase
{
    public function testGetRequestPath(): void
    {
        $request = new GetBalanceRequest();
        $this->assertEquals('/user/balance', $request->getRequestPath());
    }

    public function testGetRequestOptions(): void
    {
        $request = new GetBalanceRequest();
        $this->assertNull($request->getRequestOptions());
    }

    public function testGetRequestMethod(): void
    {
        $request = new GetBalanceRequest();
        $this->assertEquals('GET', $request->getRequestMethod());
    }
}
