<?php

namespace Tourze\DeepSeekApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiLog;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekApiLog::class)]
class DeepSeekApiLogTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new DeepSeekApiLog();
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['endpoint', 'chat_completion'],
            ['method', 'POST'],
            ['url', 'https://api.deepseek.com/v1/chat/completions'],
            ['statusCode', 200],
            ['status', 'success'],
        ];
    }

    public function testConstructor(): void
    {
        $log = new DeepSeekApiLog();

        $this->assertNull($log->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $log->getRequestTime());
        $this->assertNull($log->getRespondTime());
        $this->assertNull($log->getResponseTime());
    }

    public function testCreateForRequest(): void
    {
        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('test-key');
        $apiKey->setName('Test Key');

        $log = DeepSeekApiLog::createForRequest(
            $apiKey,
            DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION,
            'POST',
            'https://api.deepseek.com/v1/chat/completions',
            ['model' => 'deepseek-chat']
        );

        $this->assertSame($apiKey, $log->getApiKey());
        $this->assertSame(DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION, $log->getEndpoint());
        $this->assertSame('POST', $log->getMethod());
        $this->assertSame('https://api.deepseek.com/v1/chat/completions', $log->getUrl());
        $this->assertSame(['model' => 'deepseek-chat'], $log->getRequestBody());
    }

    public function testSpecificGettersAndSetters(): void
    {
        $log = new DeepSeekApiLog();

        $apiKey = new DeepSeekApiKey();
        $apiKey->setApiKey('test-key');
        $apiKey->setName('Test Key');

        $log->setApiKey($apiKey);
        $this->assertSame($apiKey, $log->getApiKey());

        $log->setEndpoint(DeepSeekApiLog::ENDPOINT_GET_BALANCE);
        $this->assertSame(DeepSeekApiLog::ENDPOINT_GET_BALANCE, $log->getEndpoint());

        $log->setMethod('GET');
        $this->assertSame('GET', $log->getMethod());

        $log->setUrl('https://api.deepseek.com/v1/balance');
        $this->assertSame('https://api.deepseek.com/v1/balance', $log->getUrl());

        $headers = ['Authorization' => 'Bearer test'];
        $log->setRequestHeaders($headers);
        $this->assertSame($headers, $log->getRequestHeaders());

        $body = ['model' => 'deepseek-chat'];
        $log->setRequestBody($body);
        $this->assertSame($body, $log->getRequestBody());

        $log->setStatusCode(200);
        $this->assertSame(200, $log->getStatusCode());

        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);
        $this->assertSame(DeepSeekApiLog::STATUS_SUCCESS, $log->getStatus());

        $responseHeaders = ['Content-Type' => 'application/json'];
        $log->setResponseHeaders($responseHeaders);
        $this->assertSame($responseHeaders, $log->getResponseHeaders());

        $responseBody = ['success' => true];
        $log->setResponseBody($responseBody);
        $this->assertSame($responseBody, $log->getResponseBody());

        $log->setErrorMessage('Test error');
        $this->assertSame('Test error', $log->getErrorMessage());

        $log->setErrorCode('ERR_001');
        $this->assertSame('ERR_001', $log->getErrorCode());

        $log->setResponseTime(1.5);
        $this->assertSame(1.5, $log->getResponseTime());

        $respondedAt = new \DateTimeImmutable();
        $log->setRespondTime($respondedAt);
        $this->assertSame($respondedAt, $log->getRespondTime());

        $log->setIpAddress('192.168.1.1');
        $this->assertSame('192.168.1.1', $log->getIpAddress());

        $log->setUserAgent('Mozilla/5.0');
        $this->assertSame('Mozilla/5.0', $log->getUserAgent());

        $metadata = ['custom' => 'data'];
        $log->setMetadata($metadata);
        $this->assertSame($metadata, $log->getMetadata());
    }

    public function testMarkAsSuccess(): void
    {
        $log = new DeepSeekApiLog();
        $responseBody = ['success' => true];

        $log->markAsSuccess(200, $responseBody);

        $this->assertSame(DeepSeekApiLog::STATUS_SUCCESS, $log->getStatus());
        $this->assertSame(200, $log->getStatusCode());
        $this->assertSame($responseBody, $log->getResponseBody());
        $this->assertNotNull($log->getRespondTime());
        $this->assertNotNull($log->getResponseTime());
        $this->assertTrue($log->isSuccess());
        $this->assertFalse($log->isError());
    }

    public function testMarkAsError(): void
    {
        $log = new DeepSeekApiLog();

        $log->markAsError('Connection failed', 'ERR_CONN', 500);

        $this->assertSame(DeepSeekApiLog::STATUS_ERROR, $log->getStatus());
        $this->assertSame('Connection failed', $log->getErrorMessage());
        $this->assertSame('ERR_CONN', $log->getErrorCode());
        $this->assertSame(500, $log->getStatusCode());
        $this->assertNotNull($log->getRespondTime());
        $this->assertNotNull($log->getResponseTime());
        $this->assertTrue($log->isError());
        $this->assertFalse($log->isSuccess());
    }

    public function testMarkAsTimeout(): void
    {
        $log = new DeepSeekApiLog();

        $log->markAsTimeout();

        $this->assertSame(DeepSeekApiLog::STATUS_TIMEOUT, $log->getStatus());
        $this->assertSame('Request timeout', $log->getErrorMessage());
        $this->assertNotNull($log->getRespondTime());
        $this->assertNotNull($log->getResponseTime());
    }

    public function testCompleteWithSuccess(): void
    {
        $log = new DeepSeekApiLog();

        $log->completeWithSuccess(200, 0.5);

        $this->assertSame(200, $log->getStatusCode());
        $this->assertSame(DeepSeekApiLog::STATUS_SUCCESS, $log->getStatus());
        $this->assertSame(0.5, $log->getResponseTime());
        $this->assertNotNull($log->getRespondTime());
    }

    public function testCompleteWithError(): void
    {
        $log = new DeepSeekApiLog();

        $log->completeWithError('Server error', 2.5);

        $this->assertSame(DeepSeekApiLog::STATUS_ERROR, $log->getStatus());
        $this->assertSame('Server error', $log->getErrorMessage());
        $this->assertSame(2.5, $log->getResponseTime());
        $this->assertNotNull($log->getRespondTime());
    }

    public function testToString(): void
    {
        $log = new DeepSeekApiLog();
        $log->setMethod('POST');
        $log->setEndpoint(DeepSeekApiLog::ENDPOINT_CHAT_COMPLETION);
        $log->setStatus(DeepSeekApiLog::STATUS_SUCCESS);

        $expected = 'DeepSeekApiLog #0: POST chat_completion [success]';
        $this->assertSame($expected, (string) $log);
    }
}
