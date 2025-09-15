<?php

declare(strict_types=1);

namespace MCP\Types\JsonRpc;

use MCP\Types\ErrorCode;
use MCP\Types\Protocol;
use MCP\Types\RequestId;

/**
 * A response to a request that indicates an error occurred in JSON-RPC format.
 */
final class JSONRPCError implements \JsonSerializable
{
    public function __construct(
        private readonly RequestId $id,
        private readonly int $code,
        private readonly string $message,
        private readonly mixed $data = null,
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
            throw new \InvalidArgumentException('JSONRPCError must have an id property');
        }

        if (!isset($data['error']) || !is_array($data['error'])) {
            throw new \InvalidArgumentException('JSONRPCError must have an error object');
        }

        $error = $data['error'];

        if (!isset($error['code']) || !is_int($error['code'])) {
            throw new \InvalidArgumentException('Error object must have an integer code');
        }

        if (!isset($error['message']) || !is_string($error['message'])) {
            throw new \InvalidArgumentException('Error object must have a string message');
        }

        return new self(
            id: RequestId::from($data['id']),
            code: $error['code'],
            message: $error['message'],
            data: $error['data'] ?? null,
            jsonrpc: $data['jsonrpc']
        );
    }

    /**
     * Create a parse error response.
     */
    public static function parseError(RequestId $id, ?string $message = null, mixed $data = null): self
    {
        return new self(
            id: $id,
            code: ErrorCode::ParseError->value,
            message: $message ?? 'Parse error',
            data: $data
        );
    }

    /**
     * Create an invalid request error response.
     */
    public static function invalidRequest(RequestId $id, ?string $message = null, mixed $data = null): self
    {
        return new self(
            id: $id,
            code: ErrorCode::InvalidRequest->value,
            message: $message ?? 'Invalid Request',
            data: $data
        );
    }

    /**
     * Create a method not found error response.
     */
    public static function methodNotFound(RequestId $id, ?string $message = null, mixed $data = null): self
    {
        return new self(
            id: $id,
            code: ErrorCode::MethodNotFound->value,
            message: $message ?? 'Method not found',
            data: $data
        );
    }

    /**
     * Create an invalid params error response.
     */
    public static function invalidParams(RequestId $id, ?string $message = null, mixed $data = null): self
    {
        return new self(
            id: $id,
            code: ErrorCode::InvalidParams->value,
            message: $message ?? 'Invalid params',
            data: $data
        );
    }

    /**
     * Create an internal error response.
     */
    public static function internalError(RequestId $id, ?string $message = null, mixed $data = null): self
    {
        return new self(
            id: $id,
            code: ErrorCode::InternalError->value,
            message: $message ?? 'Internal error',
            data: $data
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
     * Get the error code.
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Get the error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the error data.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Check if this error has additional data.
     */
    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * Get the JSON-RPC version.
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /**
     * @return array{jsonrpc: string, id: string|int, error: array{code: int, message: string, data?: mixed}}
     */
    public function jsonSerialize(): array
    {
        $error = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id->jsonSerialize(),
            'error' => $error,
        ];
    }

    /**
     * Check if a value is a valid JSONRPCError.
     */
    public static function isValid(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (!isset($value['jsonrpc']) || $value['jsonrpc'] !== Protocol::JSONRPC_VERSION) {
            return false;
        }

        if (!isset($value['id']) || (!is_string($value['id']) && !is_int($value['id']))) {
            return false;
        }

        if (!isset($value['error']) || !is_array($value['error'])) {
            return false;
        }

        $error = $value['error'];

        return isset($error['code'])
            && is_int($error['code'])
            && isset($error['message'])
            && is_string($error['message']);
    }
}
