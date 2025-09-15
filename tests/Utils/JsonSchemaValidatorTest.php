<?php

declare(strict_types=1);

namespace MCP\Tests\Utils;

use MCP\Types\McpError;
use MCP\Utils\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

class JsonSchemaValidatorTest extends TestCase
{
    public function testValidateValidData(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];

        $validData = ['name' => 'John', 'age' => 30];

        // Should not throw
        JsonSchemaValidator::validate($validData, $schema);
        $this->assertTrue(true);
    }

    public function testValidateInvalidData(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];

        $invalidData = ['age' => 30]; // Missing required 'name'

        $this->expectException(McpError::class);
        $this->expectExceptionMessage("Schema validation failed: Missing required property 'name'");

        JsonSchemaValidator::validate($invalidData, $schema);
    }

    public function testValidateTypeErrors(): void
    {
        $schema = ['type' => 'string'];

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Schema validation failed: Expected string at , got integer');

        JsonSchemaValidator::validate(123, $schema);
    }

    public function testValidateObjectType(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'prop1' => ['type' => 'string'],
                'prop2' => ['type' => 'number'],
            ],
        ];

        // Valid object
        JsonSchemaValidator::validate(['prop1' => 'test', 'prop2' => 42], $schema);

        // Invalid - not an object
        $this->expectException(McpError::class);
        JsonSchemaValidator::validate('not an object', $schema);
    }

    public function testValidateArrayType(): void
    {
        $schema = [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ];

        // Valid array
        JsonSchemaValidator::validate(['hello', 'world'], $schema);

        // Invalid array items
        $this->expectException(McpError::class);
        JsonSchemaValidator::validate(['hello', 123], $schema);
    }

    public function testValidateArrayConstraints(): void
    {
        $schema = [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'minItems' => 2,
            'maxItems' => 4,
        ];

        // Valid array
        JsonSchemaValidator::validate(['a', 'b', 'c'], $schema);

        // Too few items
        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Array at  must have at least 2 items');
        JsonSchemaValidator::validate(['a'], $schema);
    }

    public function testValidateStringConstraints(): void
    {
        $schema = [
            'type' => 'string',
            'minLength' => 3,
            'maxLength' => 10,
        ];

        // Valid string
        JsonSchemaValidator::validate('hello', $schema);

        // Too short
        $this->expectException(McpError::class);
        $this->expectExceptionMessage('String at  must be at least 3 characters');
        JsonSchemaValidator::validate('hi', $schema);
    }

    public function testValidateStringPattern(): void
    {
        $schema = [
            'type' => 'string',
            'pattern' => '^[a-z]+$',
        ];

        // Valid string
        JsonSchemaValidator::validate('hello', $schema);

        // Invalid pattern
        $this->expectException(McpError::class);
        $this->expectExceptionMessage('String at  does not match required pattern');
        JsonSchemaValidator::validate('Hello123', $schema);
    }

    public function testValidateStringEnum(): void
    {
        $schema = [
            'type' => 'string',
            'enum' => ['red', 'green', 'blue'],
        ];

        // Valid enum value
        JsonSchemaValidator::validate('red', $schema);

        // Invalid enum value
        $this->expectException(McpError::class);
        $this->expectExceptionMessage('String at  must be one of: red, green, blue');
        JsonSchemaValidator::validate('yellow', $schema);
    }

    public function testValidateNumberConstraints(): void
    {
        $schema = [
            'type' => 'number',
            'minimum' => 0,
            'maximum' => 100,
        ];

        // Valid number
        JsonSchemaValidator::validate(50, $schema);

        // Too small
        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Number at  must be at least 0');
        JsonSchemaValidator::validate(-1, $schema);
    }

    public function testValidateIntegerType(): void
    {
        $schema = ['type' => 'integer'];

        // Valid integer
        JsonSchemaValidator::validate(42, $schema);
        JsonSchemaValidator::validate('42', $schema); // String digits should work

        // Invalid integer
        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Expected integer at , got double');
        JsonSchemaValidator::validate(42.5, $schema);
    }

    public function testValidateAdditionalProperties(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'allowed' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        // Valid - only allowed properties
        JsonSchemaValidator::validate(['allowed' => 'value'], $schema);

        // Invalid - additional property
        $this->expectException(McpError::class);
        $this->expectExceptionMessage("Additional property 'extra' not allowed");
        JsonSchemaValidator::validate(['allowed' => 'value', 'extra' => 'not allowed'], $schema);
    }

    public function testNormalizeSchema(): void
    {
        // Already normalized schema
        $jsonSchema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];
        $result = JsonSchemaValidator::normalizeSchema($jsonSchema);
        $this->assertEquals($jsonSchema, $result);

        // Simple array schema
        $simpleSchema = ['name' => ['type' => 'string']];
        $result = JsonSchemaValidator::normalizeSchema($simpleSchema);
        $expected = [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'additionalProperties' => false,
        ];
        $this->assertEquals($expected, $result);

        // String type schema
        $result = JsonSchemaValidator::normalizeSchema('string');
        $this->assertEquals(['type' => 'string'], $result);

        // Default schema
        $result = JsonSchemaValidator::normalizeSchema(null);
        $expected = [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];
        $this->assertEquals($expected, $result);
    }

    public function testExtractPromptArguments(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'The name parameter',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => 'The age parameter',
                ],
                'optional' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['name', 'age'],
        ];

        $arguments = JsonSchemaValidator::extractPromptArguments($schema);

        $this->assertCount(3, $arguments);

        // Check name argument
        $nameArgs = array_filter($arguments, fn ($arg) => $arg['name'] === 'name');
        $nameArg = reset($nameArgs);
        $this->assertNotFalse($nameArg, 'Name argument should exist');
        $this->assertEquals('name', $nameArg['name']);
        $this->assertEquals('The name parameter', $nameArg['description']);
        $this->assertEquals('string', $nameArg['type']);
        $this->assertTrue($nameArg['required']);

        // Check age argument
        $ageArgs = array_filter($arguments, fn ($arg) => $arg['name'] === 'age');
        $ageArg = reset($ageArgs); // Get first element
        $this->assertNotFalse($ageArg, 'Age argument should exist');
        $this->assertEquals('age', $ageArg['name']);
        $this->assertEquals('integer', $ageArg['type']);
        $this->assertTrue($ageArg['required']);

        // Check optional argument
        $optionalArgs = array_filter($arguments, fn ($arg) => $arg['name'] === 'optional');
        $optionalArg = reset($optionalArgs);
        $this->assertNotFalse($optionalArg, 'Optional argument should exist');
        $this->assertEquals('optional', $optionalArg['name']);
        $this->assertArrayNotHasKey('required', $optionalArg);
    }

    public function testGetSchemaField(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'field1' => ['type' => 'string'],
                'field2' => ['type' => 'number'],
            ],
        ];

        $field1 = JsonSchemaValidator::getSchemaField($schema, 'field1');
        $this->assertEquals(['type' => 'string'], $field1);

        $field2 = JsonSchemaValidator::getSchemaField($schema, 'field2');
        $this->assertEquals(['type' => 'number'], $field2);

        $nonExistent = JsonSchemaValidator::getSchemaField($schema, 'nonexistent');
        $this->assertNull($nonExistent);
    }

    public function testValidateNestedObjects(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
            ],
            'required' => ['user'],
        ];

        // Valid nested object
        $validData = [
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ];
        JsonSchemaValidator::validate($validData, $schema);

        // Invalid nested object - missing required field
        $invalidData = [
            'user' => [
                'email' => 'john@example.com',
            ],
        ];

        $this->expectException(McpError::class);
        $this->expectExceptionMessage("Missing required property 'name' at user");
        JsonSchemaValidator::validate($invalidData, $schema);
    }

    public function testValidateArrayOfObjects(): void
    {
        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
                'required' => ['id'],
            ],
        ];

        // Valid array of objects
        $validData = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ];
        JsonSchemaValidator::validate($validData, $schema);

        // Invalid - missing required field in array item
        $invalidData = [
            ['id' => 1, 'name' => 'First'],
            ['name' => 'Second'], // Missing id
        ];

        $this->expectException(McpError::class);
        $this->expectExceptionMessage("Missing required property 'id' at [1]");
        JsonSchemaValidator::validate($invalidData, $schema);
    }
}
