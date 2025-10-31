<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use Knp\Menu\ItemInterface;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekApiKeyCrudController;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekApiLogCrudController;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekBalanceCrudController;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekBalanceHistoryCrudController;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekModelCrudController;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

class AdminMenu implements MenuProviderInterface
{
    public function __invoke(ItemInterface $item): void
    {
        // Implementation for MenuProviderInterface
        // This method will be called by the easy-admin-menu system
    }

    /**
     * @return MenuItemInterface[]
     */
    public function getMenuItems(): array
    {
        return [
            MenuItem::section('DeepSeek API管理', 'fas fa-brain'),

            MenuItem::linkToCrud('API密钥管理', 'fas fa-key', DeepSeekApiKeyCrudController::class),

            MenuItem::linkToCrud('模型管理', 'fas fa-microchip', DeepSeekModelCrudController::class),

            MenuItem::section('余额与历史', 'fas fa-coins'),

            MenuItem::linkToCrud('余额快照', 'fas fa-wallet', DeepSeekBalanceCrudController::class),

            MenuItem::linkToCrud('余额历史', 'fas fa-chart-line', DeepSeekBalanceHistoryCrudController::class),

            MenuItem::section('系统监控', 'fas fa-chart-bar'),

            MenuItem::linkToCrud('API调用日志', 'fas fa-list-alt', DeepSeekApiLogCrudController::class),
        ];
    }

    /**
     * 获取用于仪表板的统计菜单项
     * @return MenuItemInterface[]
     */
    public function getDashboardItems(): array
    {
        return [
            MenuItem::linkToUrl('API密钥总览', 'fas fa-tachometer-alt', '#'),

            MenuItem::linkToUrl('余额监控', 'fas fa-chart-pie', '#'),
        ];
    }

    /**
     * 获取快捷操作菜单项
     * @return MenuItemInterface[]
     */
    public function getQuickActionItems(): array
    {
        return [
            MenuItem::linkToCrud('添加API密钥', 'fas fa-plus-circle', DeepSeekApiKeyCrudController::class)
                ->setAction('new'),

            MenuItem::linkToUrl('同步余额', 'fas fa-sync-alt', '#'),

            MenuItem::linkToUrl('同步模型', 'fas fa-download', '#'),
        ];
    }

    /**
     * 获取完整的菜单结构，包含所有分组
     * @return MenuItemInterface[]
     */
    public function getFullMenuStructure(): array
    {
        return array_merge(
            $this->getMenuItems(),
            [MenuItem::section()], // 分隔符
            $this->getQuickActionItems()
        );
    }
}
