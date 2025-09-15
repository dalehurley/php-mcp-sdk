<?php

declare(strict_types=1);

namespace MCP\Types\Messages;

use MCP\Types\Requests;

/**
 * Union type helper for server requests.
 */
final class ServerRequest
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Get the list of valid server request methods.
     *
     * @return string[]
     */
    public static function getMethods(): array
    {
        return [
            Requests\PingRequest::METHOD,
            Requests\CreateMessageRequest::METHOD,
            Requests\ElicitRequest::METHOD,
            Requests\ListRootsRequest::METHOD,
        ];
    }

    /**
     * Check if a method is a valid server request method.
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
     * @return Requests\PingRequest|Requests\CreateMessageRequest|Requests\ElicitRequest|Requests\ListRootsRequest
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
            Requests\CreateMessageRequest::METHOD => Requests\CreateMessageRequest::fromArray($data),
            Requests\ElicitRequest::METHOD => Requests\ElicitRequest::fromArray($data),
            Requests\ListRootsRequest::METHOD => Requests\ListRootsRequest::fromArray($data),
            default => throw new \InvalidArgumentException('Unknown server request method: ' . $data['method']),
        };
    }
}
