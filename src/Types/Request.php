<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Base request class for MCP requests.
 */
class Request implements \JsonSerializable
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
            throw new \InvalidArgumentException('Request must have a method property');
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
     * Check if the request has parameters.
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
     */
    public function getMeta(): ?RequestMeta
    {
        if ($this->params === null || !isset($this->params['_meta'])) {
            return null;
        }

        if (is_array($this->params['_meta'])) {
            return RequestMeta::fromArray($this->params['_meta']);
        }

        return null;
    }

    /**
     * Create a new request with updated parameters.
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
