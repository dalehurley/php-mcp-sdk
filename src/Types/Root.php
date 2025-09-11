<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Represents a root directory or file that the server can operate on.
 */
final class Root implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $uri,
        private readonly ?string $name = null,
        private readonly ?array $_meta = null,
        private readonly array $additionalProperties = []
    ) {
        if (!str_starts_with($uri, 'file://')) {
            throw new \InvalidArgumentException('Root URI must start with file://');
        }
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException('Root must have a uri property');
        }

        $uri = $data['uri'];
        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : null;
        $_meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;

        // Remove known properties to collect additional properties
        unset($data['uri'], $data['name'], $data['_meta']);

        return new self(
            uri: $uri,
            name: $name,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the URI identifying the root.
     * This must start with file:// for now.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the optional name for the root.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the display name (name if available, otherwise extract from URI).
     */
    public function getDisplayName(): string
    {
        if ($this->name !== null) {
            return $this->name;
        }

        // Extract the last part of the URI path as display name
        $path = parse_url($this->uri, PHP_URL_PATH);
        if ($path === null || $path === false) {
            return $this->uri;
        }

        $parts = explode('/', rtrim($path, '/'));
        return end($parts) ?: $this->uri;
    }

    /**
     * Get metadata associated with this root.
     *
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        return $this->_meta;
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
     * @return array{uri: string, name?: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = array_merge($this->additionalProperties, [
            'uri' => $this->uri,
        ]);

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->_meta !== null) {
            $data['_meta'] = $this->_meta;
        }

        return $data;
    }
}
