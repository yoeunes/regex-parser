<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Unit\Lsp\Protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Lsp\Protocol\Message;

final class MessageTest extends TestCase
{
    #[Test]
    public function test_create_message_with_all_properties(): void
    {
        $message = new Message(
            jsonrpc: '2.0',
            method: 'initialize',
            id: 1,
            params: ['foo' => 'bar'],
            result: ['baz' => 'qux'],
            error: ['code' => -32600, 'message' => 'Invalid Request'],
        );

        $this->assertSame('2.0', $message->jsonrpc);
        $this->assertSame('initialize', $message->method);
        $this->assertSame(1, $message->id);
        $this->assertSame(['foo' => 'bar'], $message->params);
        $this->assertSame(['baz' => 'qux'], $message->result);
        $this->assertSame(['code' => -32600, 'message' => 'Invalid Request'], $message->error);
    }

    #[Test]
    public function test_create_message_with_string_id(): void
    {
        $message = new Message(
            jsonrpc: '2.0',
            method: 'test',
            id: 'abc-123',
        );

        $this->assertSame('abc-123', $message->id);
    }

    #[Test]
    public function test_is_request_returns_true_for_request(): void
    {
        $message = new Message(
            jsonrpc: '2.0',
            method: 'textDocument/hover',
            id: 1,
        );

        $this->assertTrue($message->isRequest());
        $this->assertFalse($message->isNotification());
        $this->assertFalse($message->isResponse());
    }

    #[Test]
    public function test_is_notification_returns_true_for_notification(): void
    {
        $message = new Message(
            jsonrpc: '2.0',
            method: 'textDocument/didOpen',
            id: null,
        );

        $this->assertFalse($message->isRequest());
        $this->assertTrue($message->isNotification());
        $this->assertFalse($message->isResponse());
    }

    #[Test]
    public function test_is_response_returns_true_for_response(): void
    {
        $message = new Message(
            jsonrpc: '2.0',
            method: null,
            id: 1,
            result: ['capabilities' => []],
        );

        $this->assertFalse($message->isRequest());
        $this->assertFalse($message->isNotification());
        $this->assertTrue($message->isResponse());
    }

    #[Test]
    #[DataProvider('provideMessageTypes')]
    public function test_message_type_detection(
        ?string $method,
        int|string|null $id,
        bool $expectedRequest,
        bool $expectedNotification,
        bool $expectedResponse,
    ): void {
        $message = new Message(
            jsonrpc: '2.0',
            method: $method,
            id: $id,
        );

        $this->assertSame($expectedRequest, $message->isRequest());
        $this->assertSame($expectedNotification, $message->isNotification());
        $this->assertSame($expectedResponse, $message->isResponse());
    }

    /**
     * @return iterable<string, array{?string, int|string|null, bool, bool, bool}>
     */
    public static function provideMessageTypes(): iterable
    {
        // method, id, isRequest, isNotification, isResponse
        yield 'request with int id' => ['initialize', 1, true, false, false];
        yield 'request with string id' => ['initialize', 'abc', true, false, false];
        yield 'notification' => ['textDocument/didOpen', null, false, true, false];
        yield 'response' => [null, 1, false, false, true];
        yield 'response with string id' => [null, 'abc', false, false, true];
        yield 'invalid (no method, no id)' => [null, null, false, false, false];
    }

    #[Test]
    public function test_message_is_readonly(): void
    {
        $reflection = new \ReflectionClass(Message::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function test_params_are_nullable(): void
    {
        $message = new Message(
            jsonrpc: '2.0',
            method: 'shutdown',
            id: 1,
            params: null,
        );

        $this->assertNull($message->params);
    }

    #[Test]
    public function test_empty_params_array(): void
    {
        $message = new Message(
            jsonrpc: '2.0',
            method: 'initialize',
            id: 1,
            params: [],
        );

        $this->assertSame([], $message->params);
    }

    #[Test]
    public function test_nested_params(): void
    {
        $params = [
            'textDocument' => [
                'uri' => 'file:///test.php',
                'text' => "<?php\necho 'hello';",
            ],
        ];

        $message = new Message(
            jsonrpc: '2.0',
            method: 'textDocument/didOpen',
            id: null,
            params: $params,
        );

        $this->assertSame($params, $message->params);
        $this->assertSame('file:///test.php', $message->params['textDocument']['uri']);
    }
}
