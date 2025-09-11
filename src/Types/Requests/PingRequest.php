<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;

/**
 * A ping, issued by either the server or the client, to check that the other
 * party is still alive. The receiver must promptly respond, or else may be disconnected.
 */
final class PingRequest extends Request
{
    public const METHOD = 'ping';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new ping request.
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
     * Check if this is a valid ping request.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        return is_array($value) && ($value['method'] ?? null) === self::METHOD;
    }
}
