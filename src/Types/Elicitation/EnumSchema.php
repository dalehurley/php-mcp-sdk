<?php

declare(strict_types=1);

namespace MCP\Types\Elicitation;

/**
 * Primitive schema definition for enum fields.
 */
final class EnumSchema extends PrimitiveSchemaDefinition
{
    /**
     * @param string[] $enum
     * @param string[]|null $enumNames
     * @param array<string, mixed> $additional Additional properties
     */
    public function __construct(
        array $enum,
        ?string $title = null,
        ?string $description = null,
        private readonly ?array $enumNames = null,
        array $additional = []
    ) {
        parent::__construct('string', $title, $description, $additional);

        if (empty($enum)) {
            throw new \InvalidArgumentException('EnumSchema must have at least one enum value');
        }

        $this->enum = $enum;
    }

    /** @var string[] */
    private readonly array $enum;

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type']) || $data['type'] !== 'string') {
            throw new \InvalidArgumentException('EnumSchema must have type "string"');
        }

        if (!isset($data['enum']) || !is_array($data['enum']) || empty($data['enum'])) {
            throw new \InvalidArgumentException('EnumSchema must have a non-empty enum array');
        }

        // Ensure all enum values are strings
        $enum = array_filter($data['enum'], 'is_string');
        if (count($enum) !== count($data['enum'])) {
            throw new \InvalidArgumentException('All enum values must be strings');
        }

        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;

        $enumNames = null;
        if (isset($data['enumNames']) && is_array($data['enumNames'])) {
            $enumNames = array_filter($data['enumNames'], 'is_string');
        }

        // Collect additional properties
        $additional = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['type', 'title', 'description', 'enum', 'enumNames'], true)) {
                $additional[$key] = $value;
            }
        }

        return new self($enum, $title, $description, $enumNames, $additional);
    }

    /**
     * Get the enum values.
     *
     * @return string[]
     */
    public function getEnum(): array
    {
        return $this->enum;
    }

    /**
     * Get the enum names.
     *
     * @return string[]|null
     */
    public function getEnumNames(): ?array
    {
        return $this->enumNames;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['enum'] = $this->enum;

        if ($this->enumNames !== null) {
            $data['enumNames'] = $this->enumNames;
        }

        return $data;
    }
}
