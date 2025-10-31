<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiLog;

#[When(env: 'test')]
#[When(env: 'dev')]
class DeepSeekApiLogFixtures extends Fixture implements DependentFixtureInterface
{
    public const API_LOG_1_REFERENCE = 'api-log-1';
    public const API_LOG_2_REFERENCE = 'api-log-2';
    public const API_LOG_3_REFERENCE = 'api-log-3';
    public const API_LOG_4_REFERENCE = 'api-log-4';
    public const API_LOG_5_REFERENCE = 'api-log-5';

    public function load(ObjectManager $manager): void
    {
        /** @var DeepSeekApiKey $apiKey1 */
        $apiKey1 = $this->getReference(DeepSeekApiKeyFixtures::API_KEY_1_REFERENCE, DeepSeekApiKey::class);
        /** @var DeepSeekApiKey $apiKey2 */
        $apiKey2 = $this->getReference(DeepSeekApiKeyFixtures::API_KEY_2_REFERENCE, DeepSeekApiKey::class);

        // 创建成功的请求日志
        $log1 = DeepSeekApiLog::createForRequest(
            $apiKey1,
            DeepSeekApiLog::ENDPOINT_LIST_MODELS,
            'GET',
            'https://api.deepseek.com/v1/models'
        );
        $log1->markAsSuccess(200, ['data' => []]);
        $manager->persist($log1);

        // 创建余额查询日志
        $log2 = DeepSeekApiLog::createForRequest(
            $apiKey1,
            DeepSeekApiLog::ENDPOINT_GET_BALANCE,
            'GET',
            'https://api.deepseek.com/v1/balance'
        );
        $log2->markAsSuccess(200, ['balance' => ['USD' => 100.0]]);
        $manager->persist($log2);

        // 创建聊天完成请求日志
        $log3 = DeepSeekApiLog::createForRequest(
            $apiKey2,
            DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION,
            'POST',
            'https://api.deepseek.com/v1/chat/completions',
            [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
            ]
        );
        $log3->markAsSuccess(200, [
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Hi there!']],
            ],
        ]);
        $manager->persist($log3);

        // 创建失败的请求日志
        $log4 = DeepSeekApiLog::createForRequest(
            $apiKey2,
            DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION,
            'POST',
            'https://api.deepseek.com/v1/chat/completions',
            ['model' => 'deepseek-chat']
        );
        $log4->markAsError('Rate limit exceeded', 'rate_limit_error', 429);
        $manager->persist($log4);

        // 创建超时的请求日志
        $log5 = DeepSeekApiLog::createForRequest(
            $apiKey1,
            DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION,
            'POST',
            'https://api.deepseek.com/v1/chat/completions',
            ['model' => 'deepseek-chat']
        );
        $log5->markAsTimeout();
        $manager->persist($log5);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DeepSeekApiKeyFixtures::class,
        ];
    }
}
