<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;

/**
 * Sent from the server to request a list of root URIs from the client.
 */
final class ListRootsRequest extends Request
{
    public const METHOD = 'roots/list';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new list roots request.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Check if this is a valid list roots request.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        return is_array($value) && ($value['method'] ?? null) === self::METHOD;
    }
}
