<?php

declare(strict_types=1);

namespace Tests\Types\Results;

use MCP\Types\Content\TextContent;
use MCP\Types\Results\CallToolResult;
use PHPUnit\Framework\TestCase;

class CallToolResultTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $result = new CallToolResult();

        $this->assertEmpty($result->getContent());
        $this->assertNull($result->getStructuredContent());
        $this->assertFalse($result->isError());
    }

    public function testConstructorWithContent(): void
    {
        $content = [new TextContent('Hello, world!')];
        $result = new CallToolResult(content: $content);

        $this->assertCount(1, $result->getContent());
        $this->assertInstanceOf(TextContent::class, $result->getContent()[0]);
        $this->assertEquals('Hello, world!', $result->getContent()[0]->getText());
    }

    public function testConstructorWithStructuredContent(): void
    {
        $structured = ['key' => 'value', 'number' => 42];
        $result = new CallToolResult(structuredContent: $structured);

        $this->assertEquals($structured, $result->getStructuredContent());
    }

    public function testConstructorWithIsError(): void
    {
        $result = new CallToolResult(isError: true);

        $this->assertTrue($result->isError());
    }

    public function testConstructorWithMeta(): void
    {
        $result = new CallToolResult(_meta: ['version' => '1.0']);

        $this->assertEquals(['version' => '1.0'], $result->getMeta());
    }

    public function testFromArrayWithMinimalData(): void
    {
        $result = CallToolResult::fromArray([]);

        $this->assertEmpty($result->getContent());
        $this->assertNull($result->getStructuredContent());
        $this->assertFalse($result->isError());
    }

    public function testFromArrayWithTextContent(): void
    {
        $result = CallToolResult::fromArray([
            'content' => [
                ['type' => 'text', 'text' => 'Tool output'],
            ],
        ]);

        $this->assertCount(1, $result->getContent());
        $this->assertEquals('text', $result->getContent()[0]->getType());
    }

    public function testFromArrayWithMultipleContent(): void
    {
        $result = CallToolResult::fromArray([
            'content' => [
                ['type' => 'text', 'text' => 'First output'],
                ['type' => 'text', 'text' => 'Second output'],
            ],
        ]);

        $this->assertCount(2, $result->getContent());
    }

    public function testFromArrayWithStructuredContent(): void
    {
        $result = CallToolResult::fromArray([
            'content' => [],
            'structuredContent' => ['result' => 'success', 'data' => [1, 2, 3]],
        ]);

        $this->assertEquals(['result' => 'success', 'data' => [1, 2, 3]], $result->getStructuredContent());
    }

    public function testFromArrayWithIsError(): void
    {
        $result = CallToolResult::fromArray([
            'content' => [['type' => 'text', 'text' => 'Error occurred']],
            'isError' => true,
        ]);

        $this->assertTrue($result->isError());
    }

    public function testFromArrayWithMeta(): void
    {
        $result = CallToolResult::fromArray([
            'content' => [],
            '_meta' => ['executionTime' => 150],
        ]);

        $this->assertEquals(['executionTime' => 150], $result->getMeta());
    }

    public function testFromArrayWithLegacyToolResult(): void
    {
        // Test backward compatibility with protocol version 2024-10-07
        $result = CallToolResult::fromArray([
            'toolResult' => ['key' => 'value'],
        ]);

        $this->assertEmpty($result->getContent());
        $this->assertEquals(['key' => 'value'], $result->getStructuredContent());
    }

    public function testFromArrayIgnoresNonArrayContent(): void
    {
        $result = CallToolResult::fromArray([
            'content' => 'not an array',
        ]);

        $this->assertEmpty($result->getContent());
    }

    public function testFromArrayIgnoresNonArrayStructuredContent(): void
    {
        $result = CallToolResult::fromArray([
            'content' => [],
            'structuredContent' => 'not an array',
        ]);

        $this->assertNull($result->getStructuredContent());
    }

    public function testFromArrayIgnoresNonBoolIsError(): void
    {
        $result = CallToolResult::fromArray([
            'content' => [],
            'isError' => 'true',
        ]);

        $this->assertFalse($result->isError());
    }

    public function testJsonSerializeMinimal(): void
    {
        $result = new CallToolResult();
        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('content', $json);
        $this->assertEmpty($json['content']);
        $this->assertArrayNotHasKey('structuredContent', $json);
        $this->assertArrayNotHasKey('isError', $json);
    }

    public function testJsonSerializeWithContent(): void
    {
        $result = new CallToolResult(
            content: [new TextContent('Output text')]
        );
        $json = $result->jsonSerialize();

        $this->assertCount(1, $json['content']);
        $this->assertEquals('text', $json['content'][0]['type']);
        $this->assertEquals('Output text', $json['content'][0]['text']);
    }

    public function testJsonSerializeWithStructuredContent(): void
    {
        $result = new CallToolResult(
            structuredContent: ['status' => 'ok']
        );
        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('structuredContent', $json);
        $this->assertEquals(['status' => 'ok'], $json['structuredContent']);
    }

    public function testJsonSerializeWithIsError(): void
    {
        $result = new CallToolResult(isError: true);
        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('isError', $json);
        $this->assertTrue($json['isError']);
    }

    public function testJsonSerializeOmitsIsErrorWhenFalse(): void
    {
        $result = new CallToolResult(isError: false);
        $json = $result->jsonSerialize();

        $this->assertArrayNotHasKey('isError', $json);
    }

    public function testJsonSerializeWithMeta(): void
    {
        $result = new CallToolResult(_meta: ['trace' => 'abc123']);
        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('_meta', $json);
        $this->assertEquals(['trace' => 'abc123'], $json['_meta']);
    }

    public function testRoundTrip(): void
    {
        $original = new CallToolResult(
            content: [new TextContent('Round trip test')],
            structuredContent: ['key' => 'value'],
            isError: false,
            _meta: ['id' => '123']
        );

        $json = $original->jsonSerialize();
        $restored = CallToolResult::fromArray($json);

        $this->assertCount(1, $restored->getContent());
        $this->assertEquals('Round trip test', $restored->getContent()[0]->getText());
        $this->assertEquals(['key' => 'value'], $restored->getStructuredContent());
        $this->assertFalse($restored->isError());
        $this->assertEquals(['id' => '123'], $restored->getMeta());
    }

    public function testFromArraySkipsInvalidContentItems(): void
    {
        $result = CallToolResult::fromArray([
            'content' => [
                ['type' => 'text', 'text' => 'Valid'],
                'invalid item', // Not an array, should be skipped
                null, // Should be skipped
            ],
        ]);

        $this->assertCount(1, $result->getContent());
    }
}

