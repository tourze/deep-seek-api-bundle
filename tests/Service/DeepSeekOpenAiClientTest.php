<?php

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Service\DeepSeekOpenAiClient;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekOpenAiClient::class)]
#[RunTestsInSeparateProcesses]
class DeepSeekOpenAiClientTest extends AbstractIntegrationTestCase
{
    private DeepSeekOpenAiClient $service;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(DeepSeekOpenAiClient::class, $this->service);
    }

    public function testGetName(): void
    {
        $name = $this->service->getName();

        $this->assertEquals('DeepSeek', $name);
    }

    public function testSetName(): void
    {
        $this->service->setName('Test DeepSeek');
        $name = $this->service->getName();

        $this->assertEquals('Test DeepSeek', $name);
    }

    public function testGetBaseUrl(): void
    {
        $baseUrl = $this->service->getBaseUrl();

        $this->assertEquals('https://api.deepseek.com', $baseUrl);
    }

    public function testSetBaseUrl(): void
    {
        $this->service->setBaseUrl('https://test.deepseek.com/');
        $baseUrl = $this->service->getBaseUrl();

        $this->assertEquals('https://test.deepseek.com', $baseUrl);
    }

    public function testSetBaseUrlTrimsTrailingSlash(): void
    {
        $this->service->setBaseUrl('https://test.deepseek.com/v1/');
        $baseUrl = $this->service->getBaseUrl();

        $this->assertEquals('https://test.deepseek.com/v1', $baseUrl);
    }

    protected function onSetUp(): void
    {
        $this->service = self::getService(DeepSeekOpenAiClient::class);
    }

    protected function onTearDown(): void
    {
        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->close();
    }
}
