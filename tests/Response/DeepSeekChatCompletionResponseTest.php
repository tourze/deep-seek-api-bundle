<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DeepSeekApiBundle\Response\DeepSeekChatCompletionResponse;
use Tourze\OpenAiContracts\DTO\ChatChoice;
use Tourze\OpenAiContracts\DTO\ChatMessage;
use Tourze\OpenAiContracts\DTO\Usage;

/**
 * @internal
 */
#[CoversClass(DeepSeekChatCompletionResponse::class)]
class DeepSeekChatCompletionResponseTest extends TestCase
{
    public function testFromArrayCreatesInstance(): void
    {
        $data = [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'deepseek-chat',
        ];

        $response = DeepSeekChatCompletionResponse::fromArray($data);

        $this->assertInstanceOf(DeepSeekChatCompletionResponse::class, $response);
    }

    public function testGetId(): void
    {
        $data = ['id' => 'chatcmpl-123'];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals('chatcmpl-123', $response->getId());
    }

    public function testGetIdWithNull(): void
    {
        $data = [];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertNull($response->getId());
    }

    public function testGetObject(): void
    {
        $data = ['object' => 'chat.completion'];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals('chat.completion', $response->getObject());
    }

    public function testGetObjectDefault(): void
    {
        $data = [];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals('chat.completion', $response->getObject());
    }

    public function testGetCreated(): void
    {
        $data = ['created' => 1234567890];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals(1234567890, $response->getCreated());
    }

    public function testGetCreatedWithString(): void
    {
        $data = ['created' => '1234567890'];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals(1234567890, $response->getCreated());
    }

    public function testGetCreatedWithNull(): void
    {
        $data = [];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertNull($response->getCreated());
    }

    public function testGetModel(): void
    {
        $data = ['model' => 'deepseek-chat'];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals('deepseek-chat', $response->getModel());
    }

    public function testGetModelWithNull(): void
    {
        $data = [];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertNull($response->getModel());
    }

    public function testGetChoicesEmpty(): void
    {
        $data = [];
        $response = new DeepSeekChatCompletionResponse($data);

        $choices = $response->getChoices();
        $this->assertIsArray($choices);
        $this->assertEmpty($choices);
    }

    public function testGetChoicesWithSingleChoice(): void
    {
        $data = [
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you today?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ];

        $response = new DeepSeekChatCompletionResponse($data);
        $choices = $response->getChoices();

        $this->assertCount(1, $choices);
        $this->assertInstanceOf(ChatChoice::class, $choices[0]);

        $choice = $choices[0];
        $this->assertEquals(0, $choice->getIndex());
        $this->assertEquals('stop', $choice->getFinishReason());

        $message = $choice->getMessage();
        $this->assertInstanceOf(ChatMessage::class, $message);
        $this->assertEquals('assistant', $message->getRole());
        $this->assertEquals('Hello! How can I help you today?', $message->getContent());
    }

    public function testGetChoicesWithMultipleChoices(): void
    {
        $data = [
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'First response',
                    ],
                    'finish_reason' => 'stop',
                ],
                [
                    'index' => 1,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Second response',
                    ],
                    'finish_reason' => 'length',
                ],
            ],
        ];

        $response = new DeepSeekChatCompletionResponse($data);
        $choices = $response->getChoices();

        $this->assertCount(2, $choices);

        $this->assertEquals(0, $choices[0]->getIndex());
        $this->assertEquals('First response', $choices[0]->getMessage()->getContent());
        $this->assertEquals('stop', $choices[0]->getFinishReason());

        $this->assertEquals(1, $choices[1]->getIndex());
        $this->assertEquals('Second response', $choices[1]->getMessage()->getContent());
        $this->assertEquals('length', $choices[1]->getFinishReason());
    }

    public function testGetChoicesWithIncompleteData(): void
    {
        $data = [
            'choices' => [
                [
                    'message' => [],
                ],
                [
                    'index' => 1,
                    'message' => [
                        'role' => 'assistant',
                    ],
                ],
            ],
        ];

        $response = new DeepSeekChatCompletionResponse($data);
        $choices = $response->getChoices();

        $this->assertCount(2, $choices);

        // 第一个选择使用默认值
        $this->assertEquals(0, $choices[0]->getIndex());
        $this->assertEquals('assistant', $choices[0]->getMessage()->getRole());
        $this->assertEquals('', $choices[0]->getMessage()->getContent());
        $this->assertNull($choices[0]->getFinishReason());

        // 第二个选择
        $this->assertEquals(1, $choices[1]->getIndex());
        $this->assertEquals('assistant', $choices[1]->getMessage()->getRole());
        $this->assertEquals('', $choices[1]->getMessage()->getContent());
    }

    public function testGetUsageWithCompleteData(): void
    {
        $data = [
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ];

        $response = new DeepSeekChatCompletionResponse($data);
        $usage = $response->getUsage();

        $this->assertInstanceOf(Usage::class, $usage);
        $this->assertEquals(10, $usage->getPromptTokens());
        $this->assertEquals(20, $usage->getCompletionTokens());
        $this->assertEquals(30, $usage->getTotalTokens());
    }

    public function testGetUsageWithPartialData(): void
    {
        $data = [
            'usage' => [
                'prompt_tokens' => 15,
                'total_tokens' => 25,
            ],
        ];

        $response = new DeepSeekChatCompletionResponse($data);
        $usage = $response->getUsage();

        $this->assertInstanceOf(Usage::class, $usage);
        $this->assertEquals(15, $usage->getPromptTokens());
        $this->assertEquals(0, $usage->getCompletionTokens());
        $this->assertEquals(25, $usage->getTotalTokens());
    }

    public function testGetUsageWithNull(): void
    {
        $data = [];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertNull($response->getUsage());
    }

    public function testGetSystemFingerprint(): void
    {
        $data = ['system_fingerprint' => 'fp_123abc'];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals('fp_123abc', $response->getSystemFingerprint());
    }

    public function testGetSystemFingerprintWithNull(): void
    {
        $data = [];
        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertNull($response->getSystemFingerprint());
    }

    public function testToArray(): void
    {
        $data = [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'deepseek-chat',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Test response',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ];

        $response = new DeepSeekChatCompletionResponse($data);
        $result = $response->toArray();

        $this->assertEquals($data, $result);
    }

    public function testJsonSerialize(): void
    {
        $data = [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'model' => 'deepseek-chat',
        ];

        $response = new DeepSeekChatCompletionResponse($data);
        $result = $response->jsonSerialize();

        $this->assertEquals($data, $result);
    }

    public function testJsonEncodeResponse(): void
    {
        $data = [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'model' => 'deepseek-chat',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'JSON test',
                    ],
                ],
            ],
        ];

        $response = new DeepSeekChatCompletionResponse($data);
        $json = json_encode($response);
        $this->assertNotFalse($json, 'JSON encoding should not fail');

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded, 'JSON decoding should not fail');
        $this->assertEquals($data, $decoded);
    }

    public function testCompleteResponse(): void
    {
        $data = [
            'id' => 'chatcmpl-complete-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'deepseek-chat',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'This is a complete response with all fields populated.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 25,
                'completion_tokens' => 12,
                'total_tokens' => 37,
            ],
            'system_fingerprint' => 'fp_complete_123',
        ];

        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals('chatcmpl-complete-123', $response->getId());
        $this->assertEquals('chat.completion', $response->getObject());
        $this->assertEquals(1234567890, $response->getCreated());
        $this->assertEquals('deepseek-chat', $response->getModel());
        $this->assertEquals('fp_complete_123', $response->getSystemFingerprint());

        $choices = $response->getChoices();
        $this->assertCount(1, $choices);
        $this->assertEquals('This is a complete response with all fields populated.', $choices[0]->getMessage()->getContent());

        $usage = $response->getUsage();
        $this->assertNotNull($usage, 'Usage should not be null');
        $this->assertEquals(25, $usage->getPromptTokens());
        $this->assertEquals(12, $usage->getCompletionTokens());
        $this->assertEquals(37, $usage->getTotalTokens());

        $this->assertEquals($data, $response->toArray());
    }

    public function testEmptyDataResponse(): void
    {
        $response = new DeepSeekChatCompletionResponse([]);

        $this->assertNull($response->getId());
        $this->assertEquals('chat.completion', $response->getObject());
        $this->assertNull($response->getCreated());
        $this->assertNull($response->getModel());
        $this->assertNull($response->getSystemFingerprint());
        $this->assertNull($response->getUsage());
        $this->assertEmpty($response->getChoices());
    }

    public function testReasonerModelResponse(): void
    {
        $data = [
            'id' => 'chatcmpl-reasoner-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'deepseek-reasoner',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Let me think about this step by step...',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 50,
                'completion_tokens' => 150,
                'total_tokens' => 200,
            ],
        ];

        $response = new DeepSeekChatCompletionResponse($data);

        $this->assertEquals('deepseek-reasoner', $response->getModel());

        $choices = $response->getChoices();
        $this->assertCount(1, $choices);
        $this->assertEquals('Let me think about this step by step...', $choices[0]->getMessage()->getContent());

        $usage = $response->getUsage();
        $this->assertNotNull($usage, 'Usage should not be null');
        $this->assertEquals(200, $usage->getTotalTokens());
    }
}
