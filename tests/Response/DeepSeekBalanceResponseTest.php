<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DeepSeekApiBundle\Response\DeepSeekBalanceResponse;
use Tourze\OpenAiContracts\DTO\Balance;

/**
 * @internal
 */
#[CoversClass(DeepSeekBalanceResponse::class)]
class DeepSeekBalanceResponseTest extends TestCase
{
    public function testFromArrayCreatesInstance(): void
    {
        $data = [
            'id' => 'bal_123',
            'object' => 'balance',
            'created' => 1234567890,
            'total_balance' => 100.0,
            'used_balance' => 10.0,
            'remaining_balance' => 90.0,
            'currency' => 'CNY',
        ];

        $response = DeepSeekBalanceResponse::fromArray($data);

        $this->assertInstanceOf(DeepSeekBalanceResponse::class, $response);
    }

    public function testGetId(): void
    {
        $data = ['id' => 'bal_123'];
        $response = new DeepSeekBalanceResponse($data);

        $this->assertEquals('bal_123', $response->getId());
    }

    public function testGetIdWithNull(): void
    {
        $data = [];
        $response = new DeepSeekBalanceResponse($data);

        $this->assertNull($response->getId());
    }

    public function testGetObject(): void
    {
        $data = ['object' => 'balance'];
        $response = new DeepSeekBalanceResponse($data);

        $this->assertEquals('balance', $response->getObject());
    }

    public function testGetObjectDefault(): void
    {
        $data = [];
        $response = new DeepSeekBalanceResponse($data);

        $this->assertEquals('balance', $response->getObject());
    }

    public function testGetCreated(): void
    {
        $data = ['created' => 1234567890];
        $response = new DeepSeekBalanceResponse($data);

        $this->assertEquals(1234567890, $response->getCreated());
    }

    public function testGetCreatedWithString(): void
    {
        $data = ['created' => '1234567890'];
        $response = new DeepSeekBalanceResponse($data);

        $this->assertEquals(1234567890, $response->getCreated());
    }

    public function testGetCreatedWithNull(): void
    {
        $data = [];
        $response = new DeepSeekBalanceResponse($data);

        $this->assertNull($response->getCreated());
    }

    public function testGetBalanceWithStandardKeys(): void
    {
        $data = [
            'total_balance' => 100.0,
            'used_balance' => 10.0,
            'remaining_balance' => 90.0,
            'currency' => 'CNY',
        ];

        $response = new DeepSeekBalanceResponse($data);
        $balance = $response->getBalance();

        $this->assertInstanceOf(Balance::class, $balance);
        $this->assertEquals(100.0, $balance->getTotalAmount());
        $this->assertEquals(10.0, $balance->getUsedAmount());
        $this->assertEquals(90.0, $balance->getRemainingAmount());
        $this->assertEquals('CNY', $balance->getCurrency());
    }

    public function testGetBalanceWithAlternativeKeys(): void
    {
        $data = [
            'total_granted' => 100.0,
            'total_used' => 10.0,
            'total_available' => 90.0,
            'currency' => 'USD',
        ];

        $response = new DeepSeekBalanceResponse($data);
        $balance = $response->getBalance();

        $this->assertEquals(100.0, $balance->getTotalAmount());
        $this->assertEquals(10.0, $balance->getUsedAmount());
        $this->assertEquals(90.0, $balance->getRemainingAmount());
        $this->assertEquals('USD', $balance->getCurrency());
    }

    public function testGetBalanceWithCalculatedRemaining(): void
    {
        $data = [
            'total_balance' => 100.0,
            'used_balance' => 10.0,
            // remaining_balance 缺失，应该自动计算
            'currency' => 'CNY',
        ];

        $response = new DeepSeekBalanceResponse($data);
        $balance = $response->getBalance();

        $this->assertEquals(90.0, $balance->getRemainingAmount());
    }

    public function testGetBalanceWithDefaults(): void
    {
        $data = [];
        $response = new DeepSeekBalanceResponse($data);
        $balance = $response->getBalance();

        $this->assertEquals(0.0, $balance->getTotalAmount());
        $this->assertEquals(0.0, $balance->getUsedAmount());
        $this->assertEquals(0.0, $balance->getRemainingAmount());
        $this->assertEquals('USD', $balance->getCurrency());
    }

    public function testGetBalanceWithMixedTypes(): void
    {
        $data = [
            'total_balance' => '100.50',
            'used_balance' => 10,
            'remaining_balance' => '90.50',
            'currency' => 'EUR',
        ];

        $response = new DeepSeekBalanceResponse($data);
        $balance = $response->getBalance();

        $this->assertEquals(100.50, $balance->getTotalAmount());
        $this->assertEquals(10.0, $balance->getUsedAmount());
        $this->assertEquals(90.50, $balance->getRemainingAmount());
        $this->assertEquals('EUR', $balance->getCurrency());
    }

    public function testGetBalanceWithCNYCurrency(): void
    {
        $data = [
            'total_balance' => 1000.0,
            'used_balance' => 100.0,
            'remaining_balance' => 900.0,
            'currency' => 'CNY',
        ];

        $response = new DeepSeekBalanceResponse($data);
        $balance = $response->getBalance();

        $this->assertEquals('CNY', $balance->getCurrency());
    }

    public function testToArray(): void
    {
        $data = [
            'id' => 'bal_123',
            'object' => 'balance',
            'created' => 1234567890,
            'total_balance' => 100.0,
            'currency' => 'CNY',
        ];

        $response = new DeepSeekBalanceResponse($data);
        $result = $response->toArray();

        $this->assertEquals($data, $result);
    }

    public function testJsonSerialize(): void
    {
        $data = [
            'id' => 'bal_123',
            'object' => 'balance',
            'total_balance' => 100.0,
            'currency' => 'CNY',
        ];

        $response = new DeepSeekBalanceResponse($data);
        $result = $response->jsonSerialize();

        $this->assertEquals($data, $result);
    }

    public function testJsonEncodeResponse(): void
    {
        $data = [
            'id' => 'bal_123',
            'object' => 'balance',
            'total_balance' => 100.0,
            'currency' => 'CNY',
        ];

        $response = new DeepSeekBalanceResponse($data);

        // 先验证 toArray 方法工作正常
        $arrayResult = $response->toArray();
        $this->assertEquals($data, $arrayResult);

        // 再验证 jsonSerialize 方法工作正常
        $jsonSerializeResult = $response->jsonSerialize();
        $this->assertEquals($data, $jsonSerializeResult);

        $json = json_encode($response);
        $this->assertNotFalse($json, 'JSON encoding should not fail');
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded, 'JSON decoding should not fail');
        $this->assertEquals($data, $decoded);
    }

    public function testComplexBalanceResponse(): void
    {
        $data = [
            'id' => 'bal_complex_123',
            'object' => 'balance',
            'created' => 1234567890,
            'total_balance' => 500.1234,
            'used_balance' => 99.5678,
            'remaining_balance' => 400.5556,
            'currency' => 'CNY',
            'metadata' => [
                'source' => 'api',
                'updated_at' => '2023-12-01T10:00:00Z',
            ],
        ];

        $response = new DeepSeekBalanceResponse($data);

        $this->assertEquals('bal_complex_123', $response->getId());
        $this->assertEquals('balance', $response->getObject());
        $this->assertEquals(1234567890, $response->getCreated());

        $balance = $response->getBalance();
        $this->assertEquals(500.1234, $balance->getTotalAmount());
        $this->assertEquals(99.5678, $balance->getUsedAmount());
        $this->assertEquals(400.5556, $balance->getRemainingAmount());
        $this->assertEquals('CNY', $balance->getCurrency());

        $this->assertEquals($data, $response->toArray());
    }

    public function testEmptyDataResponse(): void
    {
        $response = new DeepSeekBalanceResponse([]);

        $this->assertNull($response->getId());
        $this->assertEquals('balance', $response->getObject());
        $this->assertNull($response->getCreated());

        $balance = $response->getBalance();
        $this->assertEquals(0.0, $balance->getTotalAmount());
        $this->assertEquals(0.0, $balance->getUsedAmount());
        $this->assertEquals(0.0, $balance->getRemainingAmount());
        $this->assertEquals('USD', $balance->getCurrency());
    }
}
