<?php

declare(strict_types=1);

namespace MCP\Types\Content;

/**
 * Interface for all content types that can be used in prompts and tool results.
 */
interface ContentBlock extends \JsonSerializable
{
    /**
     * Get the content type.
     */
    public function getType(): string;

    /**
     * Get metadata associated with this content, if any.
     *
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array;

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static;
}
