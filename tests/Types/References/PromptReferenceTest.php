<?php

declare(strict_types=1);

namespace MCP\Tests\Types\References;

use MCP\Types\References\PromptReference;
use PHPUnit\Framework\TestCase;

/**
 * Test class for PromptReference.
 */
class PromptReferenceTest extends TestCase
{
    /**
     * Test basic construction.
     */
    public function testBasicConstruction(): void
    {
        $ref = new PromptReference('my-prompt');
        
        $this->assertEquals('my-prompt', $ref->getName());
    }

    /**
     * Test fromArray method.
     */
    public function testFromArray(): void
    {
        $data = [
            'type' => 'ref/prompt',
            'name' => 'test-prompt'
        ];
        
        $ref = PromptReference::fromArray($data);
        
        $this->assertEquals('test-prompt', $ref->getName());
    }

    /**
     * Test fromArray with additional properties.
     */
    public function testFromArrayWithAdditionalProperties(): void
    {
        $data = [
            'type' => 'ref/prompt',
            'name' => 'custom-prompt',
            'version' => '1.0',
            'metadata' => ['key' => 'value']
        ];
        
        $ref = PromptReference::fromArray($data);
        
        $this->assertEquals('custom-prompt', $ref->getName());
        
        // Additional properties should be preserved in JSON
        $json = $ref->jsonSerialize();
        $this->assertEquals('1.0', $json['version']);
        $this->assertEquals(['key' => 'value'], $json['metadata']);
    }

    /**
     * Test fromArray with wrong type.
     */
    public function testFromArrayWithWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PromptReference must have type "ref/prompt"');
        
        PromptReference::fromArray([
            'type' => 'ref/other',
            'name' => 'test'
        ]);
    }

    /**
     * Test fromArray without name.
     */
    public function testFromArrayWithoutName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PromptReference must have a name property');
        
        PromptReference::fromArray([
            'type' => 'ref/prompt'
        ]);
    }

    /**
     * Test JSON serialization.
     */
    public function testJsonSerialization(): void
    {
        $ref = new PromptReference('json-prompt');
        $json = $ref->jsonSerialize();
        
        $this->assertEquals('ref/prompt', $json['type']);
        $this->assertEquals('json-prompt', $json['name']);
    }

    /**
     * Test JSON serialization with additional properties.
     */
    public function testJsonSerializationWithAdditionalProperties(): void
    {
        $ref = new PromptReference('extra-prompt', ['extra' => 'data']);
        $json = $ref->jsonSerialize();
        
        $this->assertEquals('ref/prompt', $json['type']);
        $this->assertEquals('extra-prompt', $json['name']);
        $this->assertEquals('data', $json['extra']);
    }
}
