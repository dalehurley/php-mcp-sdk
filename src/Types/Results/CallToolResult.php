<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Result;
use MCP\Types\Content\ContentBlock;
use MCP\Types\Content\ContentBlockFactory;

/**
 * The server's response to a tool call.
 */
final class CallToolResult extends Result
{
    /**
     * @param ContentBlock[] $content
     * @param array<string, mixed>|null $structuredContent
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $content = [],
        private readonly ?array $structuredContent = null,
        private readonly bool $isError = false,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        // Handle backwards compatibility with protocol version 2024-10-07
        if (isset($data['toolResult'])) {
            // Legacy format - convert to new format
            return new self(
                content: [], // Legacy format doesn't have content
                structuredContent: is_array($data['toolResult']) ? $data['toolResult'] : null,
                isError: false,
                _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
            );
        }

        $content = [];
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $item) {
                if (is_array($item)) {
                    $content[] = ContentBlockFactory::fromArray($item);
                }
            }
        }

        return new self(
            content: $content,
            structuredContent: isset($data['structuredContent']) && is_array($data['structuredContent'])
                ? $data['structuredContent']
                : null,
            isError: isset($data['isError']) && is_bool($data['isError'])
                ? $data['isError']
                : false,
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the content blocks.
     *
     * @return ContentBlock[]
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * Get the structured content.
     *
     * @return array<string, mixed>|null
     */
    public function getStructuredContent(): ?array
    {
        return $this->structuredContent;
    }

    /**
     * Check if the tool call ended in an error.
     */
    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * @return array{
     *     content: array<array<string, mixed>>,
     *     structuredContent?: array<string, mixed>,
     *     isError?: bool,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        // Content is always present, but may be empty
        $data['content'] = array_map(
            fn(ContentBlock $block) => $block->jsonSerialize(),
            $this->content
        );

        if ($this->structuredContent !== null) {
            $data['structuredContent'] = $this->structuredContent;
        }

        if ($this->isError) {
            $data['isError'] = true;
        }

        return $data;
    }
}
