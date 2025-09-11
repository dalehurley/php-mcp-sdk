<?php

declare(strict_types=1);

namespace MCP\Types\JsonRpc;

use MCP\Types\Notification;
use MCP\Types\Protocol;

/**
 * A notification which does not expect a response in JSON-RPC format.
 */
final class JSONRPCNotification extends Notification
{
    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(
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

        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new \InvalidArgumentException('JSONRPCNotification must have a method property');
        }

        // Notifications should not have an id field
        if (isset($data['id'])) {
            throw new \InvalidArgumentException('JSONRPCNotification should not have an id property');
        }

        return new static(
            method: $data['method'],
            params: $data['params'] ?? null,
            jsonrpc: $data['jsonrpc']
        );
    }

    /**
     * Get the JSON-RPC version.
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /**
     * @return array{jsonrpc: string, method: string, params?: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        $data = [
            'jsonrpc' => $this->jsonrpc,
            'method' => $this->getMethod(),
        ];

        if ($this->hasParams()) {
            $data['params'] = $this->getParams();
        }

        return $data;
    }

    /**
     * Check if a value is a valid JSONRPCNotification.
     */
    public static function isValid(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        return isset($value['jsonrpc'])
            && $value['jsonrpc'] === Protocol::JSONRPC_VERSION
            && !isset($value['id'])
            && isset($value['method'])
            && is_string($value['method']);
    }
}
