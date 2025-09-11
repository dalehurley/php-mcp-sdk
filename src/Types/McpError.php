<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * MCP protocol error.
 */
class McpError extends \Exception
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly mixed $data = null
    ) {
        parent::__construct("MCP error {$errorCode->value}: {$message}", $errorCode->value);
    }

    /**
     * Create an error from a JSON-RPC error response.
     * @param array{code?: int, message?: string, data?: mixed} $error
     */
    public static function fromJsonRpcError(array $error): self
    {
        $code = ErrorCode::tryFrom($error['code'] ?? -32603) ?? ErrorCode::InternalError;

        return new self(
            $code,
            $error['message'] ?? 'Unknown error',
            $error['data'] ?? null
        );
    }

    /**
     * Convert to JSON-RPC error format.
     * @return array{code: int, message: string, data?: mixed}
     */
    public function toJsonRpcError(): array
    {
        $error = [
            'code' => $this->errorCode->value,
            'message' => $this->getMessage(),
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return $error;
    }
}
