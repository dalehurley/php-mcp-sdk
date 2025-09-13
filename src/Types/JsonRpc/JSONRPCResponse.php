<?php

declare(strict_types=1);

namespace MCP\Types\JsonRpc;

use MCP\Types\Protocol;
use MCP\Types\RequestId;
use MCP\Types\Result;

/**
 * A successful (non-error) response to a request in JSON-RPC format.
 */
final class JSONRPCResponse implements \JsonSerializable
{
    public function __construct(
        private readonly RequestId $id,
        private readonly Result $result,
        private readonly string $jsonrpc = Protocol::JSONRPC_VERSION
    ) {
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== Protocol::JSONRPC_VERSION) {
            throw new \InvalidArgumentException(
                sprintf('Invalid or missing jsonrpc version, expected %s', Protocol::JSONRPC_VERSION)
            );
        }

        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('JSONRPCResponse must have an id property');
        }

        if (!isset($data['result'])) {
            throw new \InvalidArgumentException('JSONRPCResponse must have a result property');
        }

        if (!is_array($data['result'])) {
            throw new \InvalidArgumentException('JSONRPCResponse result must be an object');
        }

        return new self(
            id: RequestId::from($data['id']),
            result: Result::fromArray($data['result']),
            jsonrpc: $data['jsonrpc']
        );
    }

    /**
     * Create a response with a custom result type.
     */
    public static function create(RequestId $id, Result $result): self
    {
        return new self(
            id: $id,
            result: $result
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
     * Get the result.
     */
    public function getResult(): Result
    {
        return $this->result;
    }

    /**
     * Get the JSON-RPC version.
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /**
     * @return array{jsonrpc: string, id: string|int, result: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id->jsonSerialize(),
            'result' => $this->result->jsonSerialize(),
        ];
    }

    /**
     * Check if a value is a valid JSONRPCResponse.
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
            && isset($value['result'])
            && is_array($value['result']);
    }
}
