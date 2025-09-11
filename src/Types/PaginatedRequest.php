<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Base class for paginated requests.
 */
abstract class PaginatedRequest extends Request
{
    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(
        string $method,
        ?array $params = null
    ) {
        parent::__construct($method, $params);
    }

    /**
     * Get the cursor parameter.
     */
    public function getCursor(): ?Cursor
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['cursor'])) {
            return null;
        }

        if (is_string($params['cursor'])) {
            return new Cursor($params['cursor']);
        }

        return null;
    }

    /**
     * Create a new request with the given cursor.
     */
    public function withCursor(Cursor $cursor): static
    {
        $params = $this->getParams() ?? [];
        $params['cursor'] = $cursor->getValue();

        $class = static::class;
        return new $class($params);
    }

    /**
     * Create a new request without a cursor.
     */
    public function withoutCursor(): static
    {
        $params = $this->getParams() ?? [];
        unset($params['cursor']);

        $class = static::class;
        if (empty($params)) {
            return new $class(null);
        }

        return new $class($params);
    }

    /**
     * Check if a value is a valid paginated request.
     */
    public static function isValid(mixed $value): bool
    {
        return parent::isValid($value);
    }
}
