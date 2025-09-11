<?php

declare(strict_types=1);

namespace MCP\Tests\Types\Elicitation;

use MCP\Types\Elicitation\BooleanSchema;
use PHPUnit\Framework\TestCase;

/**
 * Test class for BooleanSchema.
 */
class BooleanSchemaTest extends TestCase
{
    /**
     * Test basic construction.
     */
    public function testBasicConstruction(): void
    {
        $schema = new BooleanSchema();
        
        $this->assertEquals('boolean', $schema->getType());
        $this->assertNull($schema->getTitle());
        $this->assertNull($schema->getDescription());
        $this->assertNull($schema->getDefault());
    }

    /**
     * Test construction with all parameters.
     */
    public function testFullConstruction(): void
    {
        $schema = new BooleanSchema(
            title: 'Enabled',
            description: 'Whether the feature is enabled',
            default: true
        );
        
        $this->assertEquals('boolean', $schema->getType());
        $this->assertEquals('Enabled', $schema->getTitle());
        $this->assertEquals('Whether the feature is enabled', $schema->getDescription());
        $this->assertTrue($schema->getDefault());
    }

    /**
     * Test fromArray method.
     */
    public function testFromArray(): void
    {
        $data = [
            'type' => 'boolean',
            'title' => 'Active',
            'description' => 'Is active?',
            'default' => false
        ];
        
        $schema = BooleanSchema::fromArray($data);
        
        $this->assertEquals('boolean', $schema->getType());
        $this->assertEquals('Active', $schema->getTitle());
        $this->assertEquals('Is active?', $schema->getDescription());
        $this->assertFalse($schema->getDefault());
    }

    /**
     * Test fromArray with wrong type.
     */
    public function testFromArrayWithWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BooleanSchema must have type "boolean"');
        
        BooleanSchema::fromArray(['type' => 'string']);
    }

    /**
     * Test JSON serialization.
     */
    public function testJsonSerialization(): void
    {
        $schema = new BooleanSchema(
            title: 'Confirm',
            description: 'Please confirm',
            default: true
        );
        
        $json = $schema->jsonSerialize();
        
        $this->assertEquals('boolean', $json['type']);
        $this->assertEquals('Confirm', $json['title']);
        $this->assertEquals('Please confirm', $json['description']);
        $this->assertTrue($json['default']);
    }

    /**
     * Test JSON serialization minimal.
     */
    public function testJsonSerializationMinimal(): void
    {
        $schema = new BooleanSchema();
        $json = $schema->jsonSerialize();
        
        $this->assertEquals(['type' => 'boolean'], $json);
    }

    /**
     * Test JSON serialization with additional properties.
     */
    public function testJsonSerializationWithAdditionalProperties(): void
    {
        $data = [
            'type' => 'boolean',
            'title' => 'Test',
            'x-custom' => 'value',
            'readOnly' => true
        ];
        
        $schema = BooleanSchema::fromArray($data);
        $json = $schema->jsonSerialize();
        
        $this->assertEquals('boolean', $json['type']);
        $this->assertEquals('Test', $json['title']);
        $this->assertEquals('value', $json['x-custom']);
        $this->assertTrue($json['readOnly']);
    }
}
