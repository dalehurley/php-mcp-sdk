<?php

declare(strict_types=1);

namespace MCP\Types\Prompts;

/**
 * Describes an argument that a prompt can accept.
 */
final class PromptArgument implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $name,
        private readonly ?string $description = null,
        private readonly ?bool $required = null,
        private readonly array $additionalProperties = []
    ) {
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('PromptArgument must have a name property');
        }

        $name = $data['name'];
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $required = isset($data['required']) && is_bool($data['required']) ? $data['required'] : null;

        // Remove known properties to collect additional properties
        unset($data['name'], $data['description'], $data['required']);

        return new self(
            name: $name,
            description: $description,
            required: $required,
            additionalProperties: $data
        );
    }

    /**
     * Get the name of the argument.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the human-readable description of the argument.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get whether this argument must be provided.
     */
    public function getRequired(): ?bool
    {
        return $this->required;
    }

    /**
     * Check if this argument is required.
     * Returns false if not explicitly set to true.
     */
    public function isRequired(): bool
    {
        return $this->required === true;
    }

    /**
     * Get additional properties.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalProperties(): array
    {
        return $this->additionalProperties;
    }

    /**
     * @return array{name: string, description?: string, required?: bool, ...}
     */
    public function jsonSerialize(): array
    {
        $data = array_merge($this->additionalProperties, [
            'name' => $this->name,
        ]);

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->required !== null) {
            $data['required'] = $this->required;
        }

        return $data;
    }
}
