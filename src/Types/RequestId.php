<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * A uniquely identifying ID for a request in JSON-RPC.
 * Can be either a string or an integer.
 */
final class RequestId implements \JsonSerializable
{
    /**
     * @param string|int $value
     */
    public function __construct(
        private readonly string|int $value
    ) {}

    /**
     * Create from a string value.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Create from an integer value.
     */
    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    /**
     * Create from a mixed value, validating the type.
     *
     * @throws \InvalidArgumentException
     */
    public static function from(mixed $value): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_int($value)) {
            return self::fromInt($value);
        }

        throw new \InvalidArgumentException(
            sprintf('RequestId must be a string or integer, %s given', gettype($value))
        );
    }

    /**
     * Get the underlying value.
     *
     * @return string|int
     */
    public function getValue(): string|int
    {
        return $this->value;
    }

    /**
     * Check if the ID is a string.
     */
    public function isString(): bool
    {
        return is_string($this->value);
    }

    /**
     * Check if the ID is an integer.
     */
    public function isInt(): bool
    {
        return is_int($this->value);
    }

    /**
     * Convert to string representation.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }

    /**
     * @return string|int
     */
    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    /**
     * Check equality with another request ID.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
