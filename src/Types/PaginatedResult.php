<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Base class for paginated results.
 */
abstract class PaginatedResult extends Result
{
    /**
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly ?Cursor $nextCursor = null,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
    }

    /**
     * Get the next cursor for pagination.
     */
    public function getNextCursor(): ?Cursor
    {
        return $this->nextCursor;
    }

    /**
     * Check if there are more results available.
     */
    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * @return array{nextCursor?: string, _meta?: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->nextCursor !== null) {
            $data['nextCursor'] = $this->nextCursor->getValue();
        }

        return $data;
    }

    /**
     * Extract the next cursor from array data.
     */
    protected static function extractNextCursor(array $data): ?Cursor
    {
        if (isset($data['nextCursor']) && is_string($data['nextCursor'])) {
            return new Cursor($data['nextCursor']);
        }

        return null;
    }
}
