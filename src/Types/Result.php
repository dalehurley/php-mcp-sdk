<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Base result class for MCP responses.
 */
class Result implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?array $_meta = null,
        private readonly array $additionalProperties = []
    ) {}

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        $_meta = null;
        if (isset($data['_meta']) && is_array($data['_meta'])) {
            $_meta = $data['_meta'];
            unset($data['_meta']);
        }

        /** @phpstan-ignore-next-line */
        return new static(
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the _meta field.
     *
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        return $this->_meta;
    }

    /**
     * Check if this result has metadata.
     */
    public function hasMeta(): bool
    {
        return $this->_meta !== null;
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
     * Get a specific additional property.
     */
    public function getAdditionalProperty(string $key): mixed
    {
        return $this->additionalProperties[$key] ?? null;
    }

    /**
     * Create a new result with metadata.
     *
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): static
    {
        /** @phpstan-ignore-next-line */
        return new static(
            _meta: $meta,
            additionalProperties: $this->additionalProperties
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = $this->additionalProperties;

        if ($this->_meta !== null) {
            $data['_meta'] = $this->_meta;
        }

        return $data;
    }
}
