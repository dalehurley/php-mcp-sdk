<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Cursor;
use MCP\Types\PaginatedResult;
use MCP\Types\Tools\Tool;

/**
 * The server's response to a tools/list request from the client.
 */
final class ListToolsResult extends PaginatedResult
{
    /**
     * @param Tool[] $tools
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $tools,
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
        if (!isset($data['tools']) || !is_array($data['tools'])) {
            throw new \InvalidArgumentException('ListToolsResult must have a tools array');
        }

        $tools = array_map(
            fn (array $item) => Tool::fromArray($item),
            $data['tools']
        );

        return new self(
            tools: $tools,
            nextCursor: self::extractNextCursor($data),
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the tools.
     *
     * @return Tool[]
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * @return array{
     *     tools: array<array{
     *         name: string,
     *         title?: string,
     *         description?: string,
     *         inputSchema: array<string, mixed>,
     *         outputSchema?: array<string, mixed>,
     *         annotations?: array<string, mixed>,
     *         _meta?: array<string, mixed>
     *     }>,
     *     nextCursor?: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['tools'] = array_map(
            fn (Tool $tool) => $tool->jsonSerialize(),
            $this->tools
        );

        return $data;
    }
}
