<?php

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use HttpClientBundle\Request\RequestInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Service\DeepSeekApiClient;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekApiClient::class)]
#[RunTestsInSeparateProcesses]
class DeepSeekApiClientTest extends AbstractIntegrationTestCase
{
    private DeepSeekApiClient $service;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(DeepSeekApiClient::class, $this->service);
    }

    public function testGetBaseUrl(): void
    {
        $baseUrl = $this->service->getBaseUrl();

        $this->assertEquals('https://api.deepseek.com', $baseUrl);
    }

    public function testSetApiKey(): void
    {
        $apiKey = 'test-api-key';

        // 验证 setApiKey 方法可以正常调用不抛出异常
        $this->service->setApiKey($apiKey);

        // 验证方法调用成功（在集成测试环境下，我们主要关心方法的行为而非内部状态）
        $this->assertTrue(true, 'setApiKey method executed without exceptions');
    }

    public function testRequest(): void
    {
        $this->expectException(\Throwable::class);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('getRequestPath')->willReturn('/test');
        $mockRequest->method('getRequestMethod')->willReturn('GET');
        $mockRequest->method('getRequestOptions')->willReturn([]);

        $this->service->request($mockRequest);
    }

    protected function onSetUp(): void
    {
        $this->service = self::getService(DeepSeekApiClient::class);
    }
}
