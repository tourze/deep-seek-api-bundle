<?php

namespace Tourze\DeepSeekApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DeepSeekApiBundle\DTO\BalanceInfo;
use Tourze\DeepSeekApiBundle\DTO\CurrencyBalance;

/**
 * @internal
 */
#[CoversClass(BalanceInfo::class)]
class BalanceInfoTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $cnyBalance = new CurrencyBalance('CNY', '100.00', '10.00', '90.00');
        $usdBalance = new CurrencyBalance('USD', '50.00', '5.00', '45.00');

        $balanceInfo = new BalanceInfo(true, [$cnyBalance, $usdBalance]);

        $this->assertTrue($balanceInfo->isAvailable());
        $this->assertCount(2, $balanceInfo->getBalanceInfos());
    }

    public function testGetBalanceByCurrency(): void
    {
        $cnyBalance = new CurrencyBalance('CNY', '100.00', '10.00', '90.00');
        $usdBalance = new CurrencyBalance('USD', '50.00', '5.00', '45.00');

        $balanceInfo = new BalanceInfo(true, [$cnyBalance, $usdBalance]);

        $result = $balanceInfo->getBalanceByCurrency('CNY');
        $this->assertNotNull($result);
        $this->assertEquals('CNY', $result->getCurrency());
        $this->assertEquals('100.00', $result->getTotalBalance());

        $result = $balanceInfo->getBalanceByCurrency('EUR');
        $this->assertNull($result);
    }

    public function testHasPositiveBalance(): void
    {
        $positiveBalance = new CurrencyBalance('CNY', '100.00', '10.00', '90.00');
        $balanceInfo = new BalanceInfo(true, [$positiveBalance]);
        $this->assertTrue($balanceInfo->hasPositiveBalance());

        $zeroBalance = new CurrencyBalance('USD', '0.00', '0.00', '0.00');
        $balanceInfo = new BalanceInfo(true, [$zeroBalance]);
        $this->assertFalse($balanceInfo->hasPositiveBalance());
    }

    public function testFromArray(): void
    {
        $data = [
            'is_available' => true,
            'balance_infos' => [
                [
                    'currency' => 'CNY',
                    'total_balance' => '110.00',
                    'granted_balance' => '10.00',
                    'topped_up_balance' => '100.00',
                ],
                [
                    'currency' => 'USD',
                    'total_balance' => '20.00',
                    'granted_balance' => '5.00',
                    'topped_up_balance' => '15.00',
                ],
            ],
        ];

        $balanceInfo = BalanceInfo::fromArray($data);

        $this->assertTrue($balanceInfo->isAvailable());
        $this->assertCount(2, $balanceInfo->getBalanceInfos());

        $cnyBalance = $balanceInfo->getBalanceByCurrency('CNY');
        $this->assertNotNull($cnyBalance);
        $this->assertEquals('110.00', $cnyBalance->getTotalBalance());
    }

    public function testToArray(): void
    {
        $cnyBalance = new CurrencyBalance('CNY', '100.00', '10.00', '90.00');
        $balanceInfo = new BalanceInfo(true, [$cnyBalance]);

        $expected = [
            'is_available' => true,
            'balance_infos' => [
                [
                    'currency' => 'CNY',
                    'total_balance' => '100.00',
                    'granted_balance' => '10.00',
                    'topped_up_balance' => '90.00',
                ],
            ],
        ];

        $this->assertEquals($expected, $balanceInfo->toArray());
    }
}
