<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekModelCrudController;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekModel;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekModelCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DeepSeekModelCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    private function getController(): DeepSeekModelCrudController
    {
        return new DeepSeekModelCrudController();
    }

    public function testGetEntityFqcnReturnsCorrectEntityClass(): void
    {
        $this->assertEquals(DeepSeekModel::class, DeepSeekModelCrudController::getEntityFqcn());
    }

    public function testConfigureCrudSetsCorrectLabelsAndTitles(): void
    {
        $crud = $this->getController()->configureCrud(Crud::new());

        $this->assertInstanceOf(Crud::class, $crud);
    }

    public function testConfigureActionsReturnsActionsInstance(): void
    {
        $actions = $this->getController()->configureActions(Actions::new());

        $this->assertInstanceOf(Actions::class, $actions);
    }

    public function testConfigureFiltersReturnsFiltersInstance(): void
    {
        $filters = $this->getController()->configureFilters(Filters::new());

        $this->assertInstanceOf(Filters::class, $filters);
    }

    public function testConfigureFieldsReturnsIterable(): void
    {
        $fields = $this->getController()->configureFields(Crud::PAGE_INDEX);

        $this->assertIsIterable($fields);
    }

    public function testValidationErrors(): void
    {
        // 创建验证器
        $validator = self::getService(ValidatorInterface::class);

        // 创建一个空的 DeepSeekModel 实例
        $model = new DeepSeekModel();

        // 验证实体
        $violations = $validator->validate($model);

        // 检查是否有验证错误
        $this->assertCount(3, $violations);

        // 将违规转换为数组，以便按字段名查找
        $violationsByProperty = [];
        foreach ($violations as $violation) {
            $violationsByProperty[$violation->getPropertyPath()] = $violation;
        }

        // 检查 modelId 字段错误
        $this->assertArrayHasKey('modelId', $violationsByProperty);
        $this->assertStringContainsString('should not be blank', (string) $violationsByProperty['modelId']->getMessage());

        // 检查 ownedBy 字段错误
        $this->assertArrayHasKey('ownedBy', $violationsByProperty);
        $this->assertStringContainsString('should not be blank', (string) $violationsByProperty['ownedBy']->getMessage());

        // 检查 discoverTime 字段错误
        $this->assertArrayHasKey('discoverTime', $violationsByProperty);
        $this->assertStringContainsString('should be of type string', (string) $violationsByProperty['discoverTime']->getMessage());

        // object 字段不应该有验证错误（因为有默认值 'model'）
        $this->assertArrayNotHasKey('object', $violationsByProperty);
    }

    protected function getControllerService(): DeepSeekModelCrudController
    {
        return self::getService(DeepSeekModelCrudController::class);
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'API密钥' => ['API密钥'];
        yield '模型ID' => ['模型ID'];
        yield '所属方' => ['所属方'];
        yield '是否启用' => ['是否启用'];
        yield '发现时间' => ['发现时间'];
        yield '更新时间' => ['更新时间'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield 'API密钥' => ['apiKey'];
        yield '模型ID' => ['modelId'];
        yield '对象类型' => ['object'];
        yield '所属方' => ['ownedBy'];
        yield '是否启用' => ['isActive'];
        yield '描述' => ['description'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        yield 'API密钥' => ['apiKey'];
        yield '模型ID' => ['modelId'];
        yield '对象类型' => ['object'];
        yield '所属方' => ['ownedBy'];
        yield '是否启用' => ['isActive'];
        yield '描述' => ['description'];
    }
}
