<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Resources\BlobResourceContents;
use MCP\Types\Resources\ResourceContents;
use MCP\Types\Resources\TextResourceContents;
use MCP\Types\Result;

/**
 * The server's response to a resources/read request from the client.
 */
final class ReadResourceResult extends Result
{
    /**
     * @param array<TextResourceContents|BlobResourceContents> $contents
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $contents,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['contents']) || !is_array($data['contents'])) {
            throw new \InvalidArgumentException('ReadResourceResult must have a contents array');
        }

        $contents = array_map(function (array $item) {
            // Check if it's a text resource (has 'text' property)
            if (isset($item['text'])) {
                return TextResourceContents::fromArray($item);
            }
            // Check if it's a blob resource (has 'blob' property)
            if (isset($item['blob'])) {
                return BlobResourceContents::fromArray($item);
            }

            throw new \InvalidArgumentException('Invalid resource contents type');
        }, $data['contents']);

        return new self(
            contents: $contents,
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the resource contents.
     *
     * @return array<TextResourceContents|BlobResourceContents>
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * @return array{
     *     contents: array<array<string, mixed>>,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['contents'] = array_map(
            fn (ResourceContents $content) => $content->jsonSerialize(),
            $this->contents
        );

        return $data;
    }
}
