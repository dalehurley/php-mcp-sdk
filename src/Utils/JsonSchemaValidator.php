<?php

declare(strict_types=1);

namespace MCP\Utils;

use MCP\Types\McpError;
use MCP\Types\ErrorCode;

/**
 * JSON Schema validation utility for MCP server components
 */
class JsonSchemaValidator
{
    /**
     * Validate data against a JSON schema
     * 
     * @param mixed $data The data to validate
     * @param array $schema The JSON schema to validate against
     * @throws McpError If validation fails
     */
    public static function validate($data, array $schema): void
    {
        $errors = self::validateSchema($data, $schema);

        if (!empty($errors)) {
            throw new McpError(
                ErrorCode::InvalidParams,
                "Schema validation failed: " . implode(', ', $errors)
            );
        }
    }

    /**
     * Validate data against schema and return errors
     * 
     * @param mixed $data
     * @param array $schema
     * @return array<string> Array of error messages
     */
    private static function validateSchema($data, array $schema, string $path = ''): array
    {
        $errors = [];

        // Handle type validation
        if (isset($schema['type'])) {
            $typeError = self::validateType($data, $schema['type'], $path);
            if ($typeError) {
                $errors[] = $typeError;
                return $errors; // Early return on type mismatch
            }
        }

        // Handle object validation
        if (isset($schema['type']) && $schema['type'] === 'object') {
            if (!is_array($data) && !is_object($data)) {
                $errors[] = "Expected object at {$path}";
                return $errors;
            }

            $data = is_object($data) ? (array)$data : $data;

            // Validate required properties
            if (isset($schema['required'])) {
                foreach ($schema['required'] as $required) {
                    if (!array_key_exists($required, $data)) {
                        $errors[] = "Missing required property '{$required}' at {$path}";
                    }
                }
            }

            // Validate properties
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as $prop => $propSchema) {
                    if (array_key_exists($prop, $data)) {
                        $propPath = $path ? "{$path}.{$prop}" : $prop;
                        $errors = array_merge($errors, self::validateSchema($data[$prop], $propSchema, $propPath));
                    }
                }
            }

            // Check for additional properties
            if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                $allowedProps = array_keys($schema['properties'] ?? []);
                foreach (array_keys($data) as $prop) {
                    if (!in_array($prop, $allowedProps)) {
                        $errors[] = "Additional property '{$prop}' not allowed at {$path}";
                    }
                }
            }
        }

        // Handle array validation
        if (isset($schema['type']) && $schema['type'] === 'array') {
            if (!is_array($data)) {
                $errors[] = "Expected array at {$path}";
                return $errors;
            }

            // Validate items
            if (isset($schema['items'])) {
                foreach ($data as $index => $item) {
                    $itemPath = "{$path}[{$index}]";
                    $errors = array_merge($errors, self::validateSchema($item, $schema['items'], $itemPath));
                }
            }

            // Validate array constraints
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = "Array at {$path} must have at least {$schema['minItems']} items";
            }

            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[] = "Array at {$path} must have at most {$schema['maxItems']} items";
            }
        }

        // Handle string validation
        if (isset($schema['type']) && $schema['type'] === 'string') {
            if (isset($schema['minLength']) && strlen((string)$data) < $schema['minLength']) {
                $errors[] = "String at {$path} must be at least {$schema['minLength']} characters";
            }

            if (isset($schema['maxLength']) && strlen((string)$data) > $schema['maxLength']) {
                $errors[] = "String at {$path} must be at most {$schema['maxLength']} characters";
            }

            if (isset($schema['pattern']) && !preg_match('/' . $schema['pattern'] . '/', (string)$data)) {
                $errors[] = "String at {$path} does not match required pattern";
            }

            if (isset($schema['enum']) && !in_array($data, $schema['enum'])) {
                $errors[] = "String at {$path} must be one of: " . implode(', ', $schema['enum']);
            }
        }

        // Handle number validation
        if (isset($schema['type']) && in_array($schema['type'], ['number', 'integer'])) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "Number at {$path} must be at least {$schema['minimum']}";
            }

            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "Number at {$path} must be at most {$schema['maximum']}";
            }

            if ($schema['type'] === 'integer' && !is_int($data) && !ctype_digit((string)$data)) {
                $errors[] = "Value at {$path} must be an integer";
            }
        }

        return $errors;
    }

    /**
     * Validate data type
     */
    private static function validateType($data, string $expectedType, string $path): ?string
    {
        switch ($expectedType) {
            case 'string':
                if (!is_string($data)) {
                    return "Expected string at {$path}, got " . gettype($data);
                }
                break;
            case 'number':
                if (!is_numeric($data)) {
                    return "Expected number at {$path}, got " . gettype($data);
                }
                break;
            case 'integer':
                if (!is_int($data) && !ctype_digit((string)$data)) {
                    return "Expected integer at {$path}, got " . gettype($data);
                }
                break;
            case 'boolean':
                if (!is_bool($data)) {
                    return "Expected boolean at {$path}, got " . gettype($data);
                }
                break;
            case 'array':
                if (!is_array($data)) {
                    return "Expected array at {$path}, got " . gettype($data);
                }
                break;
            case 'object':
                if (!is_array($data) && !is_object($data)) {
                    return "Expected object at {$path}, got " . gettype($data);
                }
                break;
            case 'null':
                if ($data !== null) {
                    return "Expected null at {$path}, got " . gettype($data);
                }
                break;
        }

        return null;
    }

    /**
     * Convert a simple schema definition to JSON Schema format
     * 
     * @param mixed $schema The schema definition
     * @return array JSON Schema
     */
    public static function normalizeSchema($schema): array
    {
        if (is_array($schema)) {
            // If it already looks like a JSON Schema, return as-is
            if (isset($schema['type']) || isset($schema['properties']) || isset($schema['$schema'])) {
                return $schema;
            }

            // Convert simple array to object schema
            return [
                'type' => 'object',
                'properties' => $schema,
                'additionalProperties' => false
            ];
        }

        // Handle string type definitions
        if (is_string($schema)) {
            return ['type' => $schema];
        }

        // Default to empty object schema
        return [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false
        ];
    }

    /**
     * Extract prompt arguments from a schema
     * 
     * @param mixed $schema
     * @return array<array> Array of PromptArgument-like structures
     */
    public static function extractPromptArguments($schema): array
    {
        $normalized = self::normalizeSchema($schema);
        $arguments = [];

        if (isset($normalized['properties'])) {
            foreach ($normalized['properties'] as $name => $propSchema) {
                $argument = ['name' => $name];

                if (isset($propSchema['description'])) {
                    $argument['description'] = $propSchema['description'];
                }

                if (isset($propSchema['type'])) {
                    // Map JSON Schema types to MCP argument types if needed
                    $argument['type'] = $propSchema['type'];
                }

                if (isset($normalized['required']) && in_array($name, $normalized['required'])) {
                    $argument['required'] = true;
                }

                $arguments[] = $argument;
            }
        }

        return $arguments;
    }

    /**
     * Get a field from a schema by name
     * 
     * @param mixed $schema
     * @param string $fieldName
     * @return mixed The field schema or null if not found
     */
    public static function getSchemaField($schema, string $fieldName)
    {
        $normalized = self::normalizeSchema($schema);

        return $normalized['properties'][$fieldName] ?? null;
    }
}
