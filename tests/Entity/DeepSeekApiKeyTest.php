<?php

namespace Tourze\DeepSeekApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiLog;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekModel;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekApiKey::class)]
class DeepSeekApiKeyTest extends AbstractEntityTestCase
{
    private DeepSeekApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKey = new DeepSeekApiKey();
    }

    protected function createEntity(): DeepSeekApiKey
    {
        return new DeepSeekApiKey();
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['apiKey', 'sk-test-123456'],
            ['name', 'Test API Key'],
            ['description', 'Test description'],
            ['active', true],
            ['valid', true],
            ['priority', 10],
            ['metadata', ['env' => 'test']],
            ['lastModelsSyncTime', new \DateTimeImmutable()],
            ['lastBalanceSyncTime', new \DateTimeImmutable()],
        ];
    }

    public function testConstruct(): void
    {
        $apiKey = new DeepSeekApiKey();
        self::assertEquals('DeepSeekApiKey', (new \ReflectionClass($apiKey))->getShortName());
        self::assertTrue($apiKey->isActive());
        self::assertTrue($apiKey->isValid());
        self::assertEquals(0, $apiKey->getUsageCount());
        self::assertEquals(0, $apiKey->getErrorCount());
    }

    public function testApiKeyGetterSetter(): void
    {
        $this->apiKey->setApiKey('sk-test-123456');
        self::assertEquals('sk-test-123456', $this->apiKey->getApiKey());
        self::assertEquals('3456', $this->apiKey->getApiKeySuffix());
    }

    public function testNameGetterSetter(): void
    {
        $this->apiKey->setName('Production Key');
        self::assertEquals('Production Key', $this->apiKey->getName());
    }

    public function testDescriptionGetterSetter(): void
    {
        $this->apiKey->setDescription('Main production API key');
        self::assertEquals('Main production API key', $this->apiKey->getDescription());

        $this->apiKey->setDescription(null);
        self::assertNull($this->apiKey->getDescription());
    }

    public function testActiveStatus(): void
    {
        self::assertTrue($this->apiKey->isActive());

        $this->apiKey->setIsActive(false);
        self::assertFalse($this->apiKey->isActive());
    }

    public function testValidStatus(): void
    {
        self::assertTrue($this->apiKey->isValid());

        $this->apiKey->setIsValid(false);
        self::assertFalse($this->apiKey->isValid());
    }

    public function testCanBeUsed(): void
    {
        self::assertTrue($this->apiKey->canBeUsed());

        $this->apiKey->setIsActive(false);
        self::assertFalse($this->apiKey->canBeUsed());

        $this->apiKey->setIsActive(true);
        $this->apiKey->setIsValid(false);
        self::assertFalse($this->apiKey->canBeUsed());

        $this->apiKey->setIsValid(true);
        self::assertTrue($this->apiKey->canBeUsed());
    }

    public function testIncrementUsageCount(): void
    {
        self::assertEquals(0, $this->apiKey->getUsageCount());
        self::assertNull($this->apiKey->getLastUseTime());

        $this->apiKey->incrementUsageCount();
        self::assertEquals(1, $this->apiKey->getUsageCount());
        self::assertNotNull($this->apiKey->getLastUseTime());

        $this->apiKey->incrementUsageCount();
        self::assertEquals(2, $this->apiKey->getUsageCount());
    }

    public function testIncrementErrorCount(): void
    {
        self::assertEquals(0, $this->apiKey->getErrorCount());
        self::assertNull($this->apiKey->getLastErrorTime());
        self::assertNull($this->apiKey->getLastErrorMessage());

        $this->apiKey->incrementErrorCount('API rate limit exceeded');
        self::assertEquals(1, $this->apiKey->getErrorCount());
        self::assertNotNull($this->apiKey->getLastErrorTime());
        self::assertEquals('API rate limit exceeded', $this->apiKey->getLastErrorMessage());
    }

    public function testPriorityGetterSetter(): void
    {
        self::assertEquals(0, $this->apiKey->getPriority());

        $this->apiKey->setPriority(10);
        self::assertEquals(10, $this->apiKey->getPriority());
    }

    public function testMetadataGetterSetter(): void
    {
        self::assertNull($this->apiKey->getMetadata());

        $metadata = ['environment' => 'production', 'region' => 'us-west'];
        $this->apiKey->setMetadata($metadata);
        self::assertEquals($metadata, $this->apiKey->getMetadata());

        $this->apiKey->setMetadata(null);
        self::assertNull($this->apiKey->getMetadata());
    }

    public function testModelsSyncNeeded(): void
    {
        self::assertTrue($this->apiKey->needsModelsSync());

        $this->apiKey->setLastModelsSyncTime(new \DateTimeImmutable());
        self::assertFalse($this->apiKey->needsModelsSync());

        $oldDate = new \DateTimeImmutable('-2 days');
        $this->apiKey->setLastModelsSyncTime($oldDate);
        self::assertTrue($this->apiKey->needsModelsSync());
    }

    public function testBalanceSyncNeeded(): void
    {
        self::assertTrue($this->apiKey->needsBalanceSync());

        $this->apiKey->setLastBalanceSyncTime(new \DateTimeImmutable());
        self::assertFalse($this->apiKey->needsBalanceSync());

        $oldDate = new \DateTimeImmutable('-2 hours');
        $this->apiKey->setLastBalanceSyncTime($oldDate);
        self::assertTrue($this->apiKey->needsBalanceSync());
    }

    public function testToString(): void
    {
        $this->apiKey->setName('Test Key');
        $this->apiKey->setApiKey('sk-test-abcd1234');

        $string = (string) $this->apiKey;
        self::assertEquals('Test Key (...1234)', $string);
    }

    public function testModelRelations(): void
    {
        $model = new DeepSeekModel();

        self::assertCount(0, $this->apiKey->getModels());

        $this->apiKey->addModel($model);
        self::assertCount(1, $this->apiKey->getModels());
        self::assertTrue($this->apiKey->getModels()->contains($model));

        $this->apiKey->removeModel($model);
        self::assertCount(0, $this->apiKey->getModels());
        self::assertFalse($this->apiKey->getModels()->contains($model));
    }

    public function testBalanceRelations(): void
    {
        $balance1 = new DeepSeekBalance();
        $balance2 = new DeepSeekBalance();

        self::assertCount(0, $this->apiKey->getBalances());
        self::assertNull($this->apiKey->getLatestBalance());

        $this->apiKey->addBalance($balance1);
        $this->apiKey->addBalance($balance2);
        self::assertCount(2, $this->apiKey->getBalances());
        self::assertSame($balance1, $this->apiKey->getLatestBalance());

        $this->apiKey->removeBalance($balance1);
        self::assertCount(1, $this->apiKey->getBalances());
        self::assertSame($balance2, $this->apiKey->getLatestBalance());
    }

    public function testApiLogRelations(): void
    {
        $apiLog = new DeepSeekApiLog();

        self::assertCount(0, $this->apiKey->getApiLogs());

        $this->apiKey->addApiLog($apiLog);
        self::assertCount(1, $this->apiKey->getApiLogs());
        self::assertTrue($this->apiKey->getApiLogs()->contains($apiLog));

        $this->apiKey->removeApiLog($apiLog);
        self::assertCount(0, $this->apiKey->getApiLogs());
        self::assertFalse($this->apiKey->getApiLogs()->contains($apiLog));
    }
}
