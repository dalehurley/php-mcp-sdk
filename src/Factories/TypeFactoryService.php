<?php

declare(strict_types=1);

namespace MCP\Factories;

use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Content\ContentBlockFactory;
use MCP\Types\Cursor;
use MCP\Types\Implementation;
use MCP\Types\LoggingLevel;
use MCP\Types\ProgressToken;
use MCP\Types\Prompts\Prompt;
use MCP\Types\Prompts\PromptArgument;
use MCP\Types\Prompts\PromptMessage;
use MCP\Types\RequestId;
use MCP\Types\Resources\Resource;
use MCP\Types\Resources\ResourceTemplate;
use MCP\Types\Root;
use MCP\Types\Sampling\ModelHint;
use MCP\Types\Sampling\ModelPreferences;
use MCP\Types\Sampling\SamplingMessage;
use MCP\Validation\ValidationService;

/**
 * Central factory service for creating MCP type instances.
 * Provides convenient methods for creating all MCP types with validation.
 */
class TypeFactoryService
{
    private readonly JSONRPCMessageFactory $jsonrpcFactory;

    private readonly ToolFactory $toolFactory;

    private readonly ResultFactory $resultFactory;

    public function __construct(
        private readonly ?ValidationService $validationService = null
    ) {
        $this->jsonrpcFactory = new JSONRPCMessageFactory($validationService);
        $this->toolFactory = new ToolFactory($validationService);
        $this->resultFactory = new ResultFactory($validationService);
    }

    /**
     * Create a ProgressToken from a value.
     *
     * @param string|int $value
     */
    public function createProgressToken(string|int $value): ProgressToken
    {
        return ProgressToken::from($value);
    }

    /**
     * Create a Cursor from a string.
     */
    public function createCursor(string $value): Cursor
    {
        return Cursor::from($value);
    }

    /**
     * Create a RequestId from a value.
     *
     * @param string|int $value
     */
    public function createRequestId(string|int $value): RequestId
    {
        return RequestId::from($value);
    }

    /**
     * Parse a JSON-RPC message.
     *
     * @param array<string, mixed> $data
     *
     * @return \MCP\Types\JsonRpc\JSONRPCRequest|\MCP\Types\JsonRpc\JSONRPCNotification|\MCP\Types\JsonRpc\JSONRPCResponse|\MCP\Types\JsonRpc\JSONRPCError
     */
    public function parseJSONRPCMessage(array $data): object
    {
        return $this->jsonrpcFactory->create($data);
    }

    /**
     * Create a Tool from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createTool(array $data): \MCP\Types\Tools\Tool
    {
        return $this->toolFactory->create($data);
    }

    /**
     * Create a Result from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createResult(array $data): \MCP\Types\Result
    {
        return $this->resultFactory->create($data);
    }

    /**
     * Create an EmptyResult.
     *
     * @param array<string, mixed>|null $_meta
     */
    public function createEmptyResult(?array $_meta = null): \MCP\Types\EmptyResult
    {
        return $this->resultFactory->createEmpty($_meta);
    }

    /**
     * Create a ContentBlock from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createContentBlock(array $data): \MCP\Types\Content\ContentBlock
    {
        return ContentBlockFactory::fromArray($data);
    }

    /**
     * Create multiple ContentBlocks from array data.
     *
     * @param array<array<string, mixed>> $dataArray
     *
     * @return \MCP\Types\Content\ContentBlock[]
     */
    public function createContentBlocks(array $dataArray): array
    {
        return ContentBlockFactory::fromArrayMultiple($dataArray);
    }

    /**
     * Create an Implementation from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createImplementation(array $data): Implementation
    {
        return Implementation::fromArray($data);
    }

    /**
     * Create ClientCapabilities from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createClientCapabilities(array $data): ClientCapabilities
    {
        return ClientCapabilities::fromArray($data);
    }

    /**
     * Create ServerCapabilities from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createServerCapabilities(array $data): ServerCapabilities
    {
        return ServerCapabilities::fromArray($data);
    }

    /**
     * Create a Resource from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createResource(array $data): Resource
    {
        return Resource::fromArray($data);
    }

    /**
     * Create a ResourceTemplate from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createResourceTemplate(array $data): ResourceTemplate
    {
        return ResourceTemplate::fromArray($data);
    }

    /**
     * Create a Prompt from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createPrompt(array $data): Prompt
    {
        return Prompt::fromArray($data);
    }

    /**
     * Create a PromptArgument from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createPromptArgument(array $data): PromptArgument
    {
        return PromptArgument::fromArray($data);
    }

    /**
     * Create a PromptMessage from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createPromptMessage(array $data): PromptMessage
    {
        return PromptMessage::fromArray($data);
    }

    /**
     * Create a Root from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createRoot(array $data): Root
    {
        return Root::fromArray($data);
    }

    /**
     * Create a LoggingLevel from string.
     */
    public function createLoggingLevel(string $level): LoggingLevel
    {
        return LoggingLevel::from($level);
    }

    /**
     * Create a SamplingMessage from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createSamplingMessage(array $data): SamplingMessage
    {
        return SamplingMessage::fromArray($data);
    }

    /**
     * Create ModelPreferences from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createModelPreferences(array $data): ModelPreferences
    {
        return ModelPreferences::fromArray($data);
    }

    /**
     * Create a ModelHint from array data.
     *
     * @param array<string, mixed> $data
     */
    public function createModelHint(array $data): ModelHint
    {
        return ModelHint::fromArray($data);
    }

    /**
     * Get the validation service.
     */
    public function getValidationService(): ?ValidationService
    {
        return $this->validationService;
    }
}
