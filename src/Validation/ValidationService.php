<?php

declare(strict_types=1);

namespace MCP\Validation;

use MCP\Validation\Types\ContentBlockValidator;
use MCP\Validation\Types\CreateMessageResultValidator;
use MCP\Validation\Types\CursorValidator;
use MCP\Validation\Types\ElicitResultValidator;
use MCP\Validation\Types\JSONRPCRequestValidator;
use MCP\Validation\Types\ListRootsResultValidator;
use MCP\Validation\Types\ProgressTokenValidator;
use MCP\Validation\Types\RequestIdValidator;
use MCP\Validation\Types\ToolValidator;

/**
 * Central validation service for MCP types.
 */
class ValidationService
{
    /**
     * @var array<string, TypeValidator>
     */
    private array $validators = [];

    public function __construct()
    {
        $this->registerDefaultValidators();
    }

    /**
     * Register the default validators.
     */
    private function registerDefaultValidators(): void
    {
        // Base types
        $this->registerValidator('progressToken', new ProgressTokenValidator());
        $this->registerValidator('requestId', new RequestIdValidator());
        $this->registerValidator('cursor', new CursorValidator());

        // JSON-RPC types
        $this->registerValidator('jsonrpcRequest', new JSONRPCRequestValidator());

        // Content types
        $this->registerValidator('contentBlock', new ContentBlockValidator());

        // Tool types
        $this->registerValidator('tool', new ToolValidator());

        // Result types
        $this->registerValidator('createMessageResult', new CreateMessageResultValidator());
        $this->registerValidator('elicitResult', new ElicitResultValidator());
        $this->registerValidator('listRootsResult', new ListRootsResultValidator());
    }

    /**
     * Register a validator for a type.
     */
    public function registerValidator(string $type, TypeValidator $validator): void
    {
        $this->validators[$type] = $validator;
    }

    /**
     * Get a validator for a type.
     *
     * @throws \InvalidArgumentException if validator not found
     */
    public function getValidator(string $type): TypeValidator
    {
        if (!isset($this->validators[$type])) {
            throw new \InvalidArgumentException("No validator registered for type: $type");
        }

        return $this->validators[$type];
    }

    /**
     * Validate data against a type.
     *
     * @param mixed $data
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(string $type, mixed $data): array
    {
        return $this->getValidator($type)->validate($data);
    }

    /**
     * Check if data is valid for a type.
     */
    public function isValid(string $type, mixed $data): bool
    {
        try {
            return $this->getValidator($type)->isValid($data);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Validate progress token.
     *
     * @param mixed $data
     *
     * @throws ValidationException
     */
    public function validateProgressToken(mixed $data): string|int
    {
        $this->validate('progressToken', $data);

        return $data;
    }

    /**
     * Validate request ID.
     *
     * @param mixed $data
     *
     * @throws ValidationException
     */
    public function validateRequestId(mixed $data): string|int
    {
        $this->validate('requestId', $data);

        return $data;
    }

    /**
     * Validate cursor.
     *
     * @throws ValidationException
     */
    public function validateCursor(mixed $data): string
    {
        $this->validate('cursor', $data);

        return $data;
    }

    /**
     * Validate JSON-RPC request.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateJSONRPCRequest(mixed $data): array
    {
        return $this->validate('jsonrpcRequest', $data);
    }

    /**
     * Validate content block.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateContentBlock(mixed $data): array
    {
        return $this->validate('contentBlock', $data);
    }

    /**
     * Validate tool.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateTool(mixed $data): array
    {
        return $this->validate('tool', $data);
    }

    /**
     * Validate create message result.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateCreateMessageResult(mixed $data): array
    {
        return $this->validate('createMessageResult', $data);
    }

    /**
     * Validate elicit result.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateElicitResult(mixed $data): array
    {
        return $this->validate('elicitResult', $data);
    }

    /**
     * Validate list roots result.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateListRootsResult(mixed $data): array
    {
        return $this->validate('listRootsResult', $data);
    }
}
