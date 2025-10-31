<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekModel;

#[When(env: 'test')]
#[When(env: 'dev')]
class DeepSeekModelFixtures extends Fixture implements DependentFixtureInterface
{
    public const MODEL_1_REFERENCE = 'model-1';
    public const MODEL_2_REFERENCE = 'model-2';
    public const MODEL_3_REFERENCE = 'model-3';
    public const MODEL_4_REFERENCE = 'model-4';

    public function load(ObjectManager $manager): void
    {
        $apiKey1 = $this->getReference(DeepSeekApiKeyFixtures::API_KEY_1_REFERENCE, DeepSeekApiKey::class);
        $apiKey2 = $this->getReference(DeepSeekApiKeyFixtures::API_KEY_2_REFERENCE, DeepSeekApiKey::class);

        $model1 = new DeepSeekModel();
        $model1->setModelId('deepseek-chat');
        $model1->setObject('model');
        $model1->setOwnedBy('deepseek');
        $model1->setApiKey($apiKey1);
        $model1->setIsActive(true);
        $model1->setCapabilities(['chat' => true, 'completion' => true]);
        $model1->setPricing([
            'input' => ['price' => 0.001, 'unit' => '1K tokens'],
            'output' => ['price' => 0.002, 'unit' => '1K tokens'],
        ]);
        $model1->setDescription('DeepSeek聊天模型');

        $manager->persist($model1);
        $this->addReference(self::MODEL_1_REFERENCE, $model1);

        $model2 = new DeepSeekModel();
        $model2->setModelId('deepseek-coder');
        $model2->setObject('model');
        $model2->setOwnedBy('deepseek');
        $model2->setApiKey($apiKey1);
        $model2->setIsActive(true);
        $model2->setCapabilities(['code' => true, 'completion' => true]);
        $model2->setPricing([
            'input' => ['price' => 0.0015, 'unit' => '1K tokens'],
            'output' => ['price' => 0.003, 'unit' => '1K tokens'],
        ]);
        $model2->setDescription('DeepSeek代码模型');

        $manager->persist($model2);
        $this->addReference(self::MODEL_2_REFERENCE, $model2);

        $model3 = new DeepSeekModel();
        $model3->setModelId('deepseek-reasoner');
        $model3->setObject('model');
        $model3->setOwnedBy('deepseek');
        $model3->setApiKey($apiKey2);
        $model3->setIsActive(true);
        $model3->setCapabilities(['reasoning' => true, 'analysis' => true]);
        $model3->setPricing([
            'input' => ['price' => 0.002, 'unit' => '1K tokens'],
            'output' => ['price' => 0.004, 'unit' => '1K tokens'],
        ]);
        $model3->setDescription('DeepSeek推理模型');

        $manager->persist($model3);
        $this->addReference(self::MODEL_3_REFERENCE, $model3);

        $model4 = new DeepSeekModel();
        $model4->setModelId('deepseek-math');
        $model4->setObject('model');
        $model4->setOwnedBy('deepseek');
        $model4->setApiKey($apiKey2);
        $model4->setIsActive(false);
        $model4->setCapabilities(['math' => true, 'reasoning' => true]);
        $model4->setPricing([
            'input' => ['price' => 0.002, 'unit' => '1K tokens'],
            'output' => ['price' => 0.004, 'unit' => '1K tokens'],
        ]);
        $model4->setDescription('DeepSeek数学推理模型');

        $manager->persist($model4);
        $this->addReference(self::MODEL_4_REFERENCE, $model4);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DeepSeekApiKeyFixtures::class,
        ];
    }
}
