<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\PaginatedRequest;

/**
 * Sent from the client to request a list of resource templates the server has.
 */
final class ListResourceTemplatesRequest extends PaginatedRequest
{
    public const METHOD = 'resources/templates/list';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new list resource templates request.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Check if this is a valid list resource templates request.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        return is_array($value) && ($value['method'] ?? null) === self::METHOD;
    }
}
