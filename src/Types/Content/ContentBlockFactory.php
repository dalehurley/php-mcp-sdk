<?php

declare(strict_types=1);

namespace MCP\Types\Content;

/**
 * Factory for creating ContentBlock instances from arrays.
 */
final class ContentBlockFactory
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Create a ContentBlock from an array of data.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): ContentBlock
    {
        if (!isset($data['type']) || !is_string($data['type'])) {
            throw new \InvalidArgumentException('ContentBlock must have a type property');
        }

        return match ($data['type']) {
            'text' => TextContent::fromArray($data),
            'image' => ImageContent::fromArray($data),
            'audio' => AudioContent::fromArray($data),
            'resource' => EmbeddedResource::fromArray($data),
            'resource_link' => ResourceLink::fromArray($data),
            default => throw new \InvalidArgumentException(sprintf('Unknown content block type: %s', $data['type'])),
        };
    }

    /**
     * Create multiple ContentBlocks from an array of data.
     *
     * @param array<array<string, mixed>> $dataArray
     * @return ContentBlock[]
     */
    public static function fromArrayMultiple(array $dataArray): array
    {
        return array_map(
            fn(array $data) => self::fromArray($data),
            $dataArray
        );
    }

    /**
     * Check if a value is a valid ContentBlock array.
     */
    public static function isValid(mixed $value): bool
    {
        if (!is_array($value) || !isset($value['type']) || !is_string($value['type'])) {
            return false;
        }

        return in_array($value['type'], ['text', 'image', 'audio', 'resource', 'resource_link'], true);
    }
}
