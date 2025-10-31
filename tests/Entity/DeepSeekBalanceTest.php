<?php

namespace Tourze\DeepSeekApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekBalance::class)]
class DeepSeekBalanceTest extends AbstractEntityTestCase
{
    private DeepSeekBalance $balance;

    private DeepSeekApiKey $apiKey;

    protected function createEntity(): object
    {
        return new DeepSeekBalance();
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['currency', 'USD'],
            ['totalBalance', '100.50'],
            ['grantedBalance', '50.25'],
            ['toppedUpBalance', '50.25'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $this->balance = new DeepSeekBalance();
        $this->apiKey = new DeepSeekApiKey();
        $this->apiKey->setApiKey('test-key');
        $this->apiKey->setName('Test Key');
    }

    public function testConstruct(): void
    {
        $balance = new DeepSeekBalance();
        $this->assertInstanceOf(\DateTimeImmutable::class, $balance->getRecordTime());
    }

    public function testApiKeyRelation(): void
    {
        $this->assertNull($this->balance->getApiKey());

        $this->balance->setApiKey($this->apiKey);
        $this->assertSame($this->apiKey, $this->balance->getApiKey());

        $this->balance->setApiKey(null);
        $this->assertNull($this->balance->getApiKey());
    }

    public function testCurrencyGetterSetter(): void
    {
        $this->balance->setCurrency('USD');
        $this->assertSame('USD', $this->balance->getCurrency());

        $this->balance->setCurrency('CNY');
        $this->assertSame('CNY', $this->balance->getCurrency());
    }

    public function testBalanceGettersSetters(): void
    {
        $this->balance->setTotalBalance('100.50');
        $this->assertSame('100.50', $this->balance->getTotalBalance());
        $this->assertSame(100.50, $this->balance->getTotalBalanceAsFloat());

        $this->balance->setGrantedBalance('50.25');
        $this->assertSame('50.25', $this->balance->getGrantedBalance());
        $this->assertSame(50.25, $this->balance->getGrantedBalanceAsFloat());

        $this->balance->setToppedUpBalance('50.25');
        $this->assertSame('50.25', $this->balance->getToppedUpBalance());
        $this->assertSame(50.25, $this->balance->getToppedUpBalanceAsFloat());
    }

    public function testRecordedAtGetterSetter(): void
    {
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');
        $this->balance->setRecordTime($date);
        $this->assertSame($date, $this->balance->getRecordTime());
    }

    public function testIsPositiveBalance(): void
    {
        $this->balance->setTotalBalance('0');
        $this->assertFalse($this->balance->isPositiveBalance());

        $this->balance->setTotalBalance('0.01');
        $this->assertTrue($this->balance->isPositiveBalance());

        $this->balance->setTotalBalance('-10');
        $this->assertFalse($this->balance->isPositiveBalance());
    }

    public function testGetUsedBalance(): void
    {
        $this->balance->setTotalBalance('100');
        $this->balance->setGrantedBalance('60');
        $this->balance->setToppedUpBalance('40');

        // Used balance = granted - (total - topped_up)
        // = 60 - (100 - 40) = 60 - 60 = 0
        $this->assertSame(0.0, $this->balance->getUsedBalance());

        $this->balance->setTotalBalance('80');
        // Used balance = 60 - (80 - 40) = 60 - 40 = 20
        $this->assertSame(20.0, $this->balance->getUsedBalance());
    }

    public function testToString(): void
    {
        $this->balance->setCurrency('USD');
        $this->balance->setTotalBalance('123.45');

        $this->assertSame('USD: 123.45', (string) $this->balance);
    }

    public function testFromArrayData(): void
    {
        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('test-key');
        $apiKey->setName('Test');

        $data = [
            'is_available' => true,
            'balance_infos' => [
                [
                    'currency' => 'USD',
                    'total_balance' => '100.50',
                    'granted_balance' => '60.25',
                    'topped_up_balance' => '40.25',
                ],
                [
                    'currency' => 'CNY',
                    'total_balance' => '700.00',
                    'granted_balance' => '400.00',
                    'topped_up_balance' => '300.00',
                ],
            ],
        ];

        $balances = DeepSeekBalance::fromArrayData($data, $apiKey);

        $this->assertCount(2, $balances);

        $this->assertSame('USD', $balances[0]->getCurrency());
        $this->assertSame('100.50', $balances[0]->getTotalBalance());
        $this->assertSame('60.25', $balances[0]->getGrantedBalance());
        $this->assertSame('40.25', $balances[0]->getToppedUpBalance());
        $this->assertSame($apiKey, $balances[0]->getApiKey());

        $this->assertSame('CNY', $balances[1]->getCurrency());
        $this->assertSame('700.00', $balances[1]->getTotalBalance());
        $this->assertSame('400.00', $balances[1]->getGrantedBalance());
        $this->assertSame('300.00', $balances[1]->getToppedUpBalance());
        $this->assertSame($apiKey, $balances[1]->getApiKey());
    }

    public function testFromArrayDataHandlesEmptyBalanceInfos(): void
    {
        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('test-key');
        $apiKey->setName('Test');

        $data = [
            'is_available' => false,
            'balance_infos' => [],
        ];

        $balances = DeepSeekBalance::fromArrayData($data, $apiKey);

        $this->assertCount(0, $balances);
    }
}
