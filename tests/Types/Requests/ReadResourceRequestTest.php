<?php

declare(strict_types=1);

namespace Tests\Types\Requests;

use MCP\Types\Requests\ReadResourceRequest;
use PHPUnit\Framework\TestCase;

class ReadResourceRequestTest extends TestCase
{
    public function testMethodConstant(): void
    {
        $this->assertEquals('resources/read', ReadResourceRequest::METHOD);
    }

    public function testCreate(): void
    {
        $request = ReadResourceRequest::create('file:///path/to/resource');

        $this->assertEquals('file:///path/to/resource', $request->getUri());
        $this->assertEquals(ReadResourceRequest::METHOD, $request->getMethod());
    }

    public function testCreateWithHttpUri(): void
    {
        $request = ReadResourceRequest::create('https://example.com/resource');

        $this->assertEquals('https://example.com/resource', $request->getUri());
    }

    public function testCreateWithCustomScheme(): void
    {
        $request = ReadResourceRequest::create('custom://my-resource/path');

        $this->assertEquals('custom://my-resource/path', $request->getUri());
    }

    public function testConstructorWithParams(): void
    {
        $request = new ReadResourceRequest(['uri' => 'data:text/plain,Hello']);

        $this->assertEquals('data:text/plain,Hello', $request->getUri());
    }

    public function testConstructorWithMethodAndParams(): void
    {
        $request = new ReadResourceRequest(ReadResourceRequest::METHOD, ['uri' => 'file:///test']);

        $this->assertEquals('file:///test', $request->getUri());
    }

    public function testConstructorWithDefaultMethod(): void
    {
        $request = new ReadResourceRequest(null, ['uri' => 'file:///test']);

        $this->assertEquals(ReadResourceRequest::METHOD, $request->getMethod());
        $this->assertEquals('file:///test', $request->getUri());
    }

    public function testConstructorThrowsForInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid method for ReadResourceRequest: expected 'resources/read', got 'invalid/method'");

        new ReadResourceRequest('invalid/method', ['uri' => 'file:///test']);
    }

    public function testGetUriWithNoParams(): void
    {
        $request = new ReadResourceRequest([]);
        $this->assertNull($request->getUri());
    }

    public function testGetUriWithNonStringUri(): void
    {
        $request = new ReadResourceRequest(['uri' => 123]);
        $this->assertNull($request->getUri());
    }

    public function testGetUriWithArrayUri(): void
    {
        $request = new ReadResourceRequest(['uri' => ['not', 'a', 'string']]);
        $this->assertNull($request->getUri());
    }

    public function testIsValidWithValidRequest(): void
    {
        $valid = [
            'method' => 'resources/read',
            'params' => ['uri' => 'file:///path/to/file'],
        ];

        $this->assertTrue(ReadResourceRequest::isValid($valid));
    }

    public function testIsValidWithWrongMethod(): void
    {
        $invalid = [
            'method' => 'wrong/method',
            'params' => ['uri' => 'file:///path/to/file'],
        ];

        $this->assertFalse(ReadResourceRequest::isValid($invalid));
    }

    public function testIsValidWithMissingParams(): void
    {
        $invalid = [
            'method' => 'resources/read',
        ];

        $this->assertFalse(ReadResourceRequest::isValid($invalid));
    }

    public function testIsValidWithNullParams(): void
    {
        $invalid = [
            'method' => 'resources/read',
            'params' => null,
        ];

        $this->assertFalse(ReadResourceRequest::isValid($invalid));
    }

    public function testIsValidWithMissingUri(): void
    {
        $invalid = [
            'method' => 'resources/read',
            'params' => [],
        ];

        $this->assertFalse(ReadResourceRequest::isValid($invalid));
    }

    public function testIsValidWithNonArrayValue(): void
    {
        $this->assertFalse(ReadResourceRequest::isValid('not an array'));
        $this->assertFalse(ReadResourceRequest::isValid(123));
        $this->assertFalse(ReadResourceRequest::isValid(null));
    }

    public function testJsonSerialize(): void
    {
        $request = ReadResourceRequest::create('file:///test/resource');
        $json = $request->jsonSerialize();

        $this->assertEquals('resources/read', $json['method']);
        $this->assertArrayHasKey('params', $json);
        $this->assertEquals('file:///test/resource', $json['params']['uri']);
    }

    public function testUriWithQueryParams(): void
    {
        $request = ReadResourceRequest::create('https://api.example.com/resource?id=123&format=json');

        $this->assertEquals('https://api.example.com/resource?id=123&format=json', $request->getUri());
    }

    public function testUriWithFragment(): void
    {
        $request = ReadResourceRequest::create('file:///path/to/file#section');

        $this->assertEquals('file:///path/to/file#section', $request->getUri());
    }

    public function testUriWithSpecialCharacters(): void
    {
        $request = ReadResourceRequest::create('file:///path/to/file%20with%20spaces');

        $this->assertEquals('file:///path/to/file%20with%20spaces', $request->getUri());
    }
}

