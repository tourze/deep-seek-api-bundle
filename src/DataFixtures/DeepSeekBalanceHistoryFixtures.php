<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory;

#[When(env: 'test')]
#[When(env: 'dev')]
class DeepSeekBalanceHistoryFixtures extends Fixture implements DependentFixtureInterface
{
    public const HISTORY_1_REFERENCE = 'history-1';
    public const HISTORY_2_REFERENCE = 'history-2';
    public const HISTORY_3_REFERENCE = 'history-3';
    public const HISTORY_4_REFERENCE = 'history-4';

    public function load(ObjectManager $manager): void
    {
        $apiKey1 = $this->getReference(DeepSeekApiKeyFixtures::API_KEY_1_REFERENCE, DeepSeekApiKey::class);
        $apiKey2 = $this->getReference(DeepSeekApiKeyFixtures::API_KEY_2_REFERENCE, DeepSeekApiKey::class);

        $history1 = new DeepSeekBalanceHistory();
        $history1->setApiKey($apiKey1);
        $history1->setCurrency('CNY');
        $history1->setTotalBalance('100.0000');
        $history1->setGrantedBalance('100.0000');
        $history1->setToppedUpBalance('0.0000');
        $history1->setBalanceChange('+100.0000');
        $history1->setChangeType('grant');
        $history1->setChangeReason('初始额度');
        $history1->setRecordTime(new \DateTimeImmutable('-10 days'));

        $manager->persist($history1);
        $this->addReference(self::HISTORY_1_REFERENCE, $history1);

        $history2 = new DeepSeekBalanceHistory();
        $history2->setApiKey($apiKey1);
        $history2->setCurrency('CNY');
        $history2->setTotalBalance('85.5000');
        $history2->setGrantedBalance('100.0000');
        $history2->setToppedUpBalance('0.0000');
        $history2->setBalanceChange('-14.5000');
        $history2->setChangeType('consumption');
        $history2->setChangeReason('API调用消费');
        $history2->setRecordTime(new \DateTimeImmutable('-5 days'));

        $manager->persist($history2);
        $this->addReference(self::HISTORY_2_REFERENCE, $history2);

        $history3 = new DeepSeekBalanceHistory();
        $history3->setApiKey($apiKey2);
        $history3->setCurrency('CNY');
        $history3->setTotalBalance('200.0000');
        $history3->setGrantedBalance('150.0000');
        $history3->setToppedUpBalance('50.0000');
        $history3->setBalanceChange('+50.0000');
        $history3->setChangeType('topup');
        $history3->setChangeReason('账户充值');
        $history3->setRecordTime(new \DateTimeImmutable('-3 days'));

        $manager->persist($history3);
        $this->addReference(self::HISTORY_3_REFERENCE, $history3);

        $history4 = new DeepSeekBalanceHistory();
        $history4->setApiKey($apiKey2);
        $history4->setCurrency('CNY');
        $history4->setTotalBalance('180.2500');
        $history4->setGrantedBalance('150.0000');
        $history4->setToppedUpBalance('50.0000');
        $history4->setBalanceChange('-19.7500');
        $history4->setChangeType('consumption');
        $history4->setChangeReason('批量API调用');
        $history4->setRecordTime(new \DateTimeImmutable('-1 day'));

        $manager->persist($history4);
        $this->addReference(self::HISTORY_4_REFERENCE, $history4);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DeepSeekApiKeyFixtures::class,
        ];
    }
}
