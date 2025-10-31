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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory;

#[AdminCrud(
    routePath: '/deep-seek-api/balance-history',
    routeName: 'deep_seek_api_balance_history'
)]
#[Autoconfigure(public: true)]
final class DeepSeekBalanceHistoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DeepSeekBalanceHistory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('余额历史')
            ->setEntityLabelInPlural('余额历史')
            ->setSearchFields(['currency', 'apiKey.name', 'changeReason', 'dataSource'])
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
            ->disable(Action::NEW) // 历史记录通常由系统自动生成
            ->disable(Action::EDIT) // 历史记录通常不允许编辑
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
            ->add(ChoiceFilter::new('changeType', '变化类型')
                ->setChoices([
                    '增加' => 'increase',
                    '减少' => 'decrease',
                    '无变化' => 'no_change',
                ]))
            ->add(TextFilter::new('dataSource', '数据来源'))
            ->add(NumericFilter::new('totalBalance', '总余额'))
            ->add(NumericFilter::new('balanceChange', '余额变化'))
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
                ->setHelp('记录时的总余额')
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
                ->setHelp('相对于上一次记录的变化量'),

            ChoiceField::new('changeType', '变化类型')
                ->setChoices([
                    '增加' => 'increase',
                    '减少' => 'decrease',
                    '无变化' => 'no_change',
                ])
                ->renderAsBadges([
                    'increase' => 'success',
                    'decrease' => 'danger',
                    'no_change' => 'secondary',
                ])
                ->hideOnIndex(),

            TextareaField::new('changeReason', '变化原因')
                ->setHelp('余额变化的原因')
                ->hideOnIndex(),

            TextField::new('dataSource', '数据来源')
                ->setHelp('记录的数据来源')
                ->hideOnIndex(),

            DateTimeField::new('recordTime', '记录时间')
                ->setHelp('历史记录创建的时间')
                ->hideOnForm(),
        ];
    }
}
