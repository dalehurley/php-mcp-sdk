<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Cursor;
use MCP\Types\PaginatedResult;
use MCP\Types\Resources\Resource;

/**
 * The server's response to a resources/list request from the client.
 */
final class ListResourcesResult extends PaginatedResult
{
    /**
     * @param resource[] $resources
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $resources,
        ?Cursor $nextCursor = null,
        ?array $_meta = null
    ) {
        parent::__construct($nextCursor, $_meta);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['resources']) || !is_array($data['resources'])) {
            throw new \InvalidArgumentException('ListResourcesResult must have a resources array');
        }

        $resources = array_map(
            fn (array $item) => Resource::fromArray($item),
            $data['resources']
        );

        return new self(
            resources: $resources,
            nextCursor: self::extractNextCursor($data),
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the resources.
     *
     * @return resource[]
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * @return array{
     *     resources: array<array{
     *         name: string,
     *         uri: string,
     *         title?: string,
     *         description?: string,
     *         mimeType?: string,
     *         _meta?: array<string, mixed>
     *     }>,
     *     nextCursor?: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['resources'] = array_map(
            fn (Resource $resource) => $resource->jsonSerialize(),
            $this->resources
        );

        return $data;
    }
}
