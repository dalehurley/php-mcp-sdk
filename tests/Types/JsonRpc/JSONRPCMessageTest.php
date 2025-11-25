<?php

declare(strict_types=1);

namespace Tests\Types\JsonRpc;

use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\JsonRpc\JSONRPCMessage;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCResponse;
use PHPUnit\Framework\TestCase;

class JSONRPCMessageTest extends TestCase
{
    public function testFromArrayCreatesRequest(): void
    {
        $message = JSONRPCMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test.method',
            'params' => ['key' => 'value'],
        ]);

        $this->assertInstanceOf(JSONRPCRequest::class, $message);
    }

    public function testFromArrayCreatesNotification(): void
    {
        $message = JSONRPCMessage::fromArray([
            'jsonrpc' => '2.0',
            'method' => 'test.notification',
            'params' => ['key' => 'value'],
        ]);

        $this->assertInstanceOf(JSONRPCNotification::class, $message);
    }

    public function testFromArrayCreatesResponse(): void
    {
        $message = JSONRPCMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['data' => 'value'],
        ]);

        $this->assertInstanceOf(JSONRPCResponse::class, $message);
    }

    public function testFromArrayCreatesError(): void
    {
        $message = JSONRPCMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
        ]);

        $this->assertInstanceOf(JSONRPCError::class, $message);
    }

    public function testFromArrayThrowsForInvalidMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC message format');

        JSONRPCMessage::fromArray([
            'invalid' => 'data',
        ]);
    }

    public function testFromArrayThrowsForMissingJsonrpc(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        JSONRPCMessage::fromArray([
            'id' => 1,
            'method' => 'test',
        ]);
    }

    public function testIsValidWithRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test.method',
        ];

        $this->assertTrue(JSONRPCMessage::isValid($request));
    }

    public function testIsValidWithNotification(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'test.notification',
        ];

        $this->assertTrue(JSONRPCMessage::isValid($notification));
    }

    public function testIsValidWithResponse(): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [],
        ];

        $this->assertTrue(JSONRPCMessage::isValid($response));
    }

    public function testIsValidWithError(): void
    {
        $error = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
        ];

        $this->assertTrue(JSONRPCMessage::isValid($error));
    }

    public function testIsValidWithInvalidData(): void
    {
        $this->assertFalse(JSONRPCMessage::isValid(['invalid' => 'data']));
        $this->assertFalse(JSONRPCMessage::isValid([]));
    }

    public function testIsValidWithNonArrayValue(): void
    {
        $this->assertFalse(JSONRPCMessage::isValid('not an array'));
        $this->assertFalse(JSONRPCMessage::isValid(123));
        $this->assertFalse(JSONRPCMessage::isValid(null));
    }

    public function testIsRequestWithArray(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test.method',
        ];

        $this->assertTrue(JSONRPCMessage::isRequest($request));

        $notRequest = [
            'jsonrpc' => '2.0',
            'method' => 'test.notification',
        ];

        $this->assertFalse(JSONRPCMessage::isRequest($notRequest));
    }

    public function testIsRequestWithInstance(): void
    {
        $request = JSONRPCRequest::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test',
        ]);

        $this->assertTrue(JSONRPCMessage::isRequest($request));
    }

    public function testIsNotificationWithArray(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'test.notification',
        ];

        $this->assertTrue(JSONRPCMessage::isNotification($notification));

        $notNotification = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test.method',
        ];

        $this->assertFalse(JSONRPCMessage::isNotification($notNotification));
    }

    public function testIsNotificationWithInstance(): void
    {
        $notification = JSONRPCNotification::fromArray([
            'jsonrpc' => '2.0',
            'method' => 'test',
        ]);

        $this->assertTrue(JSONRPCMessage::isNotification($notification));
    }

    public function testIsResponseWithArray(): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['data' => 'value'],
        ];

        $this->assertTrue(JSONRPCMessage::isResponse($response));

        $notResponse = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test',
        ];

        $this->assertFalse(JSONRPCMessage::isResponse($notResponse));
    }

    public function testIsResponseWithInstance(): void
    {
        $response = JSONRPCResponse::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [],
        ]);

        $this->assertTrue(JSONRPCMessage::isResponse($response));
    }

    public function testIsErrorWithArray(): void
    {
        $error = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
        ];

        $this->assertTrue(JSONRPCMessage::isError($error));

        $notError = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => null,
        ];

        $this->assertFalse(JSONRPCMessage::isError($notError));
    }

    public function testIsErrorWithInstance(): void
    {
        $error = JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32600,
                'message' => 'Test',
            ],
        ]);

        $this->assertTrue(JSONRPCMessage::isError($error));
    }

    public function testRequestParsedCorrectly(): void
    {
        $request = JSONRPCMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 'req-123',
            'method' => 'tools/call',
            'params' => ['name' => 'my_tool'],
        ]);

        $this->assertInstanceOf(JSONRPCRequest::class, $request);
        $this->assertEquals('req-123', $request->getId()->getValue());
        $this->assertEquals('tools/call', $request->getMethod());
        $this->assertEquals(['name' => 'my_tool'], $request->getParams());
    }

    public function testNotificationParsedCorrectly(): void
    {
        $notification = JSONRPCMessage::fromArray([
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => ['progressToken' => 'token-1', 'progress' => 50],
        ]);

        $this->assertInstanceOf(JSONRPCNotification::class, $notification);
        $this->assertEquals('notifications/progress', $notification->getMethod());
        $this->assertEquals(['progressToken' => 'token-1', 'progress' => 50], $notification->getParams());
    }

    public function testResponseParsedCorrectly(): void
    {
        $response = JSONRPCMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 42,
            'result' => ['tools' => [['name' => 'tool1'], ['name' => 'tool2']]],
        ]);

        $this->assertInstanceOf(JSONRPCResponse::class, $response);
        $this->assertEquals(42, $response->getId()->getValue());
        // getResult() returns a Result object, not an array
        $this->assertInstanceOf(\MCP\Types\Result::class, $response->getResult());
    }

    public function testErrorParsedCorrectly(): void
    {
        $error = JSONRPCMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 99,
            'error' => [
                'code' => -32601,
                'message' => 'Method not found',
                'data' => ['method' => 'unknown_method'],
            ],
        ]);

        $this->assertInstanceOf(JSONRPCError::class, $error);
        $this->assertEquals(99, $error->getId()->getValue());
        $this->assertEquals(-32601, $error->getCode());
        $this->assertEquals('Method not found', $error->getMessage());
        $this->assertEquals(['method' => 'unknown_method'], $error->getData());
    }
}

