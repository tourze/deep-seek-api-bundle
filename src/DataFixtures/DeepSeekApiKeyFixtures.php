<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;

#[When(env: 'test')]
#[When(env: 'dev')]
class DeepSeekApiKeyFixtures extends Fixture
{
    public const API_KEY_1_REFERENCE = 'api-key-1';
    public const API_KEY_2_REFERENCE = 'api-key-2';

    public function load(ObjectManager $manager): void
    {
        $apiKey1 = new DeepSeekApiKey();
        $apiKey1->setName('测试API密钥1');
        $apiKey1->setApiKey('sk-test-' . bin2hex(random_bytes(16)));
        $apiKey1->setDescription('用于测试环境的DeepSeek API密钥1');
        $apiKey1->setIsActive(true);
        $apiKey1->setIsValid(true);
        $apiKey1->setPriority(1);
        $apiKey1->setMetadata([
            'environment' => 'test',
            'created_by' => 'fixtures',
        ]);

        $manager->persist($apiKey1);
        $this->addReference(self::API_KEY_1_REFERENCE, $apiKey1);

        $apiKey2 = new DeepSeekApiKey();
        $apiKey2->setName('测试API密钥2');
        $apiKey2->setApiKey('sk-test-' . bin2hex(random_bytes(16)));
        $apiKey2->setDescription('用于测试环境的DeepSeek API密钥2');
        $apiKey2->setIsActive(true);
        $apiKey2->setIsValid(true);
        $apiKey2->setPriority(2);
        $apiKey2->setMetadata([
            'environment' => 'test',
            'created_by' => 'fixtures',
        ]);

        $manager->persist($apiKey2);
        $this->addReference(self::API_KEY_2_REFERENCE, $apiKey2);

        $manager->flush();
    }
}
