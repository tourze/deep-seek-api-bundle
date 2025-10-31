<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;

#[When(env: 'test')]
#[When(env: 'dev')]
class DeepSeekBalanceFixtures extends Fixture implements DependentFixtureInterface
{
    public const BALANCE_1_REFERENCE = 'balance-1';
    public const BALANCE_2_REFERENCE = 'balance-2';
    public const BALANCE_3_REFERENCE = 'balance-3';

    public function load(ObjectManager $manager): void
    {
        /** @var DeepSeekApiKey $apiKey1 */
        $apiKey1 = $this->getReference(DeepSeekApiKeyFixtures::API_KEY_1_REFERENCE, DeepSeekApiKey::class);
        /** @var DeepSeekApiKey $apiKey2 */
        $apiKey2 = $this->getReference(DeepSeekApiKeyFixtures::API_KEY_2_REFERENCE, DeepSeekApiKey::class);

        // 创建第一个 API Key 的余额记录
        $balance1 = new DeepSeekBalance();
        $balance1->setApiKey($apiKey1);
        $balance1->setCurrency('USD');
        $balance1->setTotalBalance('100.50');
        $balance1->setGrantedBalance('50.00');
        $balance1->setToppedUpBalance('50.50');
        $balance1->setRecordTime(new \DateTimeImmutable('-1 hour'));
        $manager->persist($balance1);

        $balance2 = new DeepSeekBalance();
        $balance2->setApiKey($apiKey1);
        $balance2->setCurrency('CNY');
        $balance2->setTotalBalance('700.00');
        $balance2->setGrantedBalance('350.00');
        $balance2->setToppedUpBalance('350.00');
        $balance2->setRecordTime(new \DateTimeImmutable('-1 hour'));
        $manager->persist($balance2);

        // 创建第二个 API Key 的余额记录
        $balance3 = new DeepSeekBalance();
        $balance3->setApiKey($apiKey2);
        $balance3->setCurrency('USD');
        $balance3->setTotalBalance('75.25');
        $balance3->setGrantedBalance('40.00');
        $balance3->setToppedUpBalance('35.25');
        $balance3->setRecordTime(new \DateTimeImmutable('-30 minutes'));
        $manager->persist($balance3);

        // 创建历史余额记录
        $balance4 = new DeepSeekBalance();
        $balance4->setApiKey($apiKey1);
        $balance4->setCurrency('USD');
        $balance4->setTotalBalance('105.00');
        $balance4->setGrantedBalance('50.00');
        $balance4->setToppedUpBalance('55.00');
        $balance4->setRecordTime(new \DateTimeImmutable('-2 hours'));
        $manager->persist($balance4);

        // 创建低余额记录（用于测试预警）
        $balance5 = new DeepSeekBalance();
        $balance5->setApiKey($apiKey2);
        $balance5->setCurrency('CNY');
        $balance5->setTotalBalance('5.00');
        $balance5->setGrantedBalance('3.00');
        $balance5->setToppedUpBalance('2.00');
        $balance5->setRecordTime(new \DateTimeImmutable());
        $manager->persist($balance5);

        $manager->flush();

        // 设置引用供其他 fixtures 使用
        $this->addReference(self::BALANCE_1_REFERENCE, $balance1);
        $this->addReference(self::BALANCE_2_REFERENCE, $balance2);
        $this->addReference(self::BALANCE_3_REFERENCE, $balance3);
    }

    public function getDependencies(): array
    {
        return [
            DeepSeekApiKeyFixtures::class,
        ];
    }
}
