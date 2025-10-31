<?php

namespace Tourze\DeepSeekApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DeepSeekApiBundle\DTO\ModelInfo;

/**
 * @internal
 */
#[CoversClass(ModelInfo::class)]
class ModelInfoTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $model = new ModelInfo('deepseek-chat', 'model', 'deepseek');

        $this->assertEquals('deepseek-chat', $model->getId());
        $this->assertEquals('model', $model->getObject());
        $this->assertEquals('deepseek', $model->getOwnedBy());
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'deepseek-chat',
            'object' => 'model',
            'owned_by' => 'deepseek',
        ];

        $model = ModelInfo::fromArray($data);

        $this->assertEquals('deepseek-chat', $model->getId());
        $this->assertEquals('model', $model->getObject());
        $this->assertEquals('deepseek', $model->getOwnedBy());
    }

    public function testToArray(): void
    {
        $model = new ModelInfo('deepseek-chat', 'model', 'deepseek');

        $expected = [
            'id' => 'deepseek-chat',
            'object' => 'model',
            'owned_by' => 'deepseek',
        ];

        $this->assertEquals($expected, $model->toArray());
    }
}
