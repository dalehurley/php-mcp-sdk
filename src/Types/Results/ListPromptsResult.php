<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\PaginatedResult;
use MCP\Types\Prompts\Prompt;
use MCP\Types\Cursor;

/**
 * The server's response to a prompts/list request from the client.
 */
final class ListPromptsResult extends PaginatedResult
{
    /**
     * @param Prompt[] $prompts
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $prompts,
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
        if (!isset($data['prompts']) || !is_array($data['prompts'])) {
            throw new \InvalidArgumentException('ListPromptsResult must have a prompts array');
        }

        $prompts = array_map(
            fn(array $item) => Prompt::fromArray($item),
            $data['prompts']
        );

        return new self(
            prompts: $prompts,
            nextCursor: self::extractNextCursor($data),
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the prompts.
     *
     * @return Prompt[]
     */
    public function getPrompts(): array
    {
        return $this->prompts;
    }

    /**
     * @return array{
     *     prompts: array<array{
     *         name: string,
     *         title?: string,
     *         description?: string,
     *         arguments?: array<array{name: string, description?: string, required?: bool}>,
     *         _meta?: array<string, mixed>
     *     }>,
     *     nextCursor?: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['prompts'] = array_map(
            fn(Prompt $prompt) => $prompt->jsonSerialize(),
            $this->prompts
        );
        
        return $data;
    }
}
