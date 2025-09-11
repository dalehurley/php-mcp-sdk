<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * A notification which does not expect a response.
 */
class Notification implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(
        private readonly string $method,
        private readonly ?array $params = null
    ) {}

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new \InvalidArgumentException('Notification must have a method property');
        }

        return new static(
            method: $data['method'],
            params: $data['params'] ?? null
        );
    }

    /**
     * Get the method name.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the parameters.
     *
     * @return array<string, mixed>|null
     */
    public function getParams(): ?array
    {
        return $this->params;
    }

    /**
     * Check if the notification has parameters.
     */
    public function hasParams(): bool
    {
        return $this->params !== null;
    }

    /**
     * Get a specific parameter value.
     */
    public function getParam(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Get the _meta field from params, if present.
     *
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        if ($this->params === null || !isset($this->params['_meta'])) {
            return null;
        }

        return is_array($this->params['_meta']) ? $this->params['_meta'] : null;
    }

    /**
     * Create a new notification with updated parameters.
     */
    public function withParams(array $params): static
    {
        return new static(
            method: $this->method,
            params: $params
        );
    }

    /**
     * @return array{method: string, params?: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        $data = ['method' => $this->method];

        if ($this->params !== null) {
            $data['params'] = $this->params;
        }

        return $data;
    }
}
