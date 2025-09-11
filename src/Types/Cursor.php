<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * An opaque token used to represent a cursor for pagination.
 */
final class Cursor implements \JsonSerializable, \Stringable
{
    public function __construct(
        private readonly string $value
    ) {
        if ($value === '') {
            throw new \InvalidArgumentException('Cursor value cannot be empty');
        }
    }

    /**
     * Create a new cursor from a string value.
     */
    public static function from(string $value): self
    {
        return new self($value);
    }

    /**
     * Get the underlying cursor value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Convert to string representation.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another cursor.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
