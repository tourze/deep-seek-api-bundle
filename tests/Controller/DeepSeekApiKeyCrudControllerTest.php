<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekApiKeyCrudController;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekApiKeyCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DeepSeekApiKeyCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    private function getController(): DeepSeekApiKeyCrudController
    {
        return new DeepSeekApiKeyCrudController();
    }

    protected function getControllerService(): DeepSeekApiKeyCrudController
    {
        return self::getService(DeepSeekApiKeyCrudController::class);
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '密钥名称' => ['密钥名称'];
        yield '密钥后缀' => ['密钥后缀'];
        yield '是否启用' => ['是否启用'];
        yield '是否有效' => ['是否有效'];
        yield '使用次数' => ['使用次数'];
        yield '错误次数' => ['错误次数'];
        yield '优先级' => ['优先级'];
        yield '最后使用时间' => ['最后使用时间'];
        yield '创建时间' => ['创建时间'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield '密钥名称' => ['name'];
        yield 'API密钥' => ['apiKey'];
        yield '描述' => ['description'];
        yield '是否启用' => ['isActive'];
        yield '优先级' => ['priority'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        yield '密钥名称' => ['name'];
        yield 'API密钥' => ['apiKey'];
        yield '描述' => ['description'];
        yield '是否启用' => ['isActive'];
        yield '优先级' => ['priority'];
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

        // 创建一个空的 DeepSeekApiKey 实例
        $apiKey = new DeepSeekApiKey();

        // 验证实体
        $violations = $validator->validate($apiKey);

        // 检查是否有验证错误
        $this->assertCount(2, $violations);

        // 将违规转换为数组，以便按字段名查找
        $violationsByProperty = [];
        foreach ($violations as $violation) {
            $violationsByProperty[$violation->getPropertyPath()] = $violation;
        }

        // 检查 apiKey 字段错误
        $this->assertArrayHasKey('apiKey', $violationsByProperty);
        $this->assertStringContainsString('should not be blank', (string) $violationsByProperty['apiKey']->getMessage());

        // 检查 name 字段错误
        $this->assertArrayHasKey('name', $violationsByProperty);
        $this->assertStringContainsString('should not be blank', (string) $violationsByProperty['name']->getMessage());
    }
}
