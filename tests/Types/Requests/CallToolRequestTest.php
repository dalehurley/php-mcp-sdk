<?php

declare(strict_types=1);

namespace Tests\Types\Requests;

use MCP\Types\Requests\CallToolRequest;
use PHPUnit\Framework\TestCase;

class CallToolRequestTest extends TestCase
{
    public function testMethodConstant(): void
    {
        $this->assertEquals('tools/call', CallToolRequest::METHOD);
    }

    public function testCreateWithNameOnly(): void
    {
        $request = CallToolRequest::create('my_tool');

        $this->assertEquals('my_tool', $request->getName());
        $this->assertNull($request->getArguments());
        $this->assertEquals(CallToolRequest::METHOD, $request->getMethod());
    }

    public function testCreateWithArguments(): void
    {
        $request = CallToolRequest::create('my_tool', ['arg1' => 'value1', 'arg2' => 42]);

        $this->assertEquals('my_tool', $request->getName());
        $this->assertEquals(['arg1' => 'value1', 'arg2' => 42], $request->getArguments());
    }

    public function testCreateWithEmptyArguments(): void
    {
        $request = CallToolRequest::create('my_tool', []);

        $this->assertEquals('my_tool', $request->getName());
        $this->assertEquals([], $request->getArguments());
    }

    public function testConstructorWithParams(): void
    {
        $request = new CallToolRequest(['name' => 'tool_from_constructor', 'arguments' => ['key' => 'value']]);

        $this->assertEquals('tool_from_constructor', $request->getName());
        $this->assertEquals(['key' => 'value'], $request->getArguments());
    }

    public function testConstructorWithMethodAndParams(): void
    {
        $request = new CallToolRequest(CallToolRequest::METHOD, ['name' => 'tool_name']);

        $this->assertEquals('tool_name', $request->getName());
    }

    public function testConstructorWithDefaultMethod(): void
    {
        $request = new CallToolRequest(null, ['name' => 'tool_name']);

        $this->assertEquals(CallToolRequest::METHOD, $request->getMethod());
        $this->assertEquals('tool_name', $request->getName());
    }

    public function testConstructorThrowsForInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid method for CallToolRequest: expected 'tools/call', got 'invalid/method'");

        new CallToolRequest('invalid/method', ['name' => 'tool_name']);
    }

    public function testGetNameWithNoParams(): void
    {
        $request = new CallToolRequest([]);
        $this->assertNull($request->getName());
    }

    public function testGetNameWithNonStringName(): void
    {
        $request = new CallToolRequest(['name' => 123]);
        $this->assertNull($request->getName());
    }

    public function testGetArgumentsWithNoParams(): void
    {
        $request = new CallToolRequest(['name' => 'tool']);
        $this->assertNull($request->getArguments());
    }

    public function testGetArgumentsWithNonArrayArguments(): void
    {
        $request = new CallToolRequest(['name' => 'tool', 'arguments' => 'not_array']);
        $this->assertNull($request->getArguments());
    }

    public function testIsValidWithValidRequest(): void
    {
        $valid = [
            'method' => 'tools/call',
            'params' => ['name' => 'my_tool'],
        ];

        $this->assertTrue(CallToolRequest::isValid($valid));
    }

    public function testIsValidWithArguments(): void
    {
        $valid = [
            'method' => 'tools/call',
            'params' => [
                'name' => 'my_tool',
                'arguments' => ['arg1' => 'value1'],
            ],
        ];

        $this->assertTrue(CallToolRequest::isValid($valid));
    }

    public function testIsValidWithWrongMethod(): void
    {
        $invalid = [
            'method' => 'wrong/method',
            'params' => ['name' => 'my_tool'],
        ];

        $this->assertFalse(CallToolRequest::isValid($invalid));
    }

    public function testIsValidWithMissingParams(): void
    {
        $invalid = [
            'method' => 'tools/call',
        ];

        $this->assertFalse(CallToolRequest::isValid($invalid));
    }

    public function testIsValidWithNullParams(): void
    {
        $invalid = [
            'method' => 'tools/call',
            'params' => null,
        ];

        $this->assertFalse(CallToolRequest::isValid($invalid));
    }

    public function testIsValidWithMissingName(): void
    {
        $invalid = [
            'method' => 'tools/call',
            'params' => ['arguments' => []],
        ];

        $this->assertFalse(CallToolRequest::isValid($invalid));
    }

    public function testIsValidWithNonArrayValue(): void
    {
        $this->assertFalse(CallToolRequest::isValid('not an array'));
        $this->assertFalse(CallToolRequest::isValid(123));
        $this->assertFalse(CallToolRequest::isValid(null));
    }

    public function testJsonSerialize(): void
    {
        $request = CallToolRequest::create('test_tool', ['input' => 'data']);
        $json = $request->jsonSerialize();

        $this->assertEquals('tools/call', $json['method']);
        $this->assertArrayHasKey('params', $json);
        $this->assertEquals('test_tool', $json['params']['name']);
        $this->assertEquals(['input' => 'data'], $json['params']['arguments']);
    }

    public function testComplexArguments(): void
    {
        $complexArgs = [
            'string' => 'value',
            'number' => 42,
            'float' => 3.14,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => [
                'key' => 'value',
                'deep' => ['deeper' => 'value'],
            ],
        ];

        $request = CallToolRequest::create('complex_tool', $complexArgs);

        $this->assertEquals($complexArgs, $request->getArguments());
    }
}

