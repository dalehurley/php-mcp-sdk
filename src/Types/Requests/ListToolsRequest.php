<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\PaginatedRequest;

/**
 * Sent from the client to request a list of tools the server has.
 */
final class ListToolsRequest extends PaginatedRequest
{
    public const METHOD = 'tools/list';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new list tools request.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Check if this is a valid list tools request.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        return is_array($value) && ($value['method'] ?? null) === self::METHOD;
    }
}
