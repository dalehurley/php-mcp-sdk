<?php

declare(strict_types=1);

namespace Tests\Types\Content;

use MCP\Types\Content\ImageContent;
use PHPUnit\Framework\TestCase;

class ImageContentTest extends TestCase
{
    private string $validBase64;

    protected function setUp(): void
    {
        // Create a valid base64 encoded string
        $this->validBase64 = base64_encode('fake image data');
    }

    public function testConstructor(): void
    {
        $content = new ImageContent($this->validBase64, 'image/png');

        $this->assertEquals('image', $content->getType());
        $this->assertEquals($this->validBase64, $content->getData());
        $this->assertEquals('image/png', $content->getMimeType());
    }

    public function testConstructorWithMeta(): void
    {
        $content = new ImageContent(
            $this->validBase64,
            'image/jpeg',
            ['width' => 800, 'height' => 600]
        );

        $this->assertEquals(['width' => 800, 'height' => 600], $content->getMeta());
    }

    public function testConstructorWithAdditionalProperties(): void
    {
        $content = new ImageContent(
            data: $this->validBase64,
            mimeType: 'image/png',
            _meta: null,
            additionalProperties: ['alt' => 'Image description']
        );

        $this->assertEquals(['alt' => 'Image description'], $content->getAdditionalProperties());
    }

    public function testConstructorThrowsForInvalidBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image data must be valid base64 encoded');

        // Invalid base64 characters
        new ImageContent('not valid base64!!!', 'image/png');
    }

    public function testFromArray(): void
    {
        $content = ImageContent::fromArray([
            'type' => 'image',
            'data' => $this->validBase64,
            'mimeType' => 'image/png',
        ]);

        $this->assertEquals('image', $content->getType());
        $this->assertEquals($this->validBase64, $content->getData());
        $this->assertEquals('image/png', $content->getMimeType());
    }

    public function testFromArrayWithMeta(): void
    {
        $content = ImageContent::fromArray([
            'type' => 'image',
            'data' => $this->validBase64,
            'mimeType' => 'image/jpeg',
            '_meta' => ['source' => 'camera'],
        ]);

        $this->assertEquals(['source' => 'camera'], $content->getMeta());
    }

    public function testFromArrayWithAdditionalProperties(): void
    {
        $content = ImageContent::fromArray([
            'type' => 'image',
            'data' => $this->validBase64,
            'mimeType' => 'image/png',
            'customProp' => 'value',
        ]);

        $this->assertEquals(['customProp' => 'value'], $content->getAdditionalProperties());
    }

    public function testFromArrayThrowsWithoutType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ImageContent must have type "image"');

        ImageContent::fromArray([
            'data' => $this->validBase64,
            'mimeType' => 'image/png',
        ]);
    }

    public function testFromArrayThrowsWithWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ImageContent must have type "image"');

        ImageContent::fromArray([
            'type' => 'text',
            'data' => $this->validBase64,
            'mimeType' => 'image/png',
        ]);
    }

    public function testFromArrayThrowsWithoutData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ImageContent must have a data property');

        ImageContent::fromArray([
            'type' => 'image',
            'mimeType' => 'image/png',
        ]);
    }

    public function testFromArrayThrowsWithNonStringData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ImageContent must have a data property');

        ImageContent::fromArray([
            'type' => 'image',
            'data' => 123,
            'mimeType' => 'image/png',
        ]);
    }

    public function testFromArrayThrowsWithoutMimeType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ImageContent must have a mimeType property');

        ImageContent::fromArray([
            'type' => 'image',
            'data' => $this->validBase64,
        ]);
    }

    public function testFromArrayThrowsWithNonStringMimeType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ImageContent must have a mimeType property');

        ImageContent::fromArray([
            'type' => 'image',
            'data' => $this->validBase64,
            'mimeType' => 123,
        ]);
    }

    public function testGetType(): void
    {
        $content = new ImageContent($this->validBase64, 'image/png');
        $this->assertEquals('image', $content->getType());
    }

    public function testGetData(): void
    {
        $content = new ImageContent($this->validBase64, 'image/png');
        $this->assertEquals($this->validBase64, $content->getData());
    }

    public function testGetMimeType(): void
    {
        $content = new ImageContent($this->validBase64, 'image/gif');
        $this->assertEquals('image/gif', $content->getMimeType());
    }

    public function testVariousMimeTypes(): void
    {
        $mimeTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];

        foreach ($mimeTypes as $mimeType) {
            $content = new ImageContent($this->validBase64, $mimeType);
            $this->assertEquals($mimeType, $content->getMimeType());
        }
    }

    public function testJsonSerialize(): void
    {
        $content = new ImageContent($this->validBase64, 'image/png');
        $json = $content->jsonSerialize();

        $this->assertEquals('image', $json['type']);
        $this->assertEquals($this->validBase64, $json['data']);
        $this->assertEquals('image/png', $json['mimeType']);
        $this->assertArrayNotHasKey('_meta', $json);
    }

    public function testJsonSerializeWithMeta(): void
    {
        $content = new ImageContent($this->validBase64, 'image/png', ['size' => 1024]);
        $json = $content->jsonSerialize();

        $this->assertArrayHasKey('_meta', $json);
        $this->assertEquals(['size' => 1024], $json['_meta']);
    }

    public function testJsonSerializeWithAdditionalProperties(): void
    {
        $content = new ImageContent(
            data: $this->validBase64,
            mimeType: 'image/png',
            _meta: null,
            additionalProperties: ['caption' => 'A test image']
        );
        $json = $content->jsonSerialize();

        $this->assertArrayHasKey('caption', $json);
        $this->assertEquals('A test image', $json['caption']);
    }

    public function testRoundTrip(): void
    {
        $original = new ImageContent(
            data: $this->validBase64,
            mimeType: 'image/png',
            _meta: ['id' => 'img-001'],
            additionalProperties: ['title' => 'Test Image']
        );

        $json = $original->jsonSerialize();
        $restored = ImageContent::fromArray($json);

        $this->assertEquals($original->getData(), $restored->getData());
        $this->assertEquals($original->getMimeType(), $restored->getMimeType());
        $this->assertEquals($original->getMeta(), $restored->getMeta());
        $this->assertEquals($original->getAdditionalProperties(), $restored->getAdditionalProperties());
    }

    public function testValidBase64WithPadding(): void
    {
        // Test with proper padding
        $data = base64_encode('test');  // Should be 'dGVzdA=='
        $content = new ImageContent($data, 'image/png');

        $this->assertEquals($data, $content->getData());
    }

    public function testValidBase64WithoutPadding(): void
    {
        // Some base64 strings don't need padding
        $data = base64_encode('tes'); // 'dGVz' - no padding needed
        $content = new ImageContent($data, 'image/png');

        $this->assertEquals($data, $content->getData());
    }
}

