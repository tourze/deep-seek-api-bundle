<?php

namespace Tourze\DeepSeekApiBundle\Tests\Request;

use HttpClientBundle\Tests\Request\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Request\ListModelsRequest;

/**
 * @internal
 */
#[CoversClass(ListModelsRequest::class)]
class ListModelsRequestTest extends RequestTestCase
{
    public function testGetRequestPath(): void
    {
        $request = new ListModelsRequest();
        $this->assertEquals('/models', $request->getRequestPath());
    }

    public function testGetRequestOptions(): void
    {
        $request = new ListModelsRequest();
        $this->assertNull($request->getRequestOptions());
    }

    public function testGetRequestMethod(): void
    {
        $request = new ListModelsRequest();
        $this->assertEquals('GET', $request->getRequestMethod());
    }
}
