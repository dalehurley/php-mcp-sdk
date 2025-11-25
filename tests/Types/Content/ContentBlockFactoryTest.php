<?php

declare(strict_types=1);

namespace Tests\Types\Content;

use MCP\Types\Content\AudioContent;
use MCP\Types\Content\ContentBlockFactory;
use MCP\Types\Content\EmbeddedResource;
use MCP\Types\Content\ImageContent;
use MCP\Types\Content\ResourceLink;
use MCP\Types\Content\TextContent;
use PHPUnit\Framework\TestCase;

class ContentBlockFactoryTest extends TestCase
{
    public function testFromArrayCreatesTextContent(): void
    {
        $content = ContentBlockFactory::fromArray([
            'type' => 'text',
            'text' => 'Hello, world!',
        ]);

        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertEquals('Hello, world!', $content->getText());
    }

    public function testFromArrayCreatesImageContent(): void
    {
        $content = ContentBlockFactory::fromArray([
            'type' => 'image',
            'data' => base64_encode('fake image'),
            'mimeType' => 'image/png',
        ]);

        $this->assertInstanceOf(ImageContent::class, $content);
        $this->assertEquals('image/png', $content->getMimeType());
    }

    public function testFromArrayCreatesAudioContent(): void
    {
        $content = ContentBlockFactory::fromArray([
            'type' => 'audio',
            'data' => base64_encode('fake audio'),
            'mimeType' => 'audio/mp3',
        ]);

        $this->assertInstanceOf(AudioContent::class, $content);
        $this->assertEquals('audio/mp3', $content->getMimeType());
    }

    public function testFromArrayCreatesEmbeddedResource(): void
    {
        $content = ContentBlockFactory::fromArray([
            'type' => 'resource',
            'resource' => [
                'uri' => 'file:///test.txt',
                'text' => 'Resource content',
            ],
        ]);

        $this->assertInstanceOf(EmbeddedResource::class, $content);
    }

    public function testFromArrayCreatesResourceLink(): void
    {
        $content = ContentBlockFactory::fromArray([
            'type' => 'resource_link',
            'name' => 'linked_file',
            'uri' => 'file:///linked.txt',
        ]);

        $this->assertInstanceOf(ResourceLink::class, $content);
    }

    public function testFromArrayThrowsForMissingType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ContentBlock must have a type property');

        ContentBlockFactory::fromArray([
            'text' => 'No type',
        ]);
    }

    public function testFromArrayThrowsForNonStringType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ContentBlock must have a type property');

        ContentBlockFactory::fromArray([
            'type' => 123,
            'text' => 'Wrong type',
        ]);
    }

    public function testFromArrayThrowsForUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown content block type: unknown');

        ContentBlockFactory::fromArray([
            'type' => 'unknown',
        ]);
    }

    public function testFromArrayMultiple(): void
    {
        $contents = ContentBlockFactory::fromArrayMultiple([
            ['type' => 'text', 'text' => 'First'],
            ['type' => 'text', 'text' => 'Second'],
            ['type' => 'image', 'data' => base64_encode('img'), 'mimeType' => 'image/png'],
        ]);

        $this->assertCount(3, $contents);
        $this->assertInstanceOf(TextContent::class, $contents[0]);
        $this->assertInstanceOf(TextContent::class, $contents[1]);
        $this->assertInstanceOf(ImageContent::class, $contents[2]);
    }

    public function testFromArrayMultipleWithEmptyArray(): void
    {
        $contents = ContentBlockFactory::fromArrayMultiple([]);

        $this->assertEmpty($contents);
    }

    public function testIsValidWithValidTextContent(): void
    {
        $this->assertTrue(ContentBlockFactory::isValid([
            'type' => 'text',
            'text' => 'Valid',
        ]));
    }

    public function testIsValidWithValidImageContent(): void
    {
        $this->assertTrue(ContentBlockFactory::isValid([
            'type' => 'image',
            'data' => 'base64data',
            'mimeType' => 'image/png',
        ]));
    }

    public function testIsValidWithValidAudioContent(): void
    {
        $this->assertTrue(ContentBlockFactory::isValid([
            'type' => 'audio',
            'data' => 'base64data',
            'mimeType' => 'audio/mp3',
        ]));
    }

    public function testIsValidWithValidResourceContent(): void
    {
        $this->assertTrue(ContentBlockFactory::isValid([
            'type' => 'resource',
            'resource' => [],
        ]));
    }

    public function testIsValidWithValidResourceLinkContent(): void
    {
        $this->assertTrue(ContentBlockFactory::isValid([
            'type' => 'resource_link',
            'name' => 'test_resource',
            'uri' => 'file:///test',
        ]));
    }

    public function testIsValidWithInvalidType(): void
    {
        $this->assertFalse(ContentBlockFactory::isValid([
            'type' => 'invalid',
        ]));
    }

    public function testIsValidWithMissingType(): void
    {
        $this->assertFalse(ContentBlockFactory::isValid([
            'text' => 'No type',
        ]));
    }

    public function testIsValidWithNonArrayValue(): void
    {
        $this->assertFalse(ContentBlockFactory::isValid('not an array'));
        $this->assertFalse(ContentBlockFactory::isValid(123));
        $this->assertFalse(ContentBlockFactory::isValid(null));
    }

    public function testIsValidWithNonStringType(): void
    {
        $this->assertFalse(ContentBlockFactory::isValid([
            'type' => 123,
        ]));
    }

    public function testAllContentTypesRecognized(): void
    {
        $types = ['text', 'image', 'audio', 'resource', 'resource_link'];

        foreach ($types as $type) {
            $this->assertTrue(
                ContentBlockFactory::isValid(['type' => $type]),
                "Type '$type' should be valid"
            );
        }
    }
}

