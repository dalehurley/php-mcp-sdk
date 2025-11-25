<?php

declare(strict_types=1);

namespace Tests\Types\Requests;

use MCP\Types\Requests\GetPromptRequest;
use PHPUnit\Framework\TestCase;

class GetPromptRequestTest extends TestCase
{
    public function testMethodConstant(): void
    {
        $this->assertEquals('prompts/get', GetPromptRequest::METHOD);
    }

    public function testCreateWithNameOnly(): void
    {
        $request = GetPromptRequest::create('my_prompt');

        $this->assertEquals('my_prompt', $request->getName());
        $this->assertNull($request->getArguments());
        $this->assertEquals(GetPromptRequest::METHOD, $request->getMethod());
    }

    public function testCreateWithArguments(): void
    {
        $request = GetPromptRequest::create('my_prompt', ['arg1' => 'value1', 'arg2' => 'value2']);

        $this->assertEquals('my_prompt', $request->getName());
        $this->assertEquals(['arg1' => 'value1', 'arg2' => 'value2'], $request->getArguments());
    }

    public function testCreateWithEmptyArguments(): void
    {
        $request = GetPromptRequest::create('my_prompt', []);

        $this->assertEquals('my_prompt', $request->getName());
        $this->assertEquals([], $request->getArguments());
    }

    public function testConstructorWithParams(): void
    {
        $request = new GetPromptRequest(['name' => 'prompt_from_constructor', 'arguments' => ['key' => 'value']]);

        $this->assertEquals('prompt_from_constructor', $request->getName());
        $this->assertEquals(['key' => 'value'], $request->getArguments());
    }

    public function testConstructorWithMethodAndParams(): void
    {
        $request = new GetPromptRequest(GetPromptRequest::METHOD, ['name' => 'prompt_name']);

        $this->assertEquals('prompt_name', $request->getName());
    }

    public function testConstructorWithDefaultMethod(): void
    {
        $request = new GetPromptRequest(null, ['name' => 'prompt_name']);

        $this->assertEquals(GetPromptRequest::METHOD, $request->getMethod());
        $this->assertEquals('prompt_name', $request->getName());
    }

    public function testConstructorThrowsForInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid method for GetPromptRequest: expected 'prompts/get', got 'invalid/method'");

        new GetPromptRequest('invalid/method', ['name' => 'prompt_name']);
    }

    public function testGetNameWithNoParams(): void
    {
        $request = new GetPromptRequest([]);
        $this->assertNull($request->getName());
    }

    public function testGetNameWithNonStringName(): void
    {
        $request = new GetPromptRequest(['name' => 123]);
        $this->assertNull($request->getName());
    }

    public function testGetArgumentsWithNoParams(): void
    {
        $request = new GetPromptRequest(['name' => 'prompt']);
        $this->assertNull($request->getArguments());
    }

    public function testGetArgumentsFiltersNonStringValues(): void
    {
        // GetPromptRequest arguments should only contain string values
        $request = new GetPromptRequest([
            'name' => 'prompt',
            'arguments' => [
                'valid_string' => 'value',
                'invalid_int' => 42,
                'another_valid' => 'another_value',
            ],
        ]);

        $args = $request->getArguments();
        $this->assertCount(2, $args);
        $this->assertEquals('value', $args['valid_string']);
        $this->assertEquals('another_value', $args['another_valid']);
        $this->assertArrayNotHasKey('invalid_int', $args);
    }

    public function testGetArgumentsFiltersNonStringKeys(): void
    {
        $request = new GetPromptRequest([
            'name' => 'prompt',
            'arguments' => [
                'valid_key' => 'value',
                0 => 'numeric_key_value', // Numeric key should be filtered
            ],
        ]);

        $args = $request->getArguments();
        $this->assertCount(1, $args);
        $this->assertEquals('value', $args['valid_key']);
    }

    public function testGetArgumentsWithNonArrayArguments(): void
    {
        $request = new GetPromptRequest(['name' => 'prompt', 'arguments' => 'not_array']);
        $this->assertNull($request->getArguments());
    }

    public function testIsValidWithValidRequest(): void
    {
        $valid = [
            'method' => 'prompts/get',
            'params' => ['name' => 'my_prompt'],
        ];

        $this->assertTrue(GetPromptRequest::isValid($valid));
    }

    public function testIsValidWithArguments(): void
    {
        $valid = [
            'method' => 'prompts/get',
            'params' => [
                'name' => 'my_prompt',
                'arguments' => ['arg1' => 'value1'],
            ],
        ];

        $this->assertTrue(GetPromptRequest::isValid($valid));
    }

    public function testIsValidWithWrongMethod(): void
    {
        $invalid = [
            'method' => 'wrong/method',
            'params' => ['name' => 'my_prompt'],
        ];

        $this->assertFalse(GetPromptRequest::isValid($invalid));
    }

    public function testIsValidWithMissingParams(): void
    {
        $invalid = [
            'method' => 'prompts/get',
        ];

        $this->assertFalse(GetPromptRequest::isValid($invalid));
    }

    public function testIsValidWithNullParams(): void
    {
        $invalid = [
            'method' => 'prompts/get',
            'params' => null,
        ];

        $this->assertFalse(GetPromptRequest::isValid($invalid));
    }

    public function testIsValidWithMissingName(): void
    {
        $invalid = [
            'method' => 'prompts/get',
            'params' => ['arguments' => ['key' => 'value']],
        ];

        $this->assertFalse(GetPromptRequest::isValid($invalid));
    }

    public function testIsValidWithNonArrayValue(): void
    {
        $this->assertFalse(GetPromptRequest::isValid('not an array'));
        $this->assertFalse(GetPromptRequest::isValid(123));
        $this->assertFalse(GetPromptRequest::isValid(null));
    }

    public function testJsonSerialize(): void
    {
        $request = GetPromptRequest::create('test_prompt', ['lang' => 'en']);
        $json = $request->jsonSerialize();

        $this->assertEquals('prompts/get', $json['method']);
        $this->assertArrayHasKey('params', $json);
        $this->assertEquals('test_prompt', $json['params']['name']);
        $this->assertEquals(['lang' => 'en'], $json['params']['arguments']);
    }
}

