<?php

namespace Tourze\DeepSeekApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DeepSeekApiBundle\DTO\CurrencyBalance;

/**
 * @internal
 */
#[CoversClass(CurrencyBalance::class)]
class CurrencyBalanceTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $balance = new CurrencyBalance('CNY', '110.00', '10.00', '100.00');

        $this->assertEquals('CNY', $balance->getCurrency());
        $this->assertEquals('110.00', $balance->getTotalBalance());
        $this->assertEquals('10.00', $balance->getGrantedBalance());
        $this->assertEquals('100.00', $balance->getToppedUpBalance());
    }

    public function testFloatGetters(): void
    {
        $balance = new CurrencyBalance('USD', '25.50', '5.25', '20.25');

        $this->assertEquals(25.50, $balance->getTotalBalanceAsFloat());
        $this->assertEquals(5.25, $balance->getGrantedBalanceAsFloat());
        $this->assertEquals(20.25, $balance->getToppedUpBalanceAsFloat());
    }

    public function testFromArray(): void
    {
        $data = [
            'currency' => 'CNY',
            'total_balance' => '110.00',
            'granted_balance' => '10.00',
            'topped_up_balance' => '100.00',
        ];

        $balance = CurrencyBalance::fromArray($data);

        $this->assertEquals('CNY', $balance->getCurrency());
        $this->assertEquals('110.00', $balance->getTotalBalance());
        $this->assertEquals('10.00', $balance->getGrantedBalance());
        $this->assertEquals('100.00', $balance->getToppedUpBalance());
    }

    public function testToArray(): void
    {
        $balance = new CurrencyBalance('USD', '50.00', '10.00', '40.00');

        $expected = [
            'currency' => 'USD',
            'total_balance' => '50.00',
            'granted_balance' => '10.00',
            'topped_up_balance' => '40.00',
        ];

        $this->assertEquals($expected, $balance->toArray());
    }
}
