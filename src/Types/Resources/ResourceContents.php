<?php

declare(strict_types=1);

namespace MCP\Types\Resources;

/**
 * Base class for the contents of a specific resource or sub-resource.
 */
abstract class ResourceContents implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $uri,
        private readonly ?string $mimeType = null,
        private readonly ?array $_meta = null,
        private readonly array $additionalProperties = []
    ) {}

    /**
     * Get the URI of this resource.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the MIME type of this resource, if known.
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Get metadata associated with this resource.
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
     * Create from an array of data.
     */
    abstract public static function fromArray(array $data): static;
}
