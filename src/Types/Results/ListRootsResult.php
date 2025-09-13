<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Result;
use MCP\Types\Root;

/**
 * The client's response to a roots/list request from the server.
 */
final class ListRootsResult extends Result
{
    /**
     * @param Root[] $roots
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $roots,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['roots']) || !is_array($data['roots'])) {
            throw new \InvalidArgumentException('ListRootsResult must have a roots array');
        }

        $roots = array_map(
            fn(array $item) => Root::fromArray($item),
            $data['roots']
        );

        return new self(
            roots: $roots,
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the roots.
     *
     * @return Root[]
     */
    public function getRoots(): array
    {
        return $this->roots;
    }

    /**
     * @return array{
     *     roots: array<array{
     *         uri: string,
     *         name?: string,
     *         _meta?: array<string, mixed>
     *     }>,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['roots'] = array_map(
            fn(Root $root) => $root->jsonSerialize(),
            $this->roots
        );

        return $data;
    }
}
