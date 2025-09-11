<?php

declare(strict_types=1);

namespace MCP\Client;

use Amp\Future;
use MCP\Shared\Protocol;
use MCP\Shared\RequestOptions;
use MCP\Shared\Transport;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\ErrorCode;
use MCP\Types\Implementation;
use MCP\Types\LoggingLevel;
use MCP\Types\McpError;
use MCP\Types\Notification;
use MCP\Types\Notifications\InitializedNotification;
use MCP\Types\Notifications\RootsListChangedNotification;
use MCP\Types\Protocol as ProtocolConstants;
use MCP\Types\Request;
use MCP\Types\Requests\CallToolRequest;
use MCP\Types\Requests\CompleteRequest;
use MCP\Types\Requests\CreateMessageRequest;
use MCP\Types\Requests\ElicitRequest;
use MCP\Types\Requests\GetPromptRequest;
use MCP\Types\Requests\InitializeRequest;
use MCP\Types\Requests\ListPromptsRequest;
use MCP\Types\Requests\ListResourcesRequest;
use MCP\Types\Requests\ListResourceTemplatesRequest;
use MCP\Types\Requests\ListRootsRequest;
use MCP\Types\Requests\ListToolsRequest;
use MCP\Types\Requests\PingRequest;
use MCP\Types\Requests\ReadResourceRequest;
use MCP\Types\Requests\SetLevelRequest;
use MCP\Types\Requests\SubscribeRequest;
use MCP\Types\Requests\UnsubscribeRequest;
use MCP\Types\Result;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\CompleteResult;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\Results\InitializeResult;
use MCP\Types\Results\ListPromptsResult;
use MCP\Types\Results\ListResourcesResult;
use MCP\Types\Results\ListResourceTemplatesResult;
use MCP\Types\Results\ListToolsResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\Tools\Tool;
use MCP\Utils\JsonSchemaValidator;
use MCP\Validation\ValidationService;
use function Amp\async;

/**
 * An MCP client on top of a pluggable transport.
 *
 * The client will automatically begin the initialization flow with the server when connect() is called.
 */
class Client extends Protocol
{
    private ?ServerCapabilities $serverCapabilities = null;
    private ?Implementation $serverVersion = null;
    private ClientCapabilities $capabilities;
    private ?string $instructions = null;

    /** @var array<string, array<string, mixed>> */
    private array $cachedToolOutputValidators = [];

    /**
     * Initializes this client with the given name and version information.
     */
    public function __construct(
        private readonly Implementation $clientInfo,
        ?ClientOptions $options = null
    ) {
        parent::__construct($options);
        $this->capabilities = $options?->capabilities ?? new ClientCapabilities();

        // Set up default server request handlers
        $this->setupDefaultServerRequestHandlers();
    }

    /**
     * Set up default handlers for server-initiated requests.
     */
    private function setupDefaultServerRequestHandlers(): void
    {
        // Default handler for sampling requests
        $this->setRequestHandler(
            CreateMessageRequest::class,
            function (CreateMessageRequest $request) {
                throw new McpError(
                    ErrorCode::MethodNotFound,
                    "Sampling not implemented. Override the CreateMessageRequest handler to provide sampling functionality."
                );
            }
        );

        // Default handler for elicitation requests  
        $this->setRequestHandler(
            ElicitRequest::class,
            function (ElicitRequest $request) {
                throw new McpError(
                    ErrorCode::MethodNotFound,
                    "Elicitation not implemented. Override the ElicitRequest handler to provide elicitation functionality."
                );
            }
        );

        // Default handler for roots list requests
        $this->setRequestHandler(
            ListRootsRequest::class,
            function (ListRootsRequest $request) {
                throw new McpError(
                    ErrorCode::MethodNotFound,
                    "Roots listing not implemented. Override the ListRootsRequest handler to provide roots functionality."
                );
            }
        );
    }

    /**
     * Registers new capabilities. This can only be called before connecting to a transport.
     *
     * The new capabilities will be merged with any existing capabilities previously given (e.g., at initialization).
     */
    public function registerCapabilities(ClientCapabilities $capabilities): void
    {
        if ($this->getTransport()) {
            throw new \Error("Cannot register capabilities after connecting to transport");
        }

        $this->capabilities = $this->mergeClientCapabilities($this->capabilities, $capabilities);
    }

    /**
     * Merge two ClientCapabilities objects.
     */
    private function mergeClientCapabilities(ClientCapabilities $base, ClientCapabilities $additional): ClientCapabilities
    {
        // Convert both to arrays for merging
        $baseArray = $base->jsonSerialize();
        $additionalArray = $additional->jsonSerialize();

        // Merge the arrays, with additional taking precedence for non-null values
        $merged = $baseArray;
        foreach ($additionalArray as $key => $value) {
            if ($value !== null) {
                if (isset($merged[$key]) && is_array($merged[$key]) && is_array($value)) {
                    // Merge arrays
                    $merged[$key] = array_merge($merged[$key], $value);
                } else {
                    // Replace value
                    $merged[$key] = $value;
                }
            }
        }

        return ClientCapabilities::fromArray($merged);
    }

    /**
     * Assert that the server has the required capability for a method.
     */
    private function assertCapability(string $capability, string $method): void
    {
        if (!$this->serverCapabilities) {
            throw new McpError(
                ErrorCode::InternalError,
                "Server capabilities not initialized"
            );
        }

        $hasCapability = match ($capability) {
            'logging' => $this->serverCapabilities->hasLogging(),
            'prompts' => $this->serverCapabilities->hasPrompts(),
            'resources' => $this->serverCapabilities->hasResources(),
            'tools' => $this->serverCapabilities->hasTools(),
            'completions' => $this->serverCapabilities->hasCompletions(),
            default => false,
        };

        if (!$hasCapability) {
            throw new McpError(
                ErrorCode::InvalidRequest,
                "Server does not support {$capability} (required for {$method})"
            );
        }
    }

    /**
     * Assert that the server supports resource subscriptions.
     */
    private function assertResourceSubscriptionCapability(string $method): void
    {
        $this->assertCapability('resources', $method);

        if (!$this->serverCapabilities?->supportsResourceSubscriptions()) {
            throw new McpError(
                ErrorCode::InvalidRequest,
                "Server does not support resource subscriptions (required for {$method})"
            );
        }
    }

    protected function assertCapabilityForMethod(string $method): void
    {
        switch ($method) {
            case 'logging/setLevel':
                $this->assertCapability('logging', $method);
                break;

            case 'prompts/get':
            case 'prompts/list':
                $this->assertCapability('prompts', $method);
                break;

            case 'resources/list':
            case 'resources/templates/list':
            case 'resources/read':
                $this->assertCapability('resources', $method);
                break;

            case 'resources/subscribe':
            case 'resources/unsubscribe':
                $this->assertResourceSubscriptionCapability($method);
                break;

            case 'tools/call':
            case 'tools/list':
                $this->assertCapability('tools', $method);
                break;

            case 'completion/complete':
                $this->assertCapability('completions', $method);
                break;

            case 'initialize':
            case 'ping':
                // No specific capability required
                break;

            default:
                // For unknown methods, don't assert any capabilities
                break;
        }
    }

    protected function assertNotificationCapability(string $method): void
    {
        switch ($method) {
            case 'notifications/roots/list_changed':
                if (!$this->capabilities->supportsRootsListChanged()) {
                    throw new McpError(
                        ErrorCode::InvalidRequest,
                        "Client does not support roots list changed notifications (required for {$method})"
                    );
                }
                break;

            case 'notifications/initialized':
            case 'notifications/cancelled':
            case 'notifications/progress':
                // These are always allowed
                break;
        }
    }

    protected function assertRequestHandlerCapability(string $method): void
    {
        switch ($method) {
            case 'sampling/createMessage':
                if (!$this->capabilities->hasSampling()) {
                    throw new McpError(
                        ErrorCode::InvalidRequest,
                        "Client does not support sampling capability (required for {$method})"
                    );
                }
                break;

            case 'elicitation/create':
                if (!$this->capabilities->hasElicitation()) {
                    throw new McpError(
                        ErrorCode::InvalidRequest,
                        "Client does not support elicitation capability (required for {$method})"
                    );
                }
                break;

            case 'roots/list':
                if (!$this->capabilities->hasRoots()) {
                    throw new McpError(
                        ErrorCode::InvalidRequest,
                        "Client does not support roots capability (required for {$method})"
                    );
                }
                break;

            case 'ping':
                // No specific capability required
                break;
        }
    }

    public function connect(Transport $transport, ?RequestOptions $options = null): Future
    {
        return async(function () use ($transport, $options) {
            parent::connect($transport)->await();

            // When transport sessionId is already set this means we are trying to reconnect.
            // In this case we don't need to initialize again.
            $sessionId = null;
            if (method_exists($transport, 'getSessionId')) {
                $sessionId = $transport->getSessionId();
            }
            if ($sessionId !== null) {
                return;
            }

            try {
                $initializeRequest = InitializeRequest::create(
                    ProtocolConstants::LATEST_PROTOCOL_VERSION,
                    $this->capabilities,
                    $this->clientInfo
                );

                $result = $this->request($initializeRequest, new ValidationService(), $options)->await();

                if (!($result instanceof InitializeResult)) {
                    throw new McpError(
                        ErrorCode::InternalError,
                        "Server sent invalid initialize result"
                    );
                }

                if (!ProtocolConstants::isVersionSupported($result->getProtocolVersion())) {
                    throw new McpError(
                        ErrorCode::InvalidRequest,
                        "Server's protocol version is not supported: {$result->getProtocolVersion()}"
                    );
                }

                $this->serverCapabilities = $result->getCapabilities();
                $this->serverVersion = $result->getServerInfo();
                $this->instructions = $result->getInstructions();

                // HTTP transports must set the protocol version in each header after initialization.
                if (method_exists($transport, 'setProtocolVersion')) {
                    $transport->setProtocolVersion($result->getProtocolVersion());
                }

                // Send initialized notification
                $initializedNotification = new InitializedNotification();
                $this->notification($initializedNotification)->await();
            } catch (\Throwable $error) {
                // Disconnect if initialization fails
                $this->close()->await();
                throw $error;
            }
        });
    }

    /**
     * After initialization has completed, this will be populated with the server's reported capabilities.
     */
    public function getServerCapabilities(): ?ServerCapabilities
    {
        return $this->serverCapabilities;
    }

    /**
     * After initialization has completed, this will be populated with information about the server's name and version.
     */
    public function getServerVersion(): ?Implementation
    {
        return $this->serverVersion;
    }

    /**
     * After initialization has completed, this may be populated with information about the server's instructions.
     */
    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    /**
     * Send a ping request to the server.
     */
    public function ping(?RequestOptions $options = null): Future
    {
        return async(function () use ($options) {
            $request = PingRequest::create();
            return $this->request($request, new ValidationService(), $options)->await();
        });
    }

    /**
     * Request completion suggestions from the server.
     */
    public function complete(CompleteRequest $request, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            $result = $this->request($request, new ValidationService(), $options)->await();
            return CompleteResult::fromArray($result);
        });
    }

    /**
     * Set the logging level for the server.
     */
    public function setLoggingLevel(LoggingLevel $level, ?RequestOptions $options = null): Future
    {
        return async(function () use ($level, $options) {
            $request = SetLevelRequest::create($level);
            return $this->request($request, new ValidationService(), $options)->await();
        });
    }

    /**
     * Get a prompt from the server.
     */
    public function getPrompt(GetPromptRequest $request, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            $result = $this->request($request, new ValidationService(), $options)->await();
            return GetPromptResult::fromArray($result);
        });
    }

    /**
     * List prompts available on the server.
     */
    public function listPrompts(?ListPromptsRequest $request = null, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            $request = $request ?? ListPromptsRequest::create();
            $result = $this->request($request, new ValidationService(), $options)->await();
            return ListPromptsResult::fromArray($result);
        });
    }

    /**
     * List resources available on the server.
     */
    public function listResources(?ListResourcesRequest $request = null, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            $request = $request ?? ListResourcesRequest::create();
            $result = $this->request($request, new ValidationService(), $options)->await();
            return ListResourcesResult::fromArray($result);
        });
    }

    /**
     * List resource templates available on the server.
     */
    public function listResourceTemplates(?ListResourceTemplatesRequest $request = null, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            $request = $request ?? ListResourceTemplatesRequest::create();
            $result = $this->request($request, new ValidationService(), $options)->await();
            return ListResourceTemplatesResult::fromArray($result);
        });
    }

    /**
     * Read a resource from the server.
     */
    public function readResource(ReadResourceRequest $request, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            $result = $this->request($request, new ValidationService(), $options)->await();
            return ReadResourceResult::fromArray($result);
        });
    }

    /**
     * Subscribe to a resource on the server.
     */
    public function subscribeResource(SubscribeRequest $request, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            return $this->request($request, new ValidationService(), $options)->await();
        });
    }

    /**
     * Unsubscribe from a resource on the server.
     */
    public function unsubscribeResource(UnsubscribeRequest $request, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            return $this->request($request, new ValidationService(), $options)->await();
        });
    }

    /**
     * List tools available on the server.
     */
    public function listTools(?ListToolsRequest $request = null, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            $request = $request ?? ListToolsRequest::create();
            $result = $this->request($request, new ValidationService(), $options)->await();
            $listToolsResult = ListToolsResult::fromArray($result);

            // Cache the tools and their output schemas for future validation
            $this->cacheToolOutputSchemas($listToolsResult->getTools());

            return $listToolsResult;
        });
    }

    /**
     * Call a tool on the server.
     */
    public function callTool(CallToolRequest $request, ?RequestOptions $options = null): Future
    {
        return async(function () use ($request, $options) {
            $result = $this->request($request, new ValidationService(), $options)->await();
            $callToolResult = CallToolResult::fromArray($result);

            // Validate tool output if the tool has an output schema
            $this->validateToolOutput($request->getName(), $callToolResult);

            return $callToolResult;
        });
    }

    /**
     * Cache tool output schemas for validation.
     * 
     * @param Tool[] $tools
     */
    private function cacheToolOutputSchemas(array $tools): void
    {
        $this->cachedToolOutputValidators = [];

        foreach ($tools as $tool) {
            if ($tool->hasOutputSchema()) {
                $this->cachedToolOutputValidators[$tool->getName()] = $tool->getOutputSchema();
            }
        }
    }

    /**
     * Get cached tool output schema for a tool.
     */
    private function getCachedToolOutputSchema(string $toolName): ?array
    {
        return $this->cachedToolOutputValidators[$toolName] ?? null;
    }

    /**
     * Validate tool output against cached schema.
     */
    private function validateToolOutput(string $toolName, CallToolResult $result): void
    {
        $schema = $this->getCachedToolOutputSchema($toolName);
        if (!$schema) {
            return; // No schema to validate against
        }

        // If tool has outputSchema, it MUST return structuredContent (unless it's an error)
        if (!$result->getStructuredContent() && !$result->isError()) {
            throw new McpError(
                ErrorCode::InvalidRequest,
                "Tool {$toolName} has an output schema but did not return structured content"
            );
        }

        // Only validate structured content if present (not when there's an error)
        if ($result->getStructuredContent()) {
            try {
                JsonSchemaValidator::validate($result->getStructuredContent(), $schema);
            } catch (McpError $error) {
                throw $error;
            } catch (\Throwable $error) {
                throw new McpError(
                    ErrorCode::InvalidParams,
                    "Failed to validate structured content: {$error->getMessage()}"
                );
            }
        }
    }

    /**
     * Send a roots list changed notification.
     */
    public function sendRootsListChanged(): Future
    {
        return async(function () {
            $notification = new RootsListChangedNotification();
            return $this->notification($notification)->await();
        });
    }

    // Convenience methods for common operations

    /**
     * List tools with optional cursor for pagination.
     */
    public function listToolsWithCursor(?string $cursor = null, ?RequestOptions $options = null): Future
    {
        return async(function () use ($cursor, $options) {
            $request = ListToolsRequest::create();
            if ($cursor !== null) {
                // Add cursor to request params if supported
                $params = $request->getParams() ?? [];
                $params['cursor'] = $cursor;
                $request = new ListToolsRequest($params);
            }
            return $this->listTools($request, $options)->await();
        });
    }

    /**
     * Call a tool with name and arguments.
     */
    public function callToolByName(string $name, ?array $arguments = null, ?RequestOptions $options = null): Future
    {
        return async(function () use ($name, $arguments, $options) {
            $request = CallToolRequest::create($name, $arguments);
            return $this->callTool($request, $options)->await();
        });
    }

    /**
     * Read a resource by URI.
     */
    public function readResourceByUri(string $uri, ?RequestOptions $options = null): Future
    {
        return async(function () use ($uri, $options) {
            $request = ReadResourceRequest::create($uri);
            return $this->readResource($request, $options)->await();
        });
    }

    /**
     * Subscribe to a resource by URI.
     */
    public function subscribeToResource(string $uri, ?RequestOptions $options = null): Future
    {
        return async(function () use ($uri, $options) {
            $request = SubscribeRequest::create($uri);
            return $this->subscribeResource($request, $options)->await();
        });
    }

    /**
     * Unsubscribe from a resource by URI.
     */
    public function unsubscribeFromResource(string $uri, ?RequestOptions $options = null): Future
    {
        return async(function () use ($uri, $options) {
            $request = UnsubscribeRequest::create($uri);
            return $this->unsubscribeResource($request, $options)->await();
        });
    }

    /**
     * Get a prompt by name with optional arguments.
     */
    public function getPromptByName(string $name, ?array $arguments = null, ?RequestOptions $options = null): Future
    {
        return async(function () use ($name, $arguments, $options) {
            $request = GetPromptRequest::create($name, $arguments);
            return $this->getPrompt($request, $options)->await();
        });
    }

    // Server request handler setup methods

    /**
     * Set a custom handler for sampling requests from the server.
     * 
     * @param callable(CreateMessageRequest): mixed $handler
     */
    public function setSamplingHandler(callable $handler): void
    {
        $this->setRequestHandler(CreateMessageRequest::class, $handler);
    }

    /**
     * Set a custom handler for elicitation requests from the server.
     * 
     * @param callable(ElicitRequest): mixed $handler  
     */
    public function setElicitationHandler(callable $handler): void
    {
        $this->setRequestHandler(ElicitRequest::class, $handler);
    }

    /**
     * Set a custom handler for roots list requests from the server.
     * 
     * @param callable(ListRootsRequest): mixed $handler
     */
    public function setRootsListHandler(callable $handler): void
    {
        $this->setRequestHandler(ListRootsRequest::class, $handler);
    }

    /**
     * Helper method to build a request array.
     */
    private function buildRequest(string $method, ?array $params = null): array
    {
        $request = ['method' => $method];
        if ($params !== null) {
            $request['params'] = $params;
        }
        return $request;
    }
}
