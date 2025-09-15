<?php

declare(strict_types=1);

namespace MCP\Types\Messages;

use MCP\Types\Requests;

/**
 * Union type helper for client requests.
 */
final class ClientRequest
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Get the list of valid client request methods.
     *
     * @return string[]
     */
    public static function getMethods(): array
    {
        return [
            Requests\PingRequest::METHOD,
            Requests\InitializeRequest::METHOD,
            Requests\CompleteRequest::METHOD,
            Requests\SetLevelRequest::METHOD,
            Requests\GetPromptRequest::METHOD,
            Requests\ListPromptsRequest::METHOD,
            Requests\ListResourcesRequest::METHOD,
            Requests\ListResourceTemplatesRequest::METHOD,
            Requests\ReadResourceRequest::METHOD,
            Requests\SubscribeRequest::METHOD,
            Requests\UnsubscribeRequest::METHOD,
            Requests\CallToolRequest::METHOD,
            Requests\ListToolsRequest::METHOD,
        ];
    }

    /**
     * Check if a method is a valid client request method.
     */
    public static function isValidMethod(string $method): bool
    {
        return in_array($method, self::getMethods(), true);
    }

    /**
     * Create a request from an array based on the method.
     *
     * @param array<string, mixed> $data
     *
     * @return Requests\PingRequest|Requests\InitializeRequest|Requests\CompleteRequest|Requests\SetLevelRequest|Requests\GetPromptRequest|Requests\ListPromptsRequest|Requests\ListResourcesRequest|Requests\ListResourceTemplatesRequest|Requests\ReadResourceRequest|Requests\SubscribeRequest|Requests\UnsubscribeRequest|Requests\CallToolRequest|Requests\ListToolsRequest
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): object
    {
        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new \InvalidArgumentException('Request must have a method property');
        }

        return match ($data['method']) {
            Requests\PingRequest::METHOD => Requests\PingRequest::fromArray($data),
            Requests\InitializeRequest::METHOD => Requests\InitializeRequest::fromArray($data),
            Requests\CompleteRequest::METHOD => Requests\CompleteRequest::fromArray($data),
            Requests\SetLevelRequest::METHOD => Requests\SetLevelRequest::fromArray($data),
            Requests\GetPromptRequest::METHOD => Requests\GetPromptRequest::fromArray($data),
            Requests\ListPromptsRequest::METHOD => Requests\ListPromptsRequest::fromArray($data),
            Requests\ListResourcesRequest::METHOD => Requests\ListResourcesRequest::fromArray($data),
            Requests\ListResourceTemplatesRequest::METHOD => Requests\ListResourceTemplatesRequest::fromArray($data),
            Requests\ReadResourceRequest::METHOD => Requests\ReadResourceRequest::fromArray($data),
            Requests\SubscribeRequest::METHOD => Requests\SubscribeRequest::fromArray($data),
            Requests\UnsubscribeRequest::METHOD => Requests\UnsubscribeRequest::fromArray($data),
            Requests\CallToolRequest::METHOD => Requests\CallToolRequest::fromArray($data),
            Requests\ListToolsRequest::METHOD => Requests\ListToolsRequest::fromArray($data),
            default => throw new \InvalidArgumentException('Unknown client request method: ' . $data['method']),
        };
    }
}
