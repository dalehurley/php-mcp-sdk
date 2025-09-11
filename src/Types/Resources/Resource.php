<?php

declare(strict_types=1);

namespace MCP\Types\Resources;

use MCP\Types\BaseMetadata;

/**
 * A known resource that the server is capable of reading.
 */
class Resource extends BaseMetadata
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        string $name,
        private readonly string $uri,
        ?string $title = null,
        private readonly ?string $description = null,
        private readonly ?string $mimeType = null,
        ?array $_meta = null,
        array $additionalProperties = []
    ) {
        parent::__construct($name, $title, $_meta, $additionalProperties);
        $this->uri = $uri;
        $this->description = $description;
        $this->mimeType = $mimeType;
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('Resource must have a name property');
        }

        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException('Resource must have a uri property');
        }

        $name = $data['name'];
        $uri = $data['uri'];
        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $mimeType = isset($data['mimeType']) && is_string($data['mimeType']) ? $data['mimeType'] : null;
        $_meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;

        // Remove known properties to collect additional properties
        unset($data['name'], $data['uri'], $data['title'], $data['description'], $data['mimeType'], $data['_meta']);

        return new static(
            name: $name,
            uri: $uri,
            title: $title,
            description: $description,
            mimeType: $mimeType,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the URI of this resource.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the description of what this resource represents.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the MIME type of this resource, if known.
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * @return array{name: string, uri: string, title?: string, description?: string, mimeType?: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['uri'] = $this->uri;

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }

        return $data;
    }
}
