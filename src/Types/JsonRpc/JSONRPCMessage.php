<?php

declare(strict_types=1);

namespace MCP\Types\JsonRpc;

/**
 * Helper class for working with JSON-RPC messages.
 * Represents the union of all JSON-RPC message types.
 */
final class JSONRPCMessage
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Parse a JSON-RPC message from an array.
     *
     * @return JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
    {
        // Check if it's a request
        if (JSONRPCRequest::isValid($data)) {
            return JSONRPCRequest::fromArray($data);
        }

        // Check if it's a notification
        if (JSONRPCNotification::isValid($data)) {
            return JSONRPCNotification::fromArray($data);
        }

        // Check if it's a response
        if (JSONRPCResponse::isValid($data)) {
            return JSONRPCResponse::fromArray($data);
        }

        // Check if it's an error
        if (JSONRPCError::isValid($data)) {
            return JSONRPCError::fromArray($data);
        }

        throw new \InvalidArgumentException('Invalid JSON-RPC message format');
    }

    /**
     * Check if a value is a valid JSON-RPC message.
     */
    public static function isValid(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        return JSONRPCRequest::isValid($value)
            || JSONRPCNotification::isValid($value)
            || JSONRPCResponse::isValid($value)
            || JSONRPCError::isValid($value);
    }

    /**
     * Check if a message is a request.
     */
    public static function isRequest(mixed $value): bool
    {
        return $value instanceof JSONRPCRequest || (is_array($value) && JSONRPCRequest::isValid($value));
    }

    /**
     * Check if a message is a notification.
     */
    public static function isNotification(mixed $value): bool
    {
        return $value instanceof JSONRPCNotification || (is_array($value) && JSONRPCNotification::isValid($value));
    }

    /**
     * Check if a message is a response.
     */
    public static function isResponse(mixed $value): bool
    {
        return $value instanceof JSONRPCResponse || (is_array($value) && JSONRPCResponse::isValid($value));
    }

    /**
     * Check if a message is an error.
     */
    public static function isError(mixed $value): bool
    {
        return $value instanceof JSONRPCError || (is_array($value) && JSONRPCError::isValid($value));
    }
}
