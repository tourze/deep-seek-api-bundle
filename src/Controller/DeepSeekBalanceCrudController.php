<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;

#[AdminCrud(
    routePath: '/deep-seek-api/balances',
    routeName: 'deep_seek_api_balances'
)]
#[Autoconfigure(public: true)]
final class DeepSeekBalanceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DeepSeekBalance::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('余额快照')
            ->setEntityLabelInPlural('余额快照')
            ->setSearchFields(['currency', 'apiKey.name'])
            ->setDefaultSort(['recordTime' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW) // 余额通常由系统自动记录
            ->disable(Action::EDIT) // 余额通常不允许手动编辑
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('详情');
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('apiKey', 'API密钥'))
            ->add(ChoiceFilter::new('currency', '币种')
                ->setChoices([
                    '人民币' => 'CNY',
                    '美元' => 'USD',
                    '欧元' => 'EUR',
                    '英镑' => 'GBP',
                    '日元' => 'JPY',
                ]))
            ->add(BooleanFilter::new('isAvailable', '是否可用'))
            ->add(NumericFilter::new('totalBalance', '总余额'))
            ->add(DateTimeFilter::new('recordTime', '记录时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')->onlyOnIndex(),

            AssociationField::new('apiKey', 'API密钥')
                ->setHelp('关联的API密钥'),

            ChoiceField::new('currency', '币种')
                ->setChoices([
                    '人民币' => 'CNY',
                    '美元' => 'USD',
                    '欧元' => 'EUR',
                    '英镑' => 'GBP',
                    '日元' => 'JPY',
                ])
                ->renderAsBadges(),

            MoneyField::new('totalBalance', '总余额')
                ->setHelp('账户总余额')
                ->setCurrency('CNY')
                ->setStoredAsCents(false),

            MoneyField::new('grantedBalance', '授予余额')
                ->setHelp('系统授予的余额')
                ->setCurrency('CNY')
                ->setStoredAsCents(false)
                ->hideOnIndex(),

            MoneyField::new('toppedUpBalance', '充值余额')
                ->setHelp('用户充值的余额')
                ->setCurrency('CNY')
                ->setStoredAsCents(false)
                ->hideOnIndex(),

            TextField::new('balanceChange', '余额变化')
                ->setHelp('相对于上一次记录的变化量')
                ->hideOnForm(),

            TextField::new('previousTotalBalance', '上一笔总余额')
                ->setHelp('上一次记录的总余额')
                ->hideOnForm()
                ->hideOnIndex(),

            BooleanField::new('isAvailable', '是否可用')
                ->setHelp('账户是否可用'),

            DateTimeField::new('recordTime', '记录时间')
                ->setHelp('余额快照记录的时间')
                ->hideOnForm(),
        ];
    }
}
