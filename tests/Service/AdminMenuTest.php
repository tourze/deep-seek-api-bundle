<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Service;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Service\AdminMenu;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        $this->adminMenu = self::getService(AdminMenu::class);
    }

    public function testGetMenuItemsReturnsArray(): void
    {
        $menuItems = $this->adminMenu->getMenuItems();

        $this->assertIsArray($menuItems);
        $this->assertNotEmpty($menuItems);

        foreach ($menuItems as $item) {
            $this->assertInstanceOf(MenuItemInterface::class, $item);
        }
    }

    public function testGetDashboardItemsReturnsArray(): void
    {
        $dashboardItems = $this->adminMenu->getDashboardItems();

        $this->assertIsArray($dashboardItems);

        foreach ($dashboardItems as $item) {
            $this->assertInstanceOf(MenuItemInterface::class, $item);
        }
    }

    public function testGetQuickActionItemsReturnsArray(): void
    {
        $quickActionItems = $this->adminMenu->getQuickActionItems();

        $this->assertIsArray($quickActionItems);

        foreach ($quickActionItems as $item) {
            $this->assertInstanceOf(MenuItemInterface::class, $item);
        }
    }

    public function testGetFullMenuStructureReturnsArray(): void
    {
        $fullMenuStructure = $this->adminMenu->getFullMenuStructure();

        $this->assertIsArray($fullMenuStructure);
        $this->assertNotEmpty($fullMenuStructure);

        foreach ($fullMenuStructure as $item) {
            $this->assertInstanceOf(MenuItemInterface::class, $item);
        }
    }
}
