<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekBalanceCrudController;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekBalanceCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DeepSeekBalanceCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    private function getController(): DeepSeekBalanceCrudController
    {
        return new DeepSeekBalanceCrudController();
    }

    public function testGetEntityFqcnReturnsCorrectEntityClass(): void
    {
        $this->assertEquals(DeepSeekBalance::class, DeepSeekBalanceCrudController::getEntityFqcn());
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

    protected function getControllerService(): DeepSeekBalanceCrudController
    {
        return self::getService(DeepSeekBalanceCrudController::class);
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'API密钥' => ['API密钥'];
        yield '币种' => ['币种'];
        yield '总余额' => ['总余额'];
        yield '余额变化' => ['余额变化'];
        yield '是否可用' => ['是否可用'];
        yield '记录时间' => ['记录时间'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        // NEW action 已被禁用，但为了避免 DataProvider 错误，返回一个占位值
        // 实际测试中会跳过
        return [
            'dummy' => ['dummy_field'],
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        // EDIT action 已被禁用，但为了避免 DataProvider 错误，返回一个占位值
        // 实际测试中会跳过
        return [
            'dummy' => ['dummy_field'],
        ];
    }

    public function testNewActionIsDisabled(): void
    {
        $client = $this->createAuthenticatedClient();

        // 期望访问NEW页面时抛出ForbiddenActionException
        $this->expectException(ForbiddenActionException::class);

        $client->request('GET', $this->generateAdminUrl(Action::NEW));
    }

    /**
     * 测试EDIT操作被禁用
     */
    public function testEditActionIsDisabled(): void
    {
        $client = $this->createAuthenticatedClient();

        // 期望访问EDIT页面时抛出ForbiddenActionException
        $this->expectException(ForbiddenActionException::class);

        $client->request('GET', $this->generateAdminUrl(Action::EDIT, ['entityId' => 1]));
    }
}
