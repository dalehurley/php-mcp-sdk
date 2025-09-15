<?php

declare(strict_types=1);

namespace MCP\Tests\Types\JsonRpc;

use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\Protocol;
use MCP\Types\RequestId;
use PHPUnit\Framework\TestCase;

class JSONRPCRequestTest extends TestCase
{
    public function testCreateRequest(): void
    {
        $id = RequestId::fromString('req-123');
        $request = new JSONRPCRequest($id, 'test/method', ['param' => 'value']);

        $this->assertSame('req-123', $request->getId()->getValue());
        $this->assertSame('test/method', $request->getMethod());
        $this->assertSame(['param' => 'value'], $request->getParams());
        $this->assertSame(Protocol::JSONRPC_VERSION, $request->getJsonrpc());
    }

    public function testFromArray(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'id' => 'request-id',
            'method' => 'tools/call',
            'params' => ['name' => 'calculator'],
        ];

        $request = JSONRPCRequest::fromArray($data);

        $this->assertSame('request-id', $request->getId()->getValue());
        $this->assertSame('tools/call', $request->getMethod());
        $this->assertSame(['name' => 'calculator'], $request->getParams());
    }

    public function testFromArrayWithIntId(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'id' => 123,
            'method' => 'test',
        ];

        $request = JSONRPCRequest::fromArray($data);

        $this->assertSame(123, $request->getId()->getValue());
        $this->assertTrue($request->getId()->isInt());
    }

    public function testFromArrayInvalidJsonRpcVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or missing jsonrpc version, expected 2.0');

        JSONRPCRequest::fromArray([
            'jsonrpc' => '1.0',
            'id' => 'test',
            'method' => 'test',
        ]);
    }

    public function testFromArrayMissingId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSONRPCRequest must have an id property');

        JSONRPCRequest::fromArray([
            'jsonrpc' => '2.0',
            'method' => 'test',
        ]);
    }

    public function testFromArrayMissingMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSONRPCRequest must have a method property');

        JSONRPCRequest::fromArray([
            'jsonrpc' => '2.0',
            'id' => 'test',
        ]);
    }

    public function testWithId(): void
    {
        $request = new JSONRPCRequest(
            RequestId::fromString('old-id'),
            'test/method'
        );

        $newRequest = $request->withId(RequestId::fromString('new-id'));

        $this->assertSame('old-id', $request->getId()->getValue());
        $this->assertSame('new-id', $newRequest->getId()->getValue());
        $this->assertSame('test/method', $newRequest->getMethod());
    }

    public function testJsonSerialize(): void
    {
        $request = new JSONRPCRequest(
            RequestId::fromInt(42),
            'calculate',
            ['operation' => 'add', 'a' => 1, 'b' => 2]
        );

        $json = json_encode($request);
        $decoded = json_decode($json, true);

        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(42, $decoded['id']);
        $this->assertSame('calculate', $decoded['method']);
        $this->assertSame(['operation' => 'add', 'a' => 1, 'b' => 2], $decoded['params']);
    }

    public function testJsonSerializeWithoutParams(): void
    {
        $request = new JSONRPCRequest(
            RequestId::fromString('no-params'),
            'simple/method'
        );

        $json = json_encode($request);
        $decoded = json_decode($json, true);

        $this->assertArrayNotHasKey('params', $decoded);
    }

    public function testIsValid(): void
    {
        $this->assertTrue(JSONRPCRequest::isValid([
            'jsonrpc' => '2.0',
            'id' => 'test',
            'method' => 'test/method',
        ]));

        $this->assertTrue(JSONRPCRequest::isValid([
            'jsonrpc' => '2.0',
            'id' => 123,
            'method' => 'test/method',
            'params' => ['key' => 'value'],
        ]));

        $this->assertFalse(JSONRPCRequest::isValid([
            'jsonrpc' => '2.0',
            'method' => 'test/method', // missing id
        ]));

        $this->assertFalse(JSONRPCRequest::isValid([
            'jsonrpc' => '1.0', // wrong version
            'id' => 'test',
            'method' => 'test/method',
        ]));

        $this->assertFalse(JSONRPCRequest::isValid('not-an-array'));
    }
}
