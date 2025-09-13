<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\PaginatedResult;
use MCP\Types\Resources\ResourceTemplate;
use MCP\Types\Cursor;

/**
 * The server's response to a resources/templates/list request from the client.
 */
final class ListResourceTemplatesResult extends PaginatedResult
{
    /**
     * @param ResourceTemplate[] $resourceTemplates
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $resourceTemplates,
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
        if (!isset($data['resourceTemplates']) || !is_array($data['resourceTemplates'])) {
            throw new \InvalidArgumentException('ListResourceTemplatesResult must have a resourceTemplates array');
        }

        $resourceTemplates = array_map(
            fn(array $item) => ResourceTemplate::fromArray($item),
            $data['resourceTemplates']
        );

        return new self(
            resourceTemplates: $resourceTemplates,
            nextCursor: self::extractNextCursor($data),
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the resource templates.
     *
     * @return ResourceTemplate[]
     */
    public function getResourceTemplates(): array
    {
        return $this->resourceTemplates;
    }

    /**
     * @return array{
     *     resourceTemplates: array<array{
     *         name: string,
     *         uriTemplate: string,
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
        $data['resourceTemplates'] = array_map(
            fn(ResourceTemplate $template) => $template->jsonSerialize(),
            $this->resourceTemplates
        );

        return $data;
    }
}
