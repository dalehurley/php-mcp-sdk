<?php

declare(strict_types=1);

namespace MCP\Types\Resources;

use MCP\Types\BaseMetadata;

/**
 * A template description for resources available on the server.
 */
final class ResourceTemplate extends BaseMetadata
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        string $name,
        private readonly string $uriTemplate,
        ?string $title = null,
        private readonly ?string $description = null,
        private readonly ?string $mimeType = null,
        ?array $_meta = null,
        array $additionalProperties = []
    ) {
        parent::__construct($name, $title, $_meta, $additionalProperties);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('ResourceTemplate must have a name property');
        }

        if (!isset($data['uriTemplate']) || !is_string($data['uriTemplate'])) {
            throw new \InvalidArgumentException('ResourceTemplate must have a uriTemplate property');
        }

        $name = $data['name'];
        $uriTemplate = $data['uriTemplate'];
        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $mimeType = isset($data['mimeType']) && is_string($data['mimeType']) ? $data['mimeType'] : null;
        $_meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;

        // Remove known properties to collect additional properties
        unset($data['name'], $data['uriTemplate'], $data['title'], $data['description'], $data['mimeType'], $data['_meta']);

        return new static(
            name: $name,
            uriTemplate: $uriTemplate,
            title: $title,
            description: $description,
            mimeType: $mimeType,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the URI template (according to RFC 6570).
     */
    public function getUriTemplate(): string
    {
        return $this->uriTemplate;
    }

    /**
     * Get the description of what this template is for.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the MIME type for all resources that match this template.
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * @return array{name: string, uriTemplate: string, title?: string, description?: string, mimeType?: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['uriTemplate'] = $this->uriTemplate;

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }

        return $data;
    }
}
