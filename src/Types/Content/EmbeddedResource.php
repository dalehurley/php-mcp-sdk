<?php

declare(strict_types=1);

namespace MCP\Types\Content;

use MCP\Types\Resources\BlobResourceContents;
use MCP\Types\Resources\ResourceContents;
use MCP\Types\Resources\TextResourceContents;

/**
 * The contents of a resource, embedded into a prompt or tool call result.
 */
final class EmbeddedResource implements ContentBlock
{
    /**
     * @param TextResourceContents|BlobResourceContents $resource
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly TextResourceContents|BlobResourceContents $resource,
        private readonly ?array $_meta = null,
        private readonly array $additionalProperties = []
    ) {}

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['type']) || $data['type'] !== 'resource') {
            throw new \InvalidArgumentException('EmbeddedResource must have type "resource"');
        }

        if (!isset($data['resource']) || !is_array($data['resource'])) {
            throw new \InvalidArgumentException('EmbeddedResource must have a resource property');
        }

        $resourceData = $data['resource'];

        // Determine if it's text or blob resource contents
        if (isset($resourceData['text'])) {
            $resource = TextResourceContents::fromArray($resourceData);
        } elseif (isset($resourceData['blob'])) {
            $resource = BlobResourceContents::fromArray($resourceData);
        } else {
            throw new \InvalidArgumentException('Resource must be either TextResourceContents or BlobResourceContents');
        }

        $_meta = null;
        if (isset($data['_meta']) && is_array($data['_meta'])) {
            $_meta = $data['_meta'];
        }

        // Remove known properties to collect additional properties
        unset($data['type'], $data['resource'], $data['_meta']);

        return new static(
            resource: $resource,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the content type.
     */
    public function getType(): string
    {
        return 'resource';
    }

    /**
     * Get the embedded resource.
     *
     * @return TextResourceContents|BlobResourceContents
     */
    public function getResource(): TextResourceContents|BlobResourceContents
    {
        return $this->resource;
    }

    /**
     * Get metadata associated with this content.
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
     * Check if the embedded resource contains text.
     */
    public function isTextResource(): bool
    {
        return $this->resource instanceof TextResourceContents;
    }

    /**
     * Check if the embedded resource contains binary data.
     */
    public function isBlobResource(): bool
    {
        return $this->resource instanceof BlobResourceContents;
    }

    /**
     * @return array{type: string, resource: array<string, mixed>, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = array_merge($this->additionalProperties, [
            'type' => 'resource',
            'resource' => $this->resource->jsonSerialize(),
        ]);

        if ($this->_meta !== null) {
            $data['_meta'] = $this->_meta;
        }

        return $data;
    }
}
