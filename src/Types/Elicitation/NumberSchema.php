<?php

declare(strict_types=1);

namespace MCP\Types\Elicitation;

/**
 * Primitive schema definition for number fields.
 */
final class NumberSchema extends PrimitiveSchemaDefinition
{
    /**
     * @param array<string, mixed> $additional Additional properties
     */
    public function __construct(
        string $type,
        ?string $title = null,
        ?string $description = null,
        private readonly ?float $minimum = null,
        private readonly ?float $maximum = null,
        array $additional = []
    ) {
        if (!in_array($type, ['number', 'integer'], true)) {
            throw new \InvalidArgumentException('NumberSchema type must be "number" or "integer"');
        }
        parent::__construct($type, $title, $description, $additional);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type']) || !in_array($data['type'], ['number', 'integer'], true)) {
            throw new \InvalidArgumentException('NumberSchema must have type "number" or "integer"');
        }

        $type = $data['type'];
        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $minimum = isset($data['minimum']) && is_numeric($data['minimum']) ? (float) $data['minimum'] : null;
        $maximum = isset($data['maximum']) && is_numeric($data['maximum']) ? (float) $data['maximum'] : null;

        // Collect additional properties
        $additional = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['type', 'title', 'description', 'minimum', 'maximum'], true)) {
                $additional[$key] = $value;
            }
        }

        return new self($type, $title, $description, $minimum, $maximum, $additional);
    }

    /**
     * Check if this is an integer schema.
     */
    public function isInteger(): bool
    {
        return $this->type === 'integer';
    }

    /**
     * Get the minimum value.
     */
    public function getMinimum(): ?float
    {
        return $this->minimum;
    }

    /**
     * Get the maximum value.
     */
    public function getMaximum(): ?float
    {
        return $this->maximum;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->minimum !== null) {
            $data['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $data['maximum'] = $this->maximum;
        }

        return $data;
    }
}
