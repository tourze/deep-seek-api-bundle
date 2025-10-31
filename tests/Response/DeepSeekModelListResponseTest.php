<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DeepSeekApiBundle\Response\DeepSeekModelListResponse;
use Tourze\OpenAiContracts\DTO\Model;

/**
 * @internal
 */
#[CoversClass(DeepSeekModelListResponse::class)]
class DeepSeekModelListResponseTest extends TestCase
{
    public function testFromArrayCreatesInstance(): void
    {
        $data = [
            'object' => 'list',
            'data' => [],
        ];

        $response = DeepSeekModelListResponse::fromArray($data);

        $this->assertInstanceOf(DeepSeekModelListResponse::class, $response);
    }

    public function testGetObject(): void
    {
        $data = ['object' => 'list'];
        $response = new DeepSeekModelListResponse($data);

        $this->assertEquals('list', $response->getObject());
    }

    public function testGetObjectDefault(): void
    {
        $data = [];
        $response = new DeepSeekModelListResponse($data);

        $this->assertEquals('list', $response->getObject());
    }

    public function testGetCreated(): void
    {
        $data = ['created' => 1234567890];
        $response = new DeepSeekModelListResponse($data);

        $this->assertEquals(1234567890, $response->getCreated());
    }

    public function testGetCreatedWithString(): void
    {
        $data = ['created' => '1234567890'];
        $response = new DeepSeekModelListResponse($data);

        $this->assertEquals(1234567890, $response->getCreated());
    }

    public function testGetCreatedWithNull(): void
    {
        $data = [];
        $response = new DeepSeekModelListResponse($data);

        $this->assertNull($response->getCreated());
    }

    public function testGetId(): void
    {
        $data = ['id' => 'list_123'];
        $response = new DeepSeekModelListResponse($data);

        $this->assertEquals('list_123', $response->getId());
    }

    public function testGetIdWithNull(): void
    {
        $data = [];
        $response = new DeepSeekModelListResponse($data);

        $this->assertNull($response->getId());
    }

    public function testGetDataEmpty(): void
    {
        $data = [];
        $response = new DeepSeekModelListResponse($data);

        $models = $response->getData();
        $this->assertIsArray($models);
        $this->assertEmpty($models);
    }

    public function testGetDataWithSingleModel(): void
    {
        $data = [
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'created' => 1234567890,
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);
        $models = $response->getData();

        $this->assertCount(1, $models);
        $this->assertInstanceOf(Model::class, $models[0]);

        $model = $models[0];
        $this->assertEquals('deepseek-chat', $model->getId());
        $this->assertEquals('model', $model->getObject());
        $this->assertEquals(1234567890, $model->getCreated());
        $this->assertEquals('deepseek', $model->getOwnedBy());
    }

    public function testGetDataWithMultipleModels(): void
    {
        $data = [
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'created' => 1234567890,
                    'owned_by' => 'deepseek',
                ],
                [
                    'id' => 'deepseek-coder',
                    'object' => 'model',
                    'created' => 1234567891,
                    'owned_by' => 'deepseek',
                ],
                [
                    'id' => 'deepseek-reasoner',
                    'object' => 'model',
                    'created' => 1234567892,
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);
        $models = $response->getData();

        $this->assertCount(3, $models);

        $this->assertEquals('deepseek-chat', $models[0]->getId());
        $this->assertEquals('deepseek-coder', $models[1]->getId());
        $this->assertEquals('deepseek-reasoner', $models[2]->getId());

        foreach ($models as $model) {
            $this->assertInstanceOf(Model::class, $model);
            $this->assertEquals('model', $model->getObject());
            $this->assertEquals('deepseek', $model->getOwnedBy());
        }
    }

    public function testGetDataWithIncompleteData(): void
    {
        $data = [
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    // 缺少其他字段
                ],
                [
                    'id' => 'deepseek-coder',
                    'object' => 'model',
                    'created' => 1234567890,
                    // 缺少 owned_by
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);
        $models = $response->getData();

        $this->assertCount(2, $models);

        // 第一个模型使用默认值
        $this->assertEquals('deepseek-chat', $models[0]->getId());
        $this->assertEquals('model', $models[0]->getObject());
        $this->assertEquals(0, $models[0]->getCreated());
        $this->assertEquals('', $models[0]->getOwnedBy());

        // 第二个模型
        $this->assertEquals('deepseek-coder', $models[1]->getId());
        $this->assertEquals('model', $models[1]->getObject());
        $this->assertEquals(1234567890, $models[1]->getCreated());
        $this->assertEquals('', $models[1]->getOwnedBy());
    }

    public function testHasModelTrue(): void
    {
        $data = [
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
                [
                    'id' => 'deepseek-coder',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);

        $this->assertTrue($response->hasModel('deepseek-chat'));
        $this->assertTrue($response->hasModel('deepseek-coder'));
    }

    public function testHasModelFalse(): void
    {
        $data = [
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);

        $this->assertFalse($response->hasModel('deepseek-coder'));
        $this->assertFalse($response->hasModel('deepseek-reasoner'));
        $this->assertFalse($response->hasModel('non-existent-model'));
    }

    public function testHasModelEmptyList(): void
    {
        $data = ['data' => []];
        $response = new DeepSeekModelListResponse($data);

        $this->assertFalse($response->hasModel('any-model'));
    }

    public function testToArray(): void
    {
        $data = [
            'id' => 'list_123',
            'object' => 'list',
            'created' => 1234567890,
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'created' => 1234567890,
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);
        $result = $response->toArray();

        $this->assertEquals($data, $result);
    }

    public function testJsonSerialize(): void
    {
        $data = [
            'object' => 'list',
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);
        $result = $response->jsonSerialize();

        $this->assertEquals($data, $result);
    }

    public function testJsonEncodeResponse(): void
    {
        $data = [
            'object' => 'list',
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);
        $json = json_encode($response);
        $this->assertNotFalse($json, 'JSON encoding should not fail');

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded, 'JSON decoding should not fail');
        $this->assertEquals($data, $decoded);
    }

    public function testCompleteModelListResponse(): void
    {
        $data = [
            'id' => 'list_complete_123',
            'object' => 'list',
            'created' => 1234567890,
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'created' => 1234567890,
                    'owned_by' => 'deepseek',
                ],
                [
                    'id' => 'deepseek-coder',
                    'object' => 'model',
                    'created' => 1234567891,
                    'owned_by' => 'deepseek',
                ],
                [
                    'id' => 'deepseek-reasoner',
                    'object' => 'model',
                    'created' => 1234567892,
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);

        $this->assertEquals('list_complete_123', $response->getId());
        $this->assertEquals('list', $response->getObject());
        $this->assertEquals(1234567890, $response->getCreated());

        $models = $response->getData();
        $this->assertCount(3, $models);

        $this->assertTrue($response->hasModel('deepseek-chat'));
        $this->assertTrue($response->hasModel('deepseek-coder'));
        $this->assertTrue($response->hasModel('deepseek-reasoner'));
        $this->assertFalse($response->hasModel('non-existent'));

        $this->assertEquals($data, $response->toArray());
    }

    public function testEmptyDataResponse(): void
    {
        $response = new DeepSeekModelListResponse([]);

        $this->assertNull($response->getId());
        $this->assertEquals('list', $response->getObject());
        $this->assertNull($response->getCreated());
        $this->assertEmpty($response->getData());
        $this->assertFalse($response->hasModel('any-model'));
    }

    public function testModelListWithVariousModelTypes(): void
    {
        $data = [
            'data' => [
                [
                    'id' => 'deepseek-chat',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
                [
                    'id' => 'deepseek-coder',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
                [
                    'id' => 'deepseek-reasoner',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
                [
                    'id' => 'deepseek-reasoner-v2',
                    'object' => 'model',
                    'owned_by' => 'deepseek',
                ],
            ],
        ];

        $response = new DeepSeekModelListResponse($data);
        $models = $response->getData();

        $this->assertCount(4, $models);

        $modelIds = array_map(fn ($model) => $model->getId(), $models);
        $this->assertContains('deepseek-chat', $modelIds);
        $this->assertContains('deepseek-coder', $modelIds);
        $this->assertContains('deepseek-reasoner', $modelIds);
        $this->assertContains('deepseek-reasoner-v2', $modelIds);

        // 验证模型类型检查
        $chatModels = array_filter($modelIds, fn ($id) => str_contains($id, 'chat'));
        $reasonerModels = array_filter($modelIds, fn ($id) => str_contains($id, 'reasoner'));

        $this->assertCount(1, $chatModels);
        $this->assertCount(2, $reasonerModels);
    }
}
