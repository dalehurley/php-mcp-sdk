<?php

declare(strict_types=1);

namespace MCP\Types\Elicitation;

/**
 * Primitive schema definition for string fields.
 */
final class StringSchema extends PrimitiveSchemaDefinition
{
    /**
     * @param array<string, mixed> $additional Additional properties
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        private readonly ?int $minLength = null,
        private readonly ?int $maxLength = null,
        private readonly ?string $format = null,
        array $additional = []
    ) {
        parent::__construct('string', $title, $description, $additional);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type']) || $data['type'] !== 'string') {
            throw new \InvalidArgumentException('StringSchema must have type "string"');
        }

        // Check if it's an enum schema
        if (isset($data['enum'])) {
            throw new \InvalidArgumentException('Use EnumSchema for string enums');
        }

        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $minLength = isset($data['minLength']) && is_int($data['minLength']) ? $data['minLength'] : null;
        $maxLength = isset($data['maxLength']) && is_int($data['maxLength']) ? $data['maxLength'] : null;
        $format = isset($data['format']) && is_string($data['format']) ? $data['format'] : null;

        // Collect additional properties
        $additional = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['type', 'title', 'description', 'minLength', 'maxLength', 'format'], true)) {
                $additional[$key] = $value;
            }
        }

        return new self($title, $description, $minLength, $maxLength, $format, $additional);
    }

    /**
     * Get the minimum length.
     */
    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    /**
     * Get the maximum length.
     */
    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * Get the format.
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->minLength !== null) {
            $data['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $data['maxLength'] = $this->maxLength;
        }

        if ($this->format !== null) {
            $data['format'] = $this->format;
        }

        return $data;
    }
}
