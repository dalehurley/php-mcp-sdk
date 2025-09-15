<?php

declare(strict_types=1);

namespace MCP\Types\Messages;

use MCP\Types\EmptyResult;
use MCP\Types\Results;

/**
 * Union type helper for server results.
 */
final class ServerResult
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Create a result from an array.
     *
     * @param array<string, mixed> $data
     *
     * @return EmptyResult|Results\InitializeResult|Results\CompleteResult|Results\GetPromptResult|Results\ListPromptsResult|Results\ListResourcesResult|Results\ListResourceTemplatesResult|Results\ReadResourceResult|Results\CallToolResult|Results\ListToolsResult
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): object
    {
        // Check for specific result types based on data structure

        // InitializeResult has 'protocolVersion', 'capabilities', and 'serverInfo' fields
        if (isset($data['protocolVersion']) && isset($data['capabilities']) && isset($data['serverInfo'])) {
            return Results\InitializeResult::fromArray($data);
        }

        // CompleteResult has 'completion' field
        if (isset($data['completion'])) {
            return Results\CompleteResult::fromArray($data);
        }

        // GetPromptResult has 'messages' field
        if (isset($data['messages'])) {
            return Results\GetPromptResult::fromArray($data);
        }

        // ListPromptsResult has 'prompts' field
        if (isset($data['prompts'])) {
            return Results\ListPromptsResult::fromArray($data);
        }

        // ListResourcesResult has 'resources' field
        if (isset($data['resources'])) {
            return Results\ListResourcesResult::fromArray($data);
        }

        // ListResourceTemplatesResult has 'resourceTemplates' field
        if (isset($data['resourceTemplates'])) {
            return Results\ListResourceTemplatesResult::fromArray($data);
        }

        // ReadResourceResult has 'contents' field (array)
        if (isset($data['contents']) && is_array($data['contents'])) {
            return Results\ReadResourceResult::fromArray($data);
        }

        // CallToolResult has 'content' field or legacy 'toolResult' field
        if (isset($data['content']) || isset($data['toolResult'])) {
            return Results\CallToolResult::fromArray($data);
        }

        // ListToolsResult has 'tools' field
        if (isset($data['tools'])) {
            return Results\ListToolsResult::fromArray($data);
        }

        // If none of the above, it's likely an EmptyResult
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
