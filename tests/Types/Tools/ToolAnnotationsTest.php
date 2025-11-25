<?php

declare(strict_types=1);

namespace Tests\Types\Tools;

use MCP\Types\Tools\ToolAnnotations;
use PHPUnit\Framework\TestCase;

class ToolAnnotationsTest extends TestCase
{
    public function testConstructorWithNoParameters(): void
    {
        $annotations = new ToolAnnotations();

        $this->assertNull($annotations->getTitle());
        $this->assertNull($annotations->getReadOnlyHint());
        $this->assertNull($annotations->getDestructiveHint());
        $this->assertNull($annotations->getIdempotentHint());
        $this->assertNull($annotations->getOpenWorldHint());
    }

    public function testConstructorWithAllParameters(): void
    {
        $annotations = new ToolAnnotations(
            title: 'Test Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
            additionalProperties: ['custom' => 'value']
        );

        $this->assertEquals('Test Tool', $annotations->getTitle());
        $this->assertTrue($annotations->getReadOnlyHint());
        $this->assertFalse($annotations->getDestructiveHint());
        $this->assertTrue($annotations->getIdempotentHint());
        $this->assertFalse($annotations->getOpenWorldHint());
        $this->assertEquals(['custom' => 'value'], $annotations->getAdditionalProperties());
    }

    public function testFromArrayWithMinimalData(): void
    {
        $annotations = ToolAnnotations::fromArray([]);

        $this->assertNull($annotations->getTitle());
        $this->assertNull($annotations->getReadOnlyHint());
    }

    public function testFromArrayWithFullData(): void
    {
        $annotations = ToolAnnotations::fromArray([
            'title' => 'From Array Tool',
            'readOnlyHint' => true,
            'destructiveHint' => true,
            'idempotentHint' => false,
            'openWorldHint' => true,
            'customProperty' => 'customValue',
        ]);

        $this->assertEquals('From Array Tool', $annotations->getTitle());
        $this->assertTrue($annotations->getReadOnlyHint());
        $this->assertTrue($annotations->getDestructiveHint());
        $this->assertFalse($annotations->getIdempotentHint());
        $this->assertTrue($annotations->getOpenWorldHint());
        $this->assertEquals(['customProperty' => 'customValue'], $annotations->getAdditionalProperties());
    }

    public function testFromArrayIgnoresInvalidTitleType(): void
    {
        $annotations = ToolAnnotations::fromArray([
            'title' => 123, // Should be string
        ]);

        $this->assertNull($annotations->getTitle());
    }

    public function testFromArrayIgnoresInvalidReadOnlyHintType(): void
    {
        $annotations = ToolAnnotations::fromArray([
            'readOnlyHint' => 'true', // Should be boolean
        ]);

        $this->assertNull($annotations->getReadOnlyHint());
    }

    public function testFromArrayIgnoresInvalidDestructiveHintType(): void
    {
        $annotations = ToolAnnotations::fromArray([
            'destructiveHint' => 1, // Should be boolean
        ]);

        $this->assertNull($annotations->getDestructiveHint());
    }

    public function testFromArrayIgnoresInvalidIdempotentHintType(): void
    {
        $annotations = ToolAnnotations::fromArray([
            'idempotentHint' => 'yes', // Should be boolean
        ]);

        $this->assertNull($annotations->getIdempotentHint());
    }

    public function testFromArrayIgnoresInvalidOpenWorldHintType(): void
    {
        $annotations = ToolAnnotations::fromArray([
            'openWorldHint' => 0, // Should be boolean
        ]);

        $this->assertNull($annotations->getOpenWorldHint());
    }

    public function testGetTitle(): void
    {
        $annotations = new ToolAnnotations(title: 'My Tool Title');
        $this->assertEquals('My Tool Title', $annotations->getTitle());
    }

    public function testGetReadOnlyHint(): void
    {
        $annotationsTrue = new ToolAnnotations(readOnlyHint: true);
        $annotationsFalse = new ToolAnnotations(readOnlyHint: false);

        $this->assertTrue($annotationsTrue->getReadOnlyHint());
        $this->assertFalse($annotationsFalse->getReadOnlyHint());
    }

    public function testGetDestructiveHint(): void
    {
        $annotationsTrue = new ToolAnnotations(destructiveHint: true);
        $annotationsFalse = new ToolAnnotations(destructiveHint: false);

        $this->assertTrue($annotationsTrue->getDestructiveHint());
        $this->assertFalse($annotationsFalse->getDestructiveHint());
    }

    public function testGetIdempotentHint(): void
    {
        $annotationsTrue = new ToolAnnotations(idempotentHint: true);
        $annotationsFalse = new ToolAnnotations(idempotentHint: false);

        $this->assertTrue($annotationsTrue->getIdempotentHint());
        $this->assertFalse($annotationsFalse->getIdempotentHint());
    }

    public function testGetOpenWorldHint(): void
    {
        $annotationsTrue = new ToolAnnotations(openWorldHint: true);
        $annotationsFalse = new ToolAnnotations(openWorldHint: false);

        $this->assertTrue($annotationsTrue->getOpenWorldHint());
        $this->assertFalse($annotationsFalse->getOpenWorldHint());
    }

    public function testGetAdditionalProperties(): void
    {
        $annotations = new ToolAnnotations(
            additionalProperties: [
                'key1' => 'value1',
                'key2' => 42,
                'key3' => true,
            ]
        );

        $props = $annotations->getAdditionalProperties();
        $this->assertCount(3, $props);
        $this->assertEquals('value1', $props['key1']);
        $this->assertEquals(42, $props['key2']);
        $this->assertTrue($props['key3']);
    }

    public function testJsonSerializeEmpty(): void
    {
        $annotations = new ToolAnnotations();
        $json = $annotations->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertEmpty($json);
    }

    public function testJsonSerializeMinimal(): void
    {
        $annotations = new ToolAnnotations(title: 'Only Title');
        $json = $annotations->jsonSerialize();

        $this->assertEquals(['title' => 'Only Title'], $json);
    }

    public function testJsonSerializeFull(): void
    {
        $annotations = new ToolAnnotations(
            title: 'Full Annotation',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        );

        $json = $annotations->jsonSerialize();

        $this->assertEquals('Full Annotation', $json['title']);
        $this->assertTrue($json['readOnlyHint']);
        $this->assertFalse($json['destructiveHint']);
        $this->assertTrue($json['idempotentHint']);
        $this->assertFalse($json['openWorldHint']);
    }

    public function testJsonSerializeWithAdditionalProperties(): void
    {
        $annotations = new ToolAnnotations(
            title: 'With Extras',
            additionalProperties: ['extra1' => 'value1', 'extra2' => 'value2']
        );

        $json = $annotations->jsonSerialize();

        $this->assertEquals('With Extras', $json['title']);
        $this->assertEquals('value1', $json['extra1']);
        $this->assertEquals('value2', $json['extra2']);
    }

    public function testJsonSerializeOmitsNullValues(): void
    {
        $annotations = new ToolAnnotations(readOnlyHint: true);
        $json = $annotations->jsonSerialize();

        $this->assertArrayHasKey('readOnlyHint', $json);
        $this->assertArrayNotHasKey('title', $json);
        $this->assertArrayNotHasKey('destructiveHint', $json);
        $this->assertArrayNotHasKey('idempotentHint', $json);
        $this->assertArrayNotHasKey('openWorldHint', $json);
    }

    public function testFromArrayPreservesUnknownProperties(): void
    {
        $annotations = ToolAnnotations::fromArray([
            'title' => 'Tool',
            'unknownProp1' => 'value1',
            'unknownProp2' => ['nested' => 'data'],
        ]);

        $props = $annotations->getAdditionalProperties();
        $this->assertArrayHasKey('unknownProp1', $props);
        $this->assertArrayHasKey('unknownProp2', $props);
        $this->assertEquals(['nested' => 'data'], $props['unknownProp2']);
    }

    public function testRoundTrip(): void
    {
        $original = new ToolAnnotations(
            title: 'Round Trip Tool',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
            additionalProperties: ['custom' => 'data']
        );

        $json = $original->jsonSerialize();
        $restored = ToolAnnotations::fromArray($json);

        $this->assertEquals($original->getTitle(), $restored->getTitle());
        $this->assertEquals($original->getReadOnlyHint(), $restored->getReadOnlyHint());
        $this->assertEquals($original->getDestructiveHint(), $restored->getDestructiveHint());
        $this->assertEquals($original->getIdempotentHint(), $restored->getIdempotentHint());
        $this->assertEquals($original->getOpenWorldHint(), $restored->getOpenWorldHint());
        $this->assertEquals($original->getAdditionalProperties(), $restored->getAdditionalProperties());
    }
}

