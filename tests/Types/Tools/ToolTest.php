<?php

declare(strict_types=1);

namespace Tests\Types\Tools;

use MCP\Types\Tools\Tool;
use MCP\Types\Tools\ToolAnnotations;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    public function testConstructorWithValidInputSchema(): void
    {
        $tool = new Tool(
            name: 'test_tool',
            inputSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]
        );

        $this->assertEquals('test_tool', $tool->getName());
        $this->assertEquals(['type' => 'object', 'properties' => ['name' => ['type' => 'string']]], $tool->getInputSchema());
    }

    public function testConstructorWithAllParameters(): void
    {
        $annotations = new ToolAnnotations(
            title: 'Test Tool Title',
            readOnlyHint: true
        );

        $tool = new Tool(
            name: 'full_tool',
            inputSchema: ['type' => 'object', 'properties' => []],
            title: 'Full Tool',
            description: 'A fully configured tool',
            outputSchema: ['type' => 'object', 'properties' => ['result' => ['type' => 'string']]],
            annotations: $annotations,
            _meta: ['version' => '1.0'],
            additionalProperties: ['custom' => 'value']
        );

        $this->assertEquals('full_tool', $tool->getName());
        $this->assertEquals('Full Tool', $tool->getTitle());
        $this->assertEquals('A fully configured tool', $tool->getDescription());
        $this->assertNotNull($tool->getOutputSchema());
        $this->assertNotNull($tool->getAnnotations());
        $this->assertEquals(['version' => '1.0'], $tool->getMeta());
    }

    public function testConstructorThrowsExceptionForInvalidInputSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool inputSchema must have type "object"');

        new Tool(
            name: 'invalid_tool',
            inputSchema: ['type' => 'array']
        );
    }

    public function testConstructorThrowsExceptionForMissingTypeInInputSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool inputSchema must have type "object"');

        new Tool(
            name: 'invalid_tool',
            inputSchema: ['properties' => []]
        );
    }

    public function testFromArrayWithMinimalData(): void
    {
        $tool = Tool::fromArray([
            'name' => 'minimal_tool',
            'inputSchema' => ['type' => 'object'],
        ]);

        $this->assertEquals('minimal_tool', $tool->getName());
        $this->assertNull($tool->getDescription());
        $this->assertNull($tool->getTitle());
        $this->assertNull($tool->getOutputSchema());
        $this->assertNull($tool->getAnnotations());
    }

    public function testFromArrayWithFullData(): void
    {
        $tool = Tool::fromArray([
            'name' => 'full_tool',
            'inputSchema' => ['type' => 'object', 'properties' => ['input' => ['type' => 'string']]],
            'title' => 'Full Tool',
            'description' => 'A complete tool definition',
            'outputSchema' => ['type' => 'object', 'properties' => ['output' => ['type' => 'string']]],
            'annotations' => [
                'title' => 'Annotated Title',
                'readOnlyHint' => true,
                'destructiveHint' => false,
            ],
            '_meta' => ['author' => 'test'],
            'customField' => 'customValue',
        ]);

        $this->assertEquals('full_tool', $tool->getName());
        $this->assertEquals('Full Tool', $tool->getTitle());
        $this->assertEquals('A complete tool definition', $tool->getDescription());
        $this->assertNotNull($tool->getOutputSchema());
        $this->assertNotNull($tool->getAnnotations());
        $this->assertEquals('Annotated Title', $tool->getAnnotations()->getTitle());
        $this->assertTrue($tool->getAnnotations()->getReadOnlyHint());
        $this->assertFalse($tool->getAnnotations()->getDestructiveHint());
    }

    public function testFromArrayThrowsExceptionForMissingName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool must have a name property');

        Tool::fromArray([
            'inputSchema' => ['type' => 'object'],
        ]);
    }

    public function testFromArrayThrowsExceptionForMissingInputSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool must have an inputSchema property');

        Tool::fromArray([
            'name' => 'no_schema_tool',
        ]);
    }

    public function testFromArrayThrowsExceptionForInvalidOutputSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool outputSchema must have type "object"');

        Tool::fromArray([
            'name' => 'invalid_output_tool',
            'inputSchema' => ['type' => 'object'],
            'outputSchema' => ['type' => 'array'],
        ]);
    }

    public function testGetDescription(): void
    {
        $tool = new Tool(
            name: 'described_tool',
            inputSchema: ['type' => 'object'],
            description: 'This is a tool description'
        );

        $this->assertEquals('This is a tool description', $tool->getDescription());
    }

    public function testGetInputSchema(): void
    {
        $schema = ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]];
        $tool = new Tool(
            name: 'schema_tool',
            inputSchema: $schema
        );

        $this->assertEquals($schema, $tool->getInputSchema());
    }

    public function testGetOutputSchema(): void
    {
        $outputSchema = ['type' => 'object', 'properties' => ['result' => ['type' => 'boolean']]];
        $tool = new Tool(
            name: 'output_tool',
            inputSchema: ['type' => 'object'],
            outputSchema: $outputSchema
        );

        $this->assertEquals($outputSchema, $tool->getOutputSchema());
    }

    public function testGetAnnotations(): void
    {
        $annotations = new ToolAnnotations(
            title: 'Annotation Title',
            idempotentHint: true
        );
        $tool = new Tool(
            name: 'annotated_tool',
            inputSchema: ['type' => 'object'],
            annotations: $annotations
        );

        $this->assertNotNull($tool->getAnnotations());
        $this->assertEquals('Annotation Title', $tool->getAnnotations()->getTitle());
        $this->assertTrue($tool->getAnnotations()->getIdempotentHint());
    }

    public function testHasOutputSchema(): void
    {
        $toolWithOutput = new Tool(
            name: 'with_output',
            inputSchema: ['type' => 'object'],
            outputSchema: ['type' => 'object']
        );

        $toolWithoutOutput = new Tool(
            name: 'without_output',
            inputSchema: ['type' => 'object']
        );

        $this->assertTrue($toolWithOutput->hasOutputSchema());
        $this->assertFalse($toolWithoutOutput->hasOutputSchema());
    }

    public function testGetDisplayTitleWithAnnotationsTitle(): void
    {
        $annotations = new ToolAnnotations(title: 'Annotations Display Title');
        $tool = new Tool(
            name: 'display_tool',
            inputSchema: ['type' => 'object'],
            title: 'Base Title',
            annotations: $annotations
        );

        // Should prefer annotations title
        $this->assertEquals('Annotations Display Title', $tool->getDisplayTitle());
    }

    public function testGetDisplayTitleWithoutAnnotationsTitle(): void
    {
        $tool = new Tool(
            name: 'display_tool',
            inputSchema: ['type' => 'object'],
            title: 'Base Title'
        );

        // Should fall back to base title
        $this->assertEquals('Base Title', $tool->getDisplayTitle());
    }

    public function testGetDisplayTitleFallbackToName(): void
    {
        $tool = new Tool(
            name: 'display_tool',
            inputSchema: ['type' => 'object']
        );

        // Should fall back to name
        $this->assertEquals('display_tool', $tool->getDisplayTitle());
    }

    public function testJsonSerializeMinimal(): void
    {
        $tool = new Tool(
            name: 'json_tool',
            inputSchema: ['type' => 'object']
        );

        $json = $tool->jsonSerialize();

        $this->assertEquals('json_tool', $json['name']);
        $this->assertEquals(['type' => 'object'], $json['inputSchema']);
        $this->assertArrayNotHasKey('description', $json);
        $this->assertArrayNotHasKey('outputSchema', $json);
        $this->assertArrayNotHasKey('annotations', $json);
    }

    public function testJsonSerializeFull(): void
    {
        $annotations = new ToolAnnotations(
            title: 'Serialized Title',
            readOnlyHint: true
        );

        $tool = new Tool(
            name: 'full_json_tool',
            inputSchema: ['type' => 'object', 'properties' => ['input' => ['type' => 'string']]],
            title: 'Full JSON Tool',
            description: 'A tool for JSON serialization test',
            outputSchema: ['type' => 'object', 'properties' => ['output' => ['type' => 'string']]],
            annotations: $annotations,
            _meta: ['test' => true]
        );

        $json = $tool->jsonSerialize();

        $this->assertEquals('full_json_tool', $json['name']);
        $this->assertEquals('Full JSON Tool', $json['title']);
        $this->assertEquals('A tool for JSON serialization test', $json['description']);
        $this->assertArrayHasKey('inputSchema', $json);
        $this->assertArrayHasKey('outputSchema', $json);
        $this->assertArrayHasKey('annotations', $json);
        $this->assertArrayHasKey('_meta', $json);
    }

    public function testFromArrayIgnoresInvalidTitleType(): void
    {
        $tool = Tool::fromArray([
            'name' => 'tool_with_invalid_title',
            'inputSchema' => ['type' => 'object'],
            'title' => 123, // Invalid type
        ]);

        $this->assertNull($tool->getTitle());
    }

    public function testFromArrayIgnoresInvalidDescriptionType(): void
    {
        $tool = Tool::fromArray([
            'name' => 'tool_with_invalid_desc',
            'inputSchema' => ['type' => 'object'],
            'description' => ['not' => 'a string'], // Invalid type
        ]);

        $this->assertNull($tool->getDescription());
    }

    public function testFromArrayCollectsAdditionalProperties(): void
    {
        $tool = Tool::fromArray([
            'name' => 'tool_with_extras',
            'inputSchema' => ['type' => 'object'],
            'customProp1' => 'value1',
            'customProp2' => 42,
        ]);

        $json = $tool->jsonSerialize();

        $this->assertArrayHasKey('customProp1', $json);
        $this->assertArrayHasKey('customProp2', $json);
        $this->assertEquals('value1', $json['customProp1']);
        $this->assertEquals(42, $json['customProp2']);
    }

    public function testFromArrayWithNonArrayInputSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool must have an inputSchema property');

        Tool::fromArray([
            'name' => 'invalid_schema_tool',
            'inputSchema' => 'not an array',
        ]);
    }

    public function testFromArrayWithNonStringName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool must have a name property');

        Tool::fromArray([
            'name' => 123,
            'inputSchema' => ['type' => 'object'],
        ]);
    }

    public function testFromArrayWithAnnotationsNullTitle(): void
    {
        $tool = Tool::fromArray([
            'name' => 'tool_with_null_annotation_title',
            'inputSchema' => ['type' => 'object'],
            'annotations' => [
                'readOnlyHint' => true,
            ],
        ]);

        $this->assertNotNull($tool->getAnnotations());
        $this->assertNull($tool->getAnnotations()->getTitle());
        $this->assertTrue($tool->getAnnotations()->getReadOnlyHint());
    }

    public function testComplexInputSchema(): void
    {
        $complexSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'User name'],
                'age' => ['type' => 'integer', 'minimum' => 0],
                'email' => ['type' => 'string', 'format' => 'email'],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['name', 'email'],
        ];

        $tool = new Tool(
            name: 'complex_tool',
            inputSchema: $complexSchema
        );

        $this->assertEquals($complexSchema, $tool->getInputSchema());

        $json = $tool->jsonSerialize();
        $this->assertEquals($complexSchema, $json['inputSchema']);
    }
}

