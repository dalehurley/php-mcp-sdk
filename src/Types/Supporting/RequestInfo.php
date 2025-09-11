<?php

declare(strict_types=1);

namespace MCP\Types\Supporting;

/**
 * Information about the incoming request.
 */
final class RequestInfo
{
    /**
     * @param array<string, string|string[]|null> $headers
     */
    public function __construct(
        private readonly array $headers
    ) {}

    /**
     * Get the headers of the request.
     *
     * @return array<string, string|string[]|null>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value.
     *
     * @return string|string[]|null
     */
    public function getHeader(string $name): string|array|null
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Check if a header exists.
     */
    public function hasHeader(string $name): bool
    {
        return array_key_exists($name, $this->headers);
    }

    /**
     * Create from an array of data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['headers']) || !is_array($data['headers'])) {
            throw new \InvalidArgumentException('RequestInfo must have a headers property');
        }

        return new self($data['headers']);
    }
}
