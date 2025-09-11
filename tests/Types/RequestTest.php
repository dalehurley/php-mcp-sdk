<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\Request;
use MCP\Types\RequestMeta;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testCreateRequest(): void
    {
        $request = new Request('test/method', ['param1' => 'value1']);

        $this->assertSame('test/method', $request->getMethod());
        $this->assertSame(['param1' => 'value1'], $request->getParams());
        $this->assertTrue($request->hasParams());
    }

    public function testCreateRequestWithoutParams(): void
    {
        $request = new Request('simple/method');

        $this->assertSame('simple/method', $request->getMethod());
        $this->assertNull($request->getParams());
        $this->assertFalse($request->hasParams());
    }

    public function testFromArray(): void
    {
        $data = [
            'method' => 'tools/list',
            'params' => [
                'filter' => 'active',
                '_meta' => ['progressToken' => 'token-123']
            ]
        ];

        $request = Request::fromArray($data);

        $this->assertSame('tools/list', $request->getMethod());
        $this->assertSame(['filter' => 'active', '_meta' => ['progressToken' => 'token-123']], $request->getParams());
    }

    public function testFromArrayWithoutMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request must have a method property');

        Request::fromArray(['params' => []]);
    }

    public function testFromArrayWithNonStringMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request must have a method property');

        Request::fromArray(['method' => 123]);
    }

    public function testGetParam(): void
    {
        $request = new Request('test', ['key1' => 'value1', 'key2' => 'value2']);

        $this->assertSame('value1', $request->getParam('key1'));
        $this->assertSame('value2', $request->getParam('key2'));
        $this->assertNull($request->getParam('nonexistent'));
    }

    public function testGetMeta(): void
    {
        $request = new Request('test', ['_meta' => ['progressToken' => 'abc']]);

        $meta = $request->getMeta();
        $this->assertInstanceOf(RequestMeta::class, $meta);
    }

    public function testGetMetaWithoutMeta(): void
    {
        $request = new Request('test', ['param' => 'value']);

        $this->assertNull($request->getMeta());
    }

    public function testGetMetaWithInvalidMeta(): void
    {
        $request = new Request('test', ['_meta' => 'not-an-array']);

        $this->assertNull($request->getMeta());
    }

    public function testWithParams(): void
    {
        $request = new Request('test', ['old' => 'value']);
        $newRequest = $request->withParams(['new' => 'value']);

        // Original unchanged
        $this->assertSame(['old' => 'value'], $request->getParams());

        // New instance has new params
        $this->assertSame(['new' => 'value'], $newRequest->getParams());
        $this->assertSame('test', $newRequest->getMethod());
    }

    public function testJsonSerialize(): void
    {
        $request = new Request('test/method', ['param' => 'value']);

        $json = json_encode($request);
        $decoded = json_decode($json, true);

        $this->assertSame('test/method', $decoded['method']);
        $this->assertSame(['param' => 'value'], $decoded['params']);
    }

    public function testJsonSerializeWithoutParams(): void
    {
        $request = new Request('test/method');

        $json = json_encode($request);
        $decoded = json_decode($json, true);

        $this->assertSame('test/method', $decoded['method']);
        $this->assertArrayNotHasKey('params', $decoded);
    }
}
