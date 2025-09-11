<?php

declare(strict_types=1);

namespace MCP\Types\Elicitation;

/**
 * Primitive schema definition for boolean fields.
 */
final class BooleanSchema extends PrimitiveSchemaDefinition
{
    /**
     * @param array<string, mixed> $additional Additional properties
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        private readonly ?bool $default = null,
        array $additional = []
    ) {
        parent::__construct('boolean', $title, $description, $additional);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type']) || $data['type'] !== 'boolean') {
            throw new \InvalidArgumentException('BooleanSchema must have type "boolean"');
        }

        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $default = isset($data['default']) && is_bool($data['default']) ? $data['default'] : null;

        // Collect additional properties
        $additional = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['type', 'title', 'description', 'default'], true)) {
                $additional[$key] = $value;
            }
        }

        return new self($title, $description, $default, $additional);
    }

    /**
     * Get the default value.
     */
    public function getDefault(): ?bool
    {
        return $this->default;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->default !== null) {
            $data['default'] = $this->default;
        }

        return $data;
    }
}
