<?php

namespace Tourze\DeepSeekApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekModel;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekModel::class)]
class DeepSeekModelTest extends AbstractEntityTestCase
{
    private DeepSeekModel $model;

    private DeepSeekApiKey $apiKey;

    protected function createEntity(): object
    {
        return new DeepSeekModel();
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['modelId', 'deepseek-chat'],
            ['object', 'model'],
            ['ownedBy', 'deepseek'],
            ['capabilities', ['chat', 'completion']],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $this->model = new DeepSeekModel();
        $this->apiKey = new DeepSeekApiKey();
        $this->apiKey->setApiKey('test-key');
        $this->apiKey->setName('Test Key');
    }

    public function testConstruct(): void
    {
        $model = new DeepSeekModel();
        $this->assertInstanceOf(\DateTimeImmutable::class, $model->getDiscoverTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $model->getUpdateTime());
        $this->assertTrue($model->isActive());
    }

    public function testModelIdGetterSetter(): void
    {
        $this->model->setModelId('deepseek-chat');
        $this->assertSame('deepseek-chat', $this->model->getModelId());
    }

    public function testObjectGetterSetter(): void
    {
        $this->model->setObject('model');
        $this->assertSame('model', $this->model->getObject());
    }

    public function testOwnedByGetterSetter(): void
    {
        $this->model->setOwnedBy('deepseek');
        $this->assertSame('deepseek', $this->model->getOwnedBy());
    }

    public function testApiKeyRelation(): void
    {
        $this->assertNull($this->model->getApiKey());

        $this->model->setApiKey($this->apiKey);
        $this->assertSame($this->apiKey, $this->model->getApiKey());
    }

    public function testIsActiveGetterSetter(): void
    {
        $this->assertTrue($this->model->isActive());

        $this->model->setIsActive(false);
        $this->assertFalse($this->model->isActive());

        $this->model->setIsActive(true);
        $this->assertTrue($this->model->isActive());
    }

    public function testCapabilitiesGetterSetter(): void
    {
        $capabilities = ['chat' => true, 'completion' => true];
        $this->model->setCapabilities($capabilities);
        $this->assertSame($capabilities, $this->model->getCapabilities());

        $this->model->setCapabilities(null);
        $this->assertNull($this->model->getCapabilities());
    }

    public function testPricingGetterSetter(): void
    {
        $pricing = ['prompt' => 0.002, 'completion' => 0.006];
        $this->model->setPricing($pricing);
        $this->assertSame($pricing, $this->model->getPricing());

        $this->model->setPricing(null);
        $this->assertNull($this->model->getPricing());
    }

    public function testDescriptionGetterSetter(): void
    {
        $this->model->setDescription('Test model description');
        $this->assertSame('Test model description', $this->model->getDescription());

        $this->model->setDescription(null);
        $this->assertNull($this->model->getDescription());
    }

    public function testTouchUpdatedAt(): void
    {
        $originalUpdatedAt = $this->model->getUpdateTime();

        // 等待一小段时间确保时间戳不同
        usleep(1000);

        $this->model->touchUpdateTime();
        $this->assertGreaterThan($originalUpdatedAt, $this->model->getUpdateTime());
    }

    public function testFromApiResponse(): void
    {
        $data = [
            'id' => 'deepseek-chat',
            'object' => 'model',
            'owned_by' => 'deepseek',
            'capabilities' => ['chat', 'completion'],
            'pricing' => ['prompt' => 0.002, 'completion' => 0.006],
        ];

        $model = DeepSeekModel::fromApiResponse($data, $this->apiKey);

        $this->assertSame('deepseek-chat', $model->getModelId());
        $this->assertSame('model', $model->getObject());
        $this->assertSame('deepseek', $model->getOwnedBy());
        $this->assertSame($this->apiKey, $model->getApiKey());
        $this->assertSame(['chat', 'completion'], $model->getCapabilities());
        $this->assertSame(['prompt' => 0.002, 'completion' => 0.006], $model->getPricing());
    }

    public function testFromApiResponseWithMinimalData(): void
    {
        $data = [
            'id' => 'deepseek-chat',
        ];

        $model = DeepSeekModel::fromApiResponse($data, $this->apiKey);

        $this->assertSame('deepseek-chat', $model->getModelId());
        $this->assertSame('model', $model->getObject());
        $this->assertSame('deepseek', $model->getOwnedBy());
        $this->assertSame($this->apiKey, $model->getApiKey());
        $this->assertNull($model->getCapabilities());
        $this->assertNull($model->getPricing());
    }
}
