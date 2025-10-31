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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;

#[AdminCrud(
    routePath: '/deep-seek-api/api-keys',
    routeName: 'deep_seek_api_api_keys'
)]
#[Autoconfigure(public: true)]
final class DeepSeekApiKeyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DeepSeekApiKey::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('API密钥')
            ->setEntityLabelInPlural('API密钥')
            ->setSearchFields(['name', 'description'])
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', '密钥名称'))
            ->add(BooleanFilter::new('isActive', '是否启用'))
            ->add(BooleanFilter::new('isValid', '是否有效'))
            ->add(NumericFilter::new('usageCount', '使用次数'))
            ->add(NumericFilter::new('errorCount', '错误次数'))
            ->add(DateTimeFilter::new('lastUseTime', '最后使用时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id', 'ID')->onlyOnIndex(),

            TextField::new('name', '密钥名称')
                ->setHelp('为API密钥设置一个易于识别的名称')
                ->setRequired(true),

            TextField::new('apiKey', 'API密钥')
                ->setHelp('DeepSeek API密钥')
                ->hideOnIndex()
                ->setRequired(true),

            TextField::new('apiKeySuffix', '密钥后缀')
                ->onlyOnIndex()
                ->setHelp('显示API密钥的后4位'),

            TextareaField::new('description', '描述')
                ->setHelp('可选的描述信息')
                ->hideOnIndex(),

            BooleanField::new('isActive', '是否启用')
                ->setHelp('控制此API密钥是否可用'),

            BooleanField::new('isValid', '是否有效')
                ->setHelp('标识API密钥是否有效')
                ->hideOnForm(),

            IntegerField::new('usageCount', '使用次数')
                ->setHelp('API密钥被使用的总次数')
                ->hideOnForm(),

            IntegerField::new('errorCount', '错误次数')
                ->setHelp('API密钥使用过程中出现的错误次数')
                ->hideOnForm(),

            IntegerField::new('priority', '优先级')
                ->setHelp('数值越大优先级越高，用于控制使用顺序'),

            DateTimeField::new('lastUseTime', '最后使用时间')
                ->hideOnForm(),

            DateTimeField::new('lastErrorTime', '最后错误时间')
                ->hideOnForm()
                ->hideOnIndex(),

            TextField::new('lastErrorMessage', '最后错误信息')
                ->hideOnForm()
                ->hideOnIndex(),

            DateTimeField::new('lastModelsSyncTime', '最后模型同步时间')
                ->hideOnForm()
                ->hideOnIndex(),

            DateTimeField::new('lastBalanceSyncTime', '最后余额同步时间')
                ->hideOnForm()
                ->hideOnIndex(),

            DateTimeField::new('createTime', '创建时间')
                ->hideOnForm(),

            DateTimeField::new('updateTime', '更新时间')
                ->hideOnForm()
                ->hideOnIndex(),
        ];

        // 在详情页显示关联的数据
        if (Crud::PAGE_DETAIL === $pageName) {
            $fields[] = AssociationField::new('models', '关联模型')
                ->setHelp('与此API密钥关联的模型列表')
            ;

            $fields[] = AssociationField::new('balances', '余额记录')
                ->setHelp('与此API密钥关联的余额记录')
            ;

            $fields[] = AssociationField::new('apiLogs', 'API调用日志')
                ->setHelp('与此API密钥关联的调用日志')
            ;
        }

        return $fields;
    }
}
