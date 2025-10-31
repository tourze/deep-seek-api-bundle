<?php

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Service\OpenAiProvider;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(OpenAiProvider::class)]
#[RunTestsInSeparateProcesses]
class OpenAiProviderTest extends AbstractIntegrationTestCase
{
    private OpenAiProvider $service;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(OpenAiProvider::class, $this->service);
    }

    public function testRetrieveAuthorization(): void
    {
        $authorizations = $this->service->retrieveAuthorization();

        $this->assertIsIterable($authorizations);

        $authArray = iterator_to_array($authorizations);
        $this->assertIsArray($authArray);
    }

    protected function onSetUp(): void
    {
        $this->service = self::getService(OpenAiProvider::class);
    }

    protected function onTearDown(): void
    {
        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->close();
    }
}
