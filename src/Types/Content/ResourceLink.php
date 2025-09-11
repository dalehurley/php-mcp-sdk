<?php

declare(strict_types=1);

namespace MCP\Types\Content;

use MCP\Types\Resources\Resource;

/**
 * A resource that the server is capable of reading, included in a prompt or tool call result.
 * Note: resource links returned by tools are not guaranteed to appear in the results of resources/list requests.
 */
final class ResourceLink extends Resource implements ContentBlock
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        string $name,
        string $uri,
        ?string $title = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?array $_meta = null,
        array $additionalProperties = []
    ) {
        parent::__construct(
            name: $name,
            uri: $uri,
            title: $title,
            description: $description,
            mimeType: $mimeType,
            _meta: $_meta,
            additionalProperties: $additionalProperties
        );
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['type']) || $data['type'] !== 'resource_link') {
            throw new \InvalidArgumentException('ResourceLink must have type "resource_link"');
        }

        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('ResourceLink must have a name property');
        }

        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException('ResourceLink must have a uri property');
        }

        $name = $data['name'];
        $uri = $data['uri'];
        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $mimeType = isset($data['mimeType']) && is_string($data['mimeType']) ? $data['mimeType'] : null;
        $_meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;

        // Remove known properties to collect additional properties
        unset($data['type'], $data['name'], $data['uri'], $data['title'], $data['description'], $data['mimeType'], $data['_meta']);

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
     * Get the content type.
     */
    public function getType(): string
    {
        return 'resource_link';
    }

    /**
     * @return array{type: string, name: string, uri: string, title?: string, description?: string, mimeType?: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        // Add type as the first property
        return array_merge(['type' => 'resource_link'], $data);
    }
}
