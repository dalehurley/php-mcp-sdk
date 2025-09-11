<?php

declare(strict_types=1);

namespace MCP\Types\Tools;

use MCP\Types\BaseMetadata;

/**
 * Definition for a tool the client can call.
 */
final class Tool extends BaseMetadata
{
    /**
     * @param array<string, mixed> $inputSchema
     * @param array<string, mixed>|null $outputSchema
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        string $name,
        private readonly array $inputSchema,
        ?string $title = null,
        private readonly ?string $description = null,
        private readonly ?array $outputSchema = null,
        private readonly ?ToolAnnotations $annotations = null,
        ?array $_meta = null,
        array $additionalProperties = []
    ) {
        parent::__construct($name, $title, $_meta, $additionalProperties);

        // Validate input schema
        if (!isset($inputSchema['type']) || $inputSchema['type'] !== 'object') {
            throw new \InvalidArgumentException('Tool inputSchema must have type "object"');
        }
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('Tool must have a name property');
        }

        if (!isset($data['inputSchema']) || !is_array($data['inputSchema'])) {
            throw new \InvalidArgumentException('Tool must have an inputSchema property');
        }

        $name = $data['name'];
        $inputSchema = $data['inputSchema'];
        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;

        $outputSchema = null;
        if (isset($data['outputSchema']) && is_array($data['outputSchema'])) {
            $outputSchema = $data['outputSchema'];
            if (!isset($outputSchema['type']) || $outputSchema['type'] !== 'object') {
                throw new \InvalidArgumentException('Tool outputSchema must have type "object"');
            }
        }

        $annotations = null;
        if (isset($data['annotations']) && is_array($data['annotations'])) {
            $annotations = ToolAnnotations::fromArray($data['annotations']);
        }

        $_meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;

        // Remove known properties to collect additional properties
        unset(
            $data['name'],
            $data['inputSchema'],
            $data['title'],
            $data['description'],
            $data['outputSchema'],
            $data['annotations'],
            $data['_meta']
        );

        return new static(
            name: $name,
            inputSchema: $inputSchema,
            title: $title,
            description: $description,
            outputSchema: $outputSchema,
            annotations: $annotations,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the human-readable description of the tool.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the JSON Schema object defining the expected parameters.
     *
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return $this->inputSchema;
    }

    /**
     * Get the JSON Schema object defining the structure of the tool's output.
     *
     * @return array<string, mixed>|null
     */
    public function getOutputSchema(): ?array
    {
        return $this->outputSchema;
    }

    /**
     * Get optional additional tool information.
     */
    public function getAnnotations(): ?ToolAnnotations
    {
        return $this->annotations;
    }

    /**
     * Check if this tool has output schema defined.
     */
    public function hasOutputSchema(): bool
    {
        return $this->outputSchema !== null;
    }

    /**
     * Get the display title for this tool.
     * Prefers annotations.title over the base title if available.
     */
    public function getDisplayTitle(): string
    {
        if ($this->annotations !== null && $this->annotations->getTitle() !== null) {
            return $this->annotations->getTitle();
        }

        return $this->getDisplayName();
    }

    /**
     * @return array{name: string, inputSchema: array<string, mixed>, title?: string, description?: string, outputSchema?: array<string, mixed>, annotations?: array<string, mixed>, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['inputSchema'] = $this->inputSchema;

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->outputSchema !== null) {
            $data['outputSchema'] = $this->outputSchema;
        }

        if ($this->annotations !== null) {
            $data['annotations'] = $this->annotations->jsonSerialize();
        }

        return $data;
    }
}
