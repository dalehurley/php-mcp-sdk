<?php

declare(strict_types=1);

namespace MCP\Types\Sampling;

/**
 * Hints to use for model selection.
 */
final class ModelHint implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?string $name = null,
        private readonly array $additionalProperties = []
    ) {}

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : null;

        // Remove known properties to collect additional properties
        unset($data['name']);

        return new self(
            name: $name,
            additionalProperties: $data
        );
    }

    /**
     * Get the hint for a model name.
     */
    public function getName(): ?string
    {
        return $this->name;
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
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = $this->additionalProperties;

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        return $data;
    }
}
