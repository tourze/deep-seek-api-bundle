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
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiLog;

#[AdminCrud(
    routePath: '/deep-seek-api/api-logs',
    routeName: 'deep_seek_api_api_logs'
)]
#[Autoconfigure(public: true)]
final class DeepSeekApiLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DeepSeekApiLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('API调用日志')
            ->setEntityLabelInPlural('API调用日志')
            ->setSearchFields(['endpoint', 'method', 'url', 'errorMessage', 'apiKey.name'])
            ->setDefaultSort(['requestTime' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW) // 日志通常由系统自动记录
            ->disable(Action::EDIT) // 日志通常不允许编辑
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('详情');
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('apiKey', 'API密钥'))
            ->add(ChoiceFilter::new('endpoint', 'API端点')
                ->setChoices([
                    '模型列表' => DeepSeekApiLog::ENDPOINT_LIST_MODELS,
                    '获取余额' => DeepSeekApiLog::ENDPOINT_GET_BALANCE,
                    '聊天完成' => DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION,
                ]))
            ->add(ChoiceFilter::new('method', 'HTTP方法')
                ->setChoices([
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'DELETE' => 'DELETE',
                ]))
            ->add(ChoiceFilter::new('status', '请求状态')
                ->setChoices([
                    '成功' => DeepSeekApiLog::STATUS_SUCCESS,
                    '错误' => DeepSeekApiLog::STATUS_ERROR,
                    '超时' => DeepSeekApiLog::STATUS_TIMEOUT,
                ]))
            ->add(NumericFilter::new('statusCode', 'HTTP状态码'))
            ->add(NumericFilter::new('responseTime', '响应时间(秒)'))
            ->add(DateTimeFilter::new('requestTime', '请求时间'))
            ->add(TextFilter::new('errorCode', '错误代码'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')->onlyOnIndex(),

            AssociationField::new('apiKey', 'API密钥')
                ->setHelp('发起请求的API密钥'),

            ChoiceField::new('endpoint', 'API端点')
                ->setChoices([
                    '模型列表' => DeepSeekApiLog::ENDPOINT_LIST_MODELS,
                    '获取余额' => DeepSeekApiLog::ENDPOINT_GET_BALANCE,
                    '聊天完成' => DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION,
                ])
                ->renderAsBadges([
                    DeepSeekApiLog::ENDPOINT_LIST_MODELS => 'info',
                    DeepSeekApiLog::ENDPOINT_GET_BALANCE => 'warning',
                    DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION => 'primary',
                ]),

            ChoiceField::new('method', 'HTTP方法')
                ->setChoices([
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'DELETE' => 'DELETE',
                ])
                ->renderAsBadges([
                    'GET' => 'info',
                    'POST' => 'success',
                    'PUT' => 'warning',
                    'DELETE' => 'danger',
                ]),

            ChoiceField::new('status', '请求状态')
                ->setChoices([
                    '成功' => DeepSeekApiLog::STATUS_SUCCESS,
                    '错误' => DeepSeekApiLog::STATUS_ERROR,
                    '超时' => DeepSeekApiLog::STATUS_TIMEOUT,
                ])
                ->renderAsBadges([
                    DeepSeekApiLog::STATUS_SUCCESS => 'success',
                    DeepSeekApiLog::STATUS_ERROR => 'danger',
                    DeepSeekApiLog::STATUS_TIMEOUT => 'warning',
                ]),

            IntegerField::new('statusCode', 'HTTP状态码')
                ->setHelp('HTTP响应状态码')
                ->hideOnIndex(),

            NumberField::new('responseTime', '响应时间')
                ->setHelp('请求响应时间（秒）')
                ->setNumDecimals(3),

            TextField::new('url', '请求URL')
                ->setHelp('API请求的完整URL')
                ->hideOnIndex(),

            TextField::new('errorCode', '错误代码')
                ->setHelp('API返回的错误代码')
                ->hideOnForm(),

            TextareaField::new('errorMessage', '错误信息')
                ->setHelp('详细的错误信息')
                ->hideOnIndex()
                ->hideOnForm(),

            TextField::new('ipAddress', 'IP地址')
                ->setHelp('客户端IP地址')
                ->hideOnIndex()
                ->hideOnForm(),

            TextField::new('userAgent', '用户代理')
                ->setHelp('客户端用户代理字符串')
                ->hideOnIndex()
                ->hideOnForm(),

            DateTimeField::new('requestTime', '请求时间')
                ->setHelp('API请求发起的时间')
                ->hideOnForm(),

            DateTimeField::new('respondTime', '响应时间')
                ->setHelp('API响应返回的时间')
                ->hideOnForm()
                ->hideOnIndex(),

            // 只在详情页显示JSON数据
            CodeEditorField::new('requestHeaders', '请求头')
                ->setLanguage('javascript')
                ->setHelp('HTTP请求头信息')
                ->onlyOnDetail(),

            CodeEditorField::new('requestBody', '请求体')
                ->setLanguage('javascript')
                ->setHelp('HTTP请求体内容')
                ->onlyOnDetail(),

            CodeEditorField::new('responseHeaders', '响应头')
                ->setLanguage('javascript')
                ->setHelp('HTTP响应头信息')
                ->onlyOnDetail(),

            CodeEditorField::new('responseBody', '响应体')
                ->setLanguage('javascript')
                ->setHelp('API响应内容')
                ->onlyOnDetail(),
        ];
    }
}
