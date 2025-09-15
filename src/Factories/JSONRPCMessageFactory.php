<?php

declare(strict_types=1);

namespace MCP\Factories;

use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\JsonRpc\JSONRPCMessage;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCResponse;
use MCP\Types\Protocol;
use MCP\Types\RequestId;
use MCP\Validation\ValidationException;
use MCP\Validation\ValidationService;

/**
 * Factory for creating JSON-RPC message instances.
 *
 * @extends AbstractTypeFactory<JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError>
 */
class JSONRPCMessageFactory extends AbstractTypeFactory
{
    public function __construct(
        ?ValidationService $validationService = null,
        private readonly ResultFactory $resultFactory = new ResultFactory()
    ) {
        parent::__construct($validationService);
    }

    /**
     * Create a JSON-RPC message from array data.
     *
     * @return JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
     *
     * @throws ValidationException
     */
    protected function createInstance(array $data): object
    {
        // Validate JSON-RPC version
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== Protocol::JSONRPC_VERSION) {
            throw new ValidationException(
                sprintf('Invalid or missing jsonrpc version, expected %s', Protocol::JSONRPC_VERSION)
            );
        }

        // Determine message type and create appropriate instance
        if (isset($data['method'])) {
            // It's either a request or notification
            if (isset($data['id'])) {
                return $this->createRequest($data);
            } else {
                return $this->createNotification($data);
            }
        } elseif (isset($data['id'])) {
            // It's either a response or error
            if (isset($data['result'])) {
                return $this->createResponse($data);
            } elseif (isset($data['error'])) {
                return $this->createError($data);
            }
        }

        throw new ValidationException('Invalid JSON-RPC message format');
    }

    /**
     * Create a JSON-RPC request.
     */
    private function createRequest(array $data): JSONRPCRequest
    {
        return new JSONRPCRequest(
            id: RequestId::from($data['id']),
            method: $data['method'],
            params: $this->getArray($data, 'params'),
            jsonrpc: $data['jsonrpc']
        );
    }

    /**
     * Create a JSON-RPC notification.
     */
    private function createNotification(array $data): JSONRPCNotification
    {
        return new JSONRPCNotification(
            method: $data['method'],
            params: $this->getArray($data, 'params'),
            jsonrpc: $data['jsonrpc']
        );
    }

    /**
     * Create a JSON-RPC response.
     */
    private function createResponse(array $data): JSONRPCResponse
    {
        $resultData = $this->getArray($data, 'result', []);
        $result = $this->resultFactory->create($resultData);

        return new JSONRPCResponse(
            id: RequestId::from($data['id']),
            result: $result,
            jsonrpc: $data['jsonrpc']
        );
    }

    /**
     * Create a JSON-RPC error.
     */
    private function createError(array $data): JSONRPCError
    {
        if (!is_array($data['error'])) {
            throw new ValidationException('Error must be an object');
        }

        $error = $data['error'];

        if (!isset($error['code']) || !is_int($error['code'])) {
            throw new ValidationException('Error code must be an integer');
        }

        if (!isset($error['message']) || !is_string($error['message'])) {
            throw new ValidationException('Error message must be a string');
        }

        return new JSONRPCError(
            id: RequestId::from($data['id']),
            code: $error['code'],
            message: $error['message'],
            data: $error['data'] ?? null,
            jsonrpc: $data['jsonrpc']
        );
    }

    /**
     * Parse a JSON-RPC message using the static helper.
     *
     * @return JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
     */
    public function parse(array $data): JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
    {
        return JSONRPCMessage::fromArray($data);
    }
}
