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
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekModel;

#[AdminCrud(
    routePath: '/deep-seek-api/models',
    routeName: 'deep_seek_api_models'
)]
#[Autoconfigure(public: true)]
final class DeepSeekModelCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DeepSeekModel::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('AI模型')
            ->setEntityLabelInPlural('AI模型')
            ->setSearchFields(['modelId', 'ownedBy', 'description'])
            ->setDefaultSort(['discoverTime' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('详情');
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('apiKey', 'API密钥'))
            ->add(TextFilter::new('modelId', '模型ID'))
            ->add(TextFilter::new('ownedBy', '所属方'))
            ->add(BooleanFilter::new('isActive', '是否启用'))
            ->add(DateTimeFilter::new('discoverTime', '发现时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')->onlyOnIndex(),

            AssociationField::new('apiKey', 'API密钥')
                ->setHelp('关联的API密钥'),

            TextField::new('modelId', '模型ID')
                ->setHelp('DeepSeek模型的唯一标识符')
                ->setRequired(true),

            TextField::new('object', '对象类型')
                ->setHelp('模型对象类型，通常为model')
                ->hideOnIndex(),

            TextField::new('ownedBy', '所属方')
                ->setHelp('模型的拥有者或提供方')
                ->setRequired(true),

            BooleanField::new('isActive', '是否启用')
                ->setHelp('模型是否可用'),

            TextareaField::new('description', '描述')
                ->setHelp('模型的详细描述')
                ->hideOnIndex(),

            CodeEditorField::new('capabilities', '能力集')
                ->setLanguage('javascript')
                ->setHelp('模型的能力配置信息')
                ->hideOnIndex()
                ->hideOnForm(),

            CodeEditorField::new('pricing', '定价信息')
                ->setLanguage('javascript')
                ->setHelp('模型的定价信息')
                ->hideOnIndex()
                ->hideOnForm(),

            DateTimeField::new('discoverTime', '发现时间')
                ->setHelp('模型被发现并记录的时间')
                ->hideOnForm(),

            DateTimeField::new('updateTime', '更新时间')
                ->setHelp('模型信息最后更新的时间')
                ->hideOnForm(),
        ];
    }
}
