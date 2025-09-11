<?php

declare(strict_types=1);

namespace MCP\Factories;

use MCP\Types\Tools\Tool;
use MCP\Types\Tools\ToolAnnotations;
use MCP\Validation\ValidationException;

/**
 * Factory for creating Tool instances.
 *
 * @extends AbstractTypeFactory<Tool>
 */
class ToolFactory extends AbstractTypeFactory
{
    protected function getValidatorType(): ?string
    {
        return 'tool';
    }

    protected function createInstance(array $data): Tool
    {
        // Validate required fields
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new ValidationException('Tool must have a name property');
        }

        if (!isset($data['inputSchema']) || !is_array($data['inputSchema'])) {
            throw new ValidationException('Tool must have an inputSchema property');
        }

        // Create annotations if present
        $annotations = null;
        if (isset($data['annotations']) && is_array($data['annotations'])) {
            $annotations = $this->createAnnotations($data['annotations']);
        }

        // Extract additional properties
        $additionalProperties = $data;
        unset(
            $additionalProperties['name'],
            $additionalProperties['title'],
            $additionalProperties['description'],
            $additionalProperties['inputSchema'],
            $additionalProperties['outputSchema'],
            $additionalProperties['annotations'],
            $additionalProperties['_meta']
        );

        return new Tool(
            name: $data['name'],
            inputSchema: $data['inputSchema'],
            title: $this->getString($data, 'title'),
            description: $this->getString($data, 'description'),
            outputSchema: $this->getArray($data, 'outputSchema'),
            annotations: $annotations,
            _meta: $this->getArray($data, '_meta'),
            additionalProperties: $additionalProperties
        );
    }

    /**
     * Create tool annotations.
     */
    private function createAnnotations(array $data): ToolAnnotations
    {
        $additionalProperties = $data;
        unset(
            $additionalProperties['title'],
            $additionalProperties['readOnlyHint'],
            $additionalProperties['destructiveHint'],
            $additionalProperties['idempotentHint'],
            $additionalProperties['openWorldHint']
        );

        return new ToolAnnotations(
            title: $this->getString($data, 'title'),
            readOnlyHint: $this->getBool($data, 'readOnlyHint'),
            destructiveHint: $this->getBool($data, 'destructiveHint'),
            idempotentHint: $this->getBool($data, 'idempotentHint'),
            openWorldHint: $this->getBool($data, 'openWorldHint'),
            additionalProperties: $additionalProperties
        );
    }

    /**
     * Create a tool with minimal configuration.
     *
     * @param array<string, mixed> $inputSchema
     */
    public function createSimple(
        string $name,
        array $inputSchema,
        ?string $description = null
    ): Tool {
        return new Tool(
            name: $name,
            inputSchema: $inputSchema,
            description: $description
        );
    }
}
