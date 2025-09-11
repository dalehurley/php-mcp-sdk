<?php

declare(strict_types=1);

namespace MCP\Types\Messages;

use MCP\Types\Results;
use MCP\Types\EmptyResult;

/**
 * Union type helper for client results.
 */
final class ClientResult
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Create a result from an array.
     *
     * @param array<string, mixed> $data
     * @return EmptyResult|Results\CreateMessageResult|Results\ElicitResult|Results\ListRootsResult
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): object
    {
        // Check for specific result types based on data structure

        // CreateMessageResult has 'model' and 'content' fields
        if (isset($data['model']) && isset($data['content'])) {
            return Results\CreateMessageResult::fromArray($data);
        }

        // ElicitResult has 'action' field
        if (isset($data['action'])) {
            return Results\ElicitResult::fromArray($data);
        }

        // ListRootsResult has 'roots' field
        if (isset($data['roots'])) {
            return Results\ListRootsResult::fromArray($data);
        }

        // If none of the above, it's likely an EmptyResult
        // EmptyResult has no specific fields, just optional _meta
        return EmptyResult::fromArray($data);
    }

    /**
     * Check if a result is empty.
     */
    public static function isEmpty(object $result): bool
    {
        return $result instanceof EmptyResult;
    }
}
