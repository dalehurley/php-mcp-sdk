<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\PaginatedRequest;

/**
 * Sent from the client to request a list of resources the server has.
 */
final class ListResourcesRequest extends PaginatedRequest
{
    public const METHOD = 'resources/list';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new list resources request.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        return new static($data['params'] ?? null);
    }

    /**
     * Check if this is a valid list resources request.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        return is_array($value) && ($value['method'] ?? null) === self::METHOD;
    }
}
