<?php

declare(strict_types=1);

namespace Tests\Types\Content;

use MCP\Types\Content\TextContent;
use PHPUnit\Framework\TestCase;

class TextContentTest extends TestCase
{
    public function testConstructor(): void
    {
        $content = new TextContent('Hello, world!');

        $this->assertEquals('text', $content->getType());
        $this->assertEquals('Hello, world!', $content->getText());
        $this->assertNull($content->getMeta());
        $this->assertEmpty($content->getAdditionalProperties());
    }

    public function testConstructorWithMeta(): void
    {
        $content = new TextContent('Test text', ['key' => 'value']);

        $this->assertEquals(['key' => 'value'], $content->getMeta());
    }

    public function testConstructorWithAdditionalProperties(): void
    {
        $content = new TextContent(
            text: 'Test',
            _meta: null,
            additionalProperties: ['custom' => 'prop']
        );

        $this->assertEquals(['custom' => 'prop'], $content->getAdditionalProperties());
    }

    public function testFromArray(): void
    {
        $content = TextContent::fromArray([
            'type' => 'text',
            'text' => 'From array text',
        ]);

        $this->assertEquals('text', $content->getType());
        $this->assertEquals('From array text', $content->getText());
    }

    public function testFromArrayWithMeta(): void
    {
        $content = TextContent::fromArray([
            'type' => 'text',
            'text' => 'With meta',
            '_meta' => ['version' => 1],
        ]);

        $this->assertEquals(['version' => 1], $content->getMeta());
    }

    public function testFromArrayWithAdditionalProperties(): void
    {
        $content = TextContent::fromArray([
            'type' => 'text',
            'text' => 'Test',
            'customProp' => 'customValue',
        ]);

        $this->assertEquals(['customProp' => 'customValue'], $content->getAdditionalProperties());
    }

    public function testFromArrayThrowsWithoutType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TextContent must have type "text"');

        TextContent::fromArray(['text' => 'test']);
    }

    public function testFromArrayThrowsWithWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TextContent must have type "text"');

        TextContent::fromArray(['type' => 'image', 'text' => 'test']);
    }

    public function testFromArrayThrowsWithoutText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TextContent must have a text property');

        TextContent::fromArray(['type' => 'text']);
    }

    public function testFromArrayThrowsWithNonStringText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TextContent must have a text property');

        TextContent::fromArray(['type' => 'text', 'text' => 123]);
    }

    public function testGetType(): void
    {
        $content = new TextContent('test');
        $this->assertEquals('text', $content->getType());
    }

    public function testGetText(): void
    {
        $content = new TextContent('Specific text');
        $this->assertEquals('Specific text', $content->getText());
    }

    public function testGetTextWithEmptyString(): void
    {
        $content = new TextContent('');
        $this->assertEquals('', $content->getText());
    }

    public function testGetTextWithSpecialCharacters(): void
    {
        $text = "Special chars: \n\t\r<>&\"'";
        $content = new TextContent($text);
        $this->assertEquals($text, $content->getText());
    }

    public function testGetTextWithUnicode(): void
    {
        $text = '日本語テスト 🚀 émojis';
        $content = new TextContent($text);
        $this->assertEquals($text, $content->getText());
    }

    public function testJsonSerialize(): void
    {
        $content = new TextContent('Serialized text');
        $json = $content->jsonSerialize();

        $this->assertEquals('text', $json['type']);
        $this->assertEquals('Serialized text', $json['text']);
        $this->assertArrayNotHasKey('_meta', $json);
    }

    public function testJsonSerializeWithMeta(): void
    {
        $content = new TextContent('Test', ['key' => 'value']);
        $json = $content->jsonSerialize();

        $this->assertArrayHasKey('_meta', $json);
        $this->assertEquals(['key' => 'value'], $json['_meta']);
    }

    public function testJsonSerializeWithAdditionalProperties(): void
    {
        $content = new TextContent(
            text: 'Test',
            _meta: null,
            additionalProperties: ['extra' => 'data']
        );
        $json = $content->jsonSerialize();

        $this->assertArrayHasKey('extra', $json);
        $this->assertEquals('data', $json['extra']);
    }

    public function testRoundTrip(): void
    {
        $original = new TextContent(
            text: 'Round trip text',
            _meta: ['id' => 123],
            additionalProperties: ['custom' => 'value']
        );

        $json = $original->jsonSerialize();
        $restored = TextContent::fromArray($json);

        $this->assertEquals($original->getText(), $restored->getText());
        $this->assertEquals($original->getMeta(), $restored->getMeta());
        $this->assertEquals($original->getAdditionalProperties(), $restored->getAdditionalProperties());
    }

    public function testLongText(): void
    {
        $longText = str_repeat('A', 100000);
        $content = new TextContent($longText);

        $this->assertEquals($longText, $content->getText());
        $this->assertEquals(100000, strlen($content->getText()));
    }
}

