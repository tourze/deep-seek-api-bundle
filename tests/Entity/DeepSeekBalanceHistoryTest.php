<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekBalanceHistory::class)]
final class DeepSeekBalanceHistoryTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new DeepSeekBalanceHistory();
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['currency', 'test_value'],
            ['totalBalance', 'test_value'],
            ['grantedBalance', 'test_value'],
            ['toppedUpBalance', 'test_value'],
        ];
    }

    public function testConstruct(): void
    {
        $history = new DeepSeekBalanceHistory();

        $this->assertEquals('DeepSeekBalanceHistory', (new \ReflectionClass($history))->getShortName());
        $this->assertEquals('CNY', $history->getCurrency());
        $this->assertEquals('0.0000', $history->getTotalBalance());
        $this->assertEquals('0.0000', $history->getGrantedBalance());
        $this->assertEquals('0.0000', $history->getToppedUpBalance());
        $this->assertNotNull($history->getRecordTime());
        $this->assertNull($history->getId());
        $this->assertNull($history->getApiKey());
        $this->assertNull($history->getBalanceChange());
        $this->assertNull($history->getChangeType());
        $this->assertNull($history->getChangeReason());
        $this->assertNull($history->getDataSource());
        $this->assertNull($history->getMetadata());
    }

    public function testSpecificSettersAndGetters(): void
    {
        $history = new DeepSeekBalanceHistory();
        $apiKey = new DeepSeekApiKey();
        $recordedAt = new \DateTimeImmutable();

        $history->setApiKey($apiKey);
        $history->setCurrency('USD');
        $history->setTotalBalance('100.5000');
        $history->setGrantedBalance('80.0000');
        $history->setToppedUpBalance('20.5000');
        $history->setBalanceChange('-5.2500');
        $history->setChangeType('consumption');
        $history->setChangeReason('API调用消费');
        $history->setRecordTime($recordedAt);
        $history->setDataSource('api');
        $history->setMetadata(['test' => 'value']);

        $this->assertSame($apiKey, $history->getApiKey());
        $this->assertEquals('USD', $history->getCurrency());
        $this->assertEquals('100.5000', $history->getTotalBalance());
        $this->assertEquals('80.0000', $history->getGrantedBalance());
        $this->assertEquals('20.5000', $history->getToppedUpBalance());
        $this->assertEquals('-5.2500', $history->getBalanceChange());
        $this->assertEquals('consumption', $history->getChangeType());
        $this->assertEquals('API调用消费', $history->getChangeReason());
        $this->assertSame($recordedAt, $history->getRecordTime());
        $this->assertEquals('api', $history->getDataSource());
        $this->assertEquals(['test' => 'value'], $history->getMetadata());
    }

    public function testStringable(): void
    {
        $history = new DeepSeekBalanceHistory();
        $history->setCurrency('CNY');
        $history->setTotalBalance('100.0000');

        $result = (string) $history;

        $this->assertStringContainsString('CNY', $result);
        $this->assertStringContainsString('100.0000', $result);
    }

    public function testGetTotalBalanceAsFloat(): void
    {
        $history = new DeepSeekBalanceHistory();
        $history->setTotalBalance('123.4567');

        $this->assertEquals(123.4567, $history->getTotalBalanceAsFloat());
    }

    public function testGetBalanceChangeAsFloat(): void
    {
        $history = new DeepSeekBalanceHistory();
        $history->setBalanceChange('-15.25');

        $this->assertEquals(-15.25, $history->getBalanceChangeAsFloat());
    }

    public function testGetBalanceChangeAsFloatWithNull(): void
    {
        $history = new DeepSeekBalanceHistory();

        $this->assertEquals(0.0, $history->getBalanceChangeAsFloat());
    }
}
