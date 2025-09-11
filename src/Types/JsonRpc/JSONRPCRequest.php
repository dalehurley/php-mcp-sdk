<?php

declare(strict_types=1);

namespace MCP\Types\JsonRpc;

use MCP\Types\Protocol;
use MCP\Types\Request;
use MCP\Types\RequestId;

/**
 * A request that expects a response in JSON-RPC format.
 */
final class JSONRPCRequest extends Request
{
    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(
        private readonly RequestId $id,
        string $method,
        ?array $params = null,
        private readonly string $jsonrpc = Protocol::JSONRPC_VERSION
    ) {
        parent::__construct($method, $params);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== Protocol::JSONRPC_VERSION) {
            throw new \InvalidArgumentException(
                sprintf('Invalid or missing jsonrpc version, expected %s', Protocol::JSONRPC_VERSION)
            );
        }

        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('JSONRPCRequest must have an id property');
        }

        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new \InvalidArgumentException('JSONRPCRequest must have a method property');
        }

        return new static(
            id: RequestId::from($data['id']),
            method: $data['method'],
            params: $data['params'] ?? null,
            jsonrpc: $data['jsonrpc']
        );
    }

    /**
     * Get the request ID.
     */
    public function getId(): RequestId
    {
        return $this->id;
    }

    /**
     * Get the JSON-RPC version.
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /**
     * Create a new request with a different ID.
     */
    public function withId(RequestId $id): self
    {
        return new self(
            id: $id,
            method: $this->getMethod(),
            params: $this->getParams(),
            jsonrpc: $this->jsonrpc
        );
    }

    /**
     * @return array{jsonrpc: string, id: string|int, method: string, params?: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        $data = [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id->jsonSerialize(),
            'method' => $this->getMethod(),
        ];

        if ($this->hasParams()) {
            $data['params'] = $this->getParams();
        }

        return $data;
    }

    /**
     * Check if a value is a valid JSONRPCRequest.
     */
    public static function isValid(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        return isset($value['jsonrpc'])
            && $value['jsonrpc'] === Protocol::JSONRPC_VERSION
            && isset($value['id'])
            && (is_string($value['id']) || is_int($value['id']))
            && isset($value['method'])
            && is_string($value['method']);
    }
}
