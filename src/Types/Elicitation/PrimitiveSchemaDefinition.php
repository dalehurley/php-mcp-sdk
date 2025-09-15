<?php

declare(strict_types=1);

namespace MCP\Types\Elicitation;

/**
 * Base class for primitive schema definitions.
 */
abstract class PrimitiveSchemaDefinition implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $additional Additional properties
     */
    public function __construct(
        protected readonly string $type,
        protected readonly ?string $title = null,
        protected readonly ?string $description = null,
        protected readonly array $additional = []
    ) {
    }

    /**
     * Get the type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the title.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Get the description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = array_merge(
            ['type' => $this->type],
            $this->additional
        );

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }

    /**
     * Create from an array based on the type field.
     *
     * @param array<string, mixed> $data
     *
     * @return BooleanSchema|StringSchema|NumberSchema|EnumSchema
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type']) || !is_string($data['type'])) {
            throw new \InvalidArgumentException('PrimitiveSchemaDefinition must have a type property');
        }

        return match ($data['type']) {
            'boolean' => BooleanSchema::fromArray($data),
            'string' => isset($data['enum'])
                ? EnumSchema::fromArray($data)
                : StringSchema::fromArray($data),
            'number', 'integer' => NumberSchema::fromArray($data),
            default => throw new \InvalidArgumentException('Unknown primitive schema type: ' . $data['type']),
        };
    }
}
