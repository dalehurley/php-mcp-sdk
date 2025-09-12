<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Shared\Transport;
use MCP\Shared\RequestHandlerExtra;
use MCP\Server\Auth\AuthInfo;
use MCP\Server\Auth\OAuthServerProvider;
use MCP\Types\Implementation;
use MCP\Types\Tools\Tool;
use MCP\Types\Tools\ToolAnnotations;
use MCP\Types\Prompts\Prompt;
use MCP\Types\Prompts\PromptArgument;
use MCP\Types\Resources\Resource;
use MCP\Types\References\PromptReference;
use MCP\Types\References\ResourceTemplateReference;
use MCP\Types\Results\ListToolsResult;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\ListPromptsResult;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\Results\ListResourcesResult;
use MCP\Types\Results\ListResourceTemplatesResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\Results\CompleteResult;
use MCP\Types\Requests\ListToolsRequest;
use MCP\Types\Requests\CallToolRequest;
use MCP\Types\Requests\ListPromptsRequest;
use MCP\Types\Requests\GetPromptRequest;
use MCP\Types\Requests\ListResourcesRequest;
use MCP\Types\Requests\ListResourceTemplatesRequest;
use MCP\Types\Requests\ReadResourceRequest;
use MCP\Types\Requests\CompleteRequest;
use MCP\Types\Requests\SubscribeRequest;
use MCP\Types\Requests\UnsubscribeRequest;
use MCP\Types\EmptyResult;
use MCP\Types\Notifications\LoggingMessageNotification;
use MCP\Types\McpError;
use MCP\Types\ErrorCode;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Utils\JsonSchemaValidator;
use function Amp\async;
use function Amp\Future\await;

/**
 * Empty JSON Schema for tools without input
 */
define('EMPTY_OBJECT_JSON_SCHEMA', [
    'type' => 'object',
    'properties' => (object)[],
]);

/**
 * Empty completion result
 */
define('EMPTY_COMPLETION_RESULT', [
    'completion' => [
        'values' => [],
        'hasMore' => false,
    ],
]);

/**
 * High-level MCP server that provides a simpler API for working with resources, tools, and prompts.
 * For advanced usage (like sending notifications or setting custom request handlers), use the underlying
 * Server instance available via the `server` property.
 */
class McpServer
{
    /**
     * The underlying Server instance, useful for advanced operations like sending notifications.
     */
    public readonly Server $server;

    /** @var array<string, RegisteredResource> */
    private array $_registeredResources = [];

    /** @var array<string, RegisteredResourceTemplate> */
    private array $_registeredResourceTemplates = [];

    /** @var array<string, RegisteredTool> */
    private array $_registeredTools = [];

    /** @var array<string, RegisteredPrompt> */
    private array $_registeredPrompts = [];

    private bool $_toolHandlersInitialized = false;
    private bool $_completionHandlerInitialized = false;
    private bool $_resourceHandlersInitialized = false;
    private bool $_promptHandlersInitialized = false;

    /**
     * Map of sessionId => set of subscribed resource URIs
     * @var array<string, array<string, bool>>
     */
    private array $_resourceSubscriptionsBySession = [];

    /**
     * OAuth authentication provider
     */
    private ?OAuthServerProvider $authProvider = null;

    public function __construct(
        Implementation $serverInfo,
        ?ServerOptions $options = null
    ) {
        $this->server = new Server($serverInfo, $options);
    }

    /**
     * Attaches to the given transport, starts it, and starts listening for messages.
     * 
     * The `server` object assumes ownership of the Transport, replacing any callbacks that have 
     * already been set, and expects that it is the only user of the Transport instance going forward.
     */
    public function connect(Transport $transport): \Amp\Future
    {
        return $this->server->connect($transport);
    }

    /**
     * Closes the connection.
     */
    public function close(): \Amp\Future
    {
        return $this->server->close();
    }

    /**
     * Enable OAuth authentication for this server.
     */
    public function useAuth(OAuthServerProvider $provider): void
    {
        $this->authProvider = $provider;

        // Wrap all request handlers to include auth info
        $this->server->setRequestHandlerWrapper(function (callable $handler) {
            return function ($request, RequestHandlerExtra $extra) use ($handler) {
                // The auth info should already be in the extra object from middleware
                // Just pass it through to the handler
                return $handler($request, $extra);
            };
        });
    }

    /**
     * Get the OAuth authentication provider if set.
     */
    public function getAuthProvider(): ?OAuthServerProvider
    {
        return $this->authProvider;
    }

    /**
     * Checks if the server is connected to a transport.
     * @return bool True if the server is connected
     */
    public function isConnected(): bool
    {
        return $this->server->getTransport() !== null;
    }

    // Tool Methods

    private function setToolRequestHandlers(): void
    {
        if ($this->_toolHandlersInitialized) {
            return;
        }

        $this->server->registerCapabilities(new ServerCapabilities(
            tools: ['listChanged' => true]
        ));

        $this->server->assertCanSetRequestHandler(ListToolsRequest::METHOD);
        $this->server->assertCanSetRequestHandler(CallToolRequest::METHOD);

        $this->server->setRequestHandler(
            ListToolsRequest::class,
            function (ListToolsRequest $request, RequestHandlerExtra $extra): ListToolsResult {
                $tools = [];

                foreach ($this->_registeredTools as $name => $tool) {
                    if (!$tool->enabled) {
                        continue;
                    }

                    $toolDefinition = new Tool(
                        name: $name,
                        inputSchema: $tool->inputSchema !== null
                            ? $this->schemaToJsonSchema($tool->inputSchema)
                            : EMPTY_OBJECT_JSON_SCHEMA,
                        title: $tool->title,
                        description: $tool->description,
                        outputSchema: $tool->outputSchema !== null
                            ? $this->schemaToJsonSchema($tool->outputSchema)
                            : null,
                        annotations: $tool->annotations
                    );

                    $tools[] = $toolDefinition;
                }

                return new ListToolsResult($tools);
            }
        );

        $this->server->setRequestHandler(
            CallToolRequest::class,
            function (CallToolRequest $request, RequestHandlerExtra $extra): \Amp\Future {
                return async(function () use ($request, $extra) {
                    $toolName = $request->getName();
                    $tool = $this->_registeredTools[$toolName] ?? null;

                    if ($tool === null) {
                        throw new McpError(
                            ErrorCode::InvalidParams,
                            "Tool {$toolName} not found"
                        );
                    }

                    if (!$tool->enabled) {
                        throw new McpError(
                            ErrorCode::InvalidParams,
                            "Tool {$toolName} disabled"
                        );
                    }

                    $result = null;

                    try {
                        if ($tool->inputSchema !== null) {
                            // Validate input arguments
                            $args = $request->getArguments();
                            JsonSchemaValidator::validate($args, JsonSchemaValidator::normalizeSchema($tool->inputSchema));

                            $callback = $tool->callback;
                            $result = $callback($args, $extra);
                        } else {
                            $callback = $tool->callback;
                            $result = $callback($extra);
                        }

                        // Await if it's a Future
                        if ($result instanceof \Amp\Future) {
                            $result = $result->await();
                        }
                    } catch (\Throwable $error) {
                        $result = new CallToolResult(
                            content: [
                                [
                                    'type' => 'text',
                                    'text' => $error instanceof \Error ? $error->getMessage() : (string)$error,
                                ],
                            ],
                            isError: true
                        );
                    }

                    // Validate output if schema exists and no error
                    if ($tool->outputSchema !== null && !($result instanceof CallToolResult && $result->isError())) {
                        if (!$result->hasStructuredContent()) {
                            throw new McpError(
                                ErrorCode::InvalidParams,
                                "Tool {$toolName} has an output schema but no structured content was provided"
                            );
                        }

                        // Validate structured content against output schema
                        $structuredContent = $result->getStructuredContent();
                        if ($structuredContent !== null) {
                            JsonSchemaValidator::validate($structuredContent, JsonSchemaValidator::normalizeSchema($tool->outputSchema));
                        }
                    }

                    return $result;
                });
            }
        );

        $this->_toolHandlersInitialized = true;
    }

    /**
     * Register a tool with the server
     * Multiple overloads are supported to match TypeScript SDK
     */
    public function tool(string $name, ...$args): RegisteredTool
    {
        if (isset($this->_registeredTools[$name])) {
            throw new \Error("Tool {$name} is already registered");
        }

        $description = null;
        $inputSchema = null;
        $outputSchema = null;
        $annotations = null;
        $callback = null;

        // Parse arguments based on overload pattern
        $argIndex = 0;

        // Check if first arg is description (string)
        if (isset($args[$argIndex]) && is_string($args[$argIndex])) {
            $description = $args[$argIndex];
            $argIndex++;
        }

        // Handle different overload combinations
        if (isset($args[$argIndex]) && count($args) > $argIndex + 1) {
            $firstArg = $args[$argIndex];

            if ($this->isSchema($firstArg)) {
                // We have a params schema as the first arg
                $inputSchema = $firstArg;
                $argIndex++;

                // Check if next arg is annotations
                if (
                    isset($args[$argIndex]) && count($args) > $argIndex + 1 &&
                    is_array($args[$argIndex]) && !$this->isSchema($args[$argIndex])
                ) {
                    $annotations = ToolAnnotations::fromArray($args[$argIndex]);
                    $argIndex++;
                }
            } elseif (is_array($firstArg) && !$this->isSchema($firstArg)) {
                // Not a schema, so must be annotations
                $annotations = ToolAnnotations::fromArray($firstArg);
                $argIndex++;
            }
        }

        // Last argument is always the callback
        $callback = $args[$argIndex] ?? null;

        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("Tool callback must be callable");
        }

        $callbackWrapper = new class($callback) implements ToolCallback {
            private $callback;

            public function __construct(callable $callback)
            {
                $this->callback = $callback;
            }

            public function __invoke($argsOrExtra, $extra = null)
            {
                return ($this->callback)($argsOrExtra, $extra);
            }
        };

        return $this->_createRegisteredTool(
            $name,
            null, // title
            $description,
            $inputSchema,
            $outputSchema,
            $annotations,
            $callbackWrapper
        );
    }

    /**
     * Register a tool with a config object and callback.
     * 
     * @param array{
     *   title?: string,
     *   description?: string,
     *   inputSchema?: mixed,
     *   outputSchema?: mixed,
     *   annotations?: ToolAnnotations|array
     * } $config
     */
    public function registerTool(string $name, array $config, callable $callback): RegisteredTool
    {
        if (isset($this->_registeredTools[$name])) {
            throw new \Error("Tool {$name} is already registered");
        }

        $title = $config['title'] ?? null;
        $description = $config['description'] ?? null;
        $inputSchema = $config['inputSchema'] ?? null;
        $outputSchema = $config['outputSchema'] ?? null;

        $annotations = null;
        if (isset($config['annotations'])) {
            $annotations = $config['annotations'] instanceof ToolAnnotations
                ? $config['annotations']
                : ToolAnnotations::fromArray($config['annotations']);
        }

        $callbackWrapper = new class($callback) implements ToolCallback {
            private $callback;

            public function __construct(callable $callback)
            {
                $this->callback = $callback;
            }

            public function __invoke($argsOrExtra, $extra = null)
            {
                return ($this->callback)($argsOrExtra, $extra);
            }
        };

        return $this->_createRegisteredTool(
            $name,
            $title,
            $description,
            $inputSchema,
            $outputSchema,
            $annotations,
            $callbackWrapper
        );
    }

    private function _createRegisteredTool(
        string $name,
        ?string $title,
        ?string $description,
        $inputSchema,
        $outputSchema,
        ?ToolAnnotations $annotations,
        ToolCallback $callback
    ): RegisteredTool {
        $registeredTool = new RegisteredTool(
            title: $title,
            description: $description,
            inputSchema: $inputSchema,
            outputSchema: $outputSchema,
            annotations: $annotations,
            callback: $callback,
            enabled: true,
            onUpdate: function (array $updates) use (&$name, &$registeredTool) {
                if (isset($updates['name']) && $updates['name'] !== $name) {
                    unset($this->_registeredTools[$name]);
                    if ($updates['name'] !== null) {
                        $this->_registeredTools[$updates['name']] = $registeredTool;
                        $name = $updates['name'];
                    }
                }
                $this->sendToolListChanged();
            },
            onRemove: function () use (&$name) {
                unset($this->_registeredTools[$name]);
                $this->sendToolListChanged();
            }
        );

        $this->_registeredTools[$name] = $registeredTool;
        $this->setToolRequestHandlers();
        $this->sendToolListChanged();

        return $registeredTool;
    }

    // Resource Methods

    private function setResourceRequestHandlers(): void
    {
        if ($this->_resourceHandlersInitialized) {
            return;
        }

        $this->server->registerCapabilities(new ServerCapabilities(
            resources: ['listChanged' => true]
        ));

        $this->server->assertCanSetRequestHandler(ListResourcesRequest::METHOD);
        $this->server->assertCanSetRequestHandler(ListResourceTemplatesRequest::METHOD);
        $this->server->assertCanSetRequestHandler(ReadResourceRequest::METHOD);
        $this->server->assertCanSetRequestHandler(SubscribeRequest::METHOD);
        $this->server->assertCanSetRequestHandler(UnsubscribeRequest::METHOD);

        $this->server->setRequestHandler(
            ListResourcesRequest::class,
            function (ListResourcesRequest $request, RequestHandlerExtra $extra): \Amp\Future {
                return async(function () use ($request, $extra) {
                    $resources = [];

                    // Add static resources
                    foreach ($this->_registeredResources as $uri => $resource) {
                        if (!$resource->enabled) {
                            continue;
                        }

                        $resourceData = [
                            'uri' => $uri,
                            'name' => $resource->name,
                        ];

                        if ($resource->title !== null) {
                            $resourceData['title'] = $resource->title;
                        }

                        if ($resource->metadata !== null) {
                            $resourceData = array_merge($resourceData, $resource->metadata->toArray());
                        }

                        $resources[] = Resource::fromArray($resourceData);
                    }

                    // Add template resources
                    foreach ($this->_registeredResourceTemplates as $template) {
                        if ($template->resourceTemplate->getListCallback() === null) {
                            continue;
                        }

                        $callback = $template->resourceTemplate->getListCallback();
                        $result = $callback($extra);

                        if ($result instanceof \Amp\Future) {
                            $result = $result->await();
                        }

                        foreach ($result->getResources() as $resource) {
                            // Merge template metadata with resource metadata
                            $resourceData = $resource->jsonSerialize();

                            if ($template->metadata !== null) {
                                $templateMeta = $template->metadata->toArray();
                                // Resource metadata should override template metadata
                                $resourceData = array_merge($templateMeta, $resourceData);
                            }

                            $resources[] = Resource::fromArray($resourceData);
                        }
                    }

                    return new ListResourcesResult($resources);
                });
            }
        );

        $this->server->setRequestHandler(
            ListResourceTemplatesRequest::class,
            function (ListResourceTemplatesRequest $request, RequestHandlerExtra $extra): ListResourceTemplatesResult {
                $resourceTemplates = [];

                foreach ($this->_registeredResourceTemplates as $name => $template) {
                    $templateData = [
                        'name' => $name,
                        'uriTemplate' => (string)$template->resourceTemplate->getUriTemplate(),
                    ];

                    if ($template->title !== null) {
                        $templateData['title'] = $template->title;
                    }

                    if ($template->metadata !== null) {
                        $templateData = array_merge($templateData, $template->metadata->toArray());
                    }

                    $resourceTemplates[] = $templateData;
                }

                return new ListResourceTemplatesResult($resourceTemplates);
            }
        );

        $this->server->setRequestHandler(
            ReadResourceRequest::class,
            function (ReadResourceRequest $request, RequestHandlerExtra $extra): \Amp\Future {
                return async(function () use ($request, $extra) {
                    $uri = $request->getUri();

                    // First check for exact resource match
                    $resource = $this->_registeredResources[$uri] ?? null;
                    if ($resource !== null) {
                        if (!$resource->enabled) {
                            throw new McpError(
                                ErrorCode::InvalidParams,
                                "Resource {$uri} disabled"
                            );
                        }

                        $callback = $resource->readCallback;
                        $result = $callback($uri, $extra);

                        if ($result instanceof \Amp\Future) {
                            $result = $result->await();
                        }

                        return $result;
                    }

                    // Then check templates
                    foreach ($this->_registeredResourceTemplates as $template) {
                        $variables = $template->resourceTemplate->getUriTemplate()->match($uri);
                        if ($variables !== null) {
                            $callback = $template->readCallback;
                            $result = $callback($uri, $variables, $extra);

                            if ($result instanceof \Amp\Future) {
                                $result = $result->await();
                            }

                            return $result;
                        }
                    }

                    throw new McpError(
                        ErrorCode::InvalidParams,
                        "Resource {$uri} not found"
                    );
                });
            }
        );

        // Subscribe to resource updates for a given URI
        $this->server->setRequestHandler(
            SubscribeRequest::class,
            function (SubscribeRequest $request, RequestHandlerExtra $extra): EmptyResult {
                $uri = $request->getUri();
                if ($uri === null) {
                    throw new McpError(ErrorCode::InvalidParams, 'Missing uri');
                }

                $sessionId = $extra->sessionId ?? ($extra->requestInfo['headers']['mcp-session-id'] ?? null);
                if (!is_string($sessionId) || $sessionId === '') {
                    // If no session id, treat as no-op; protocol allows stateless clients
                    return new EmptyResult();
                }

                $this->_resourceSubscriptionsBySession[$sessionId] ??= [];
                $this->_resourceSubscriptionsBySession[$sessionId][$uri] = true;
                return new EmptyResult();
            }
        );

        // Unsubscribe from resource updates for a given URI
        $this->server->setRequestHandler(
            UnsubscribeRequest::class,
            function (UnsubscribeRequest $request, RequestHandlerExtra $extra): EmptyResult {
                $uri = $request->getUri();
                if ($uri === null) {
                    throw new McpError(ErrorCode::InvalidParams, 'Missing uri');
                }

                $sessionId = $extra->sessionId ?? ($extra->requestInfo['headers']['mcp-session-id'] ?? null);
                if (!is_string($sessionId) || $sessionId === '') {
                    return new EmptyResult();
                }

                if (isset($this->_resourceSubscriptionsBySession[$sessionId][$uri])) {
                    unset($this->_resourceSubscriptionsBySession[$sessionId][$uri]);
                    if (empty($this->_resourceSubscriptionsBySession[$sessionId])) {
                        unset($this->_resourceSubscriptionsBySession[$sessionId]);
                    }
                }

                return new EmptyResult();
            }
        );

        $this->setCompletionRequestHandler();

        $this->_resourceHandlersInitialized = true;
    }

    /**
     * Register a resource with the server
     * Multiple overloads are supported to match TypeScript SDK
     */
    public function resource(string $name, $uriOrTemplate, ...$args)
    {
        $metadata = null;
        $readCallback = null;

        // Check if first remaining arg is metadata
        if (isset($args[0]) && is_array($args[0]) && !is_callable($args[0])) {
            $metadata = new ResourceMetadata(
                title: $args[0]['title'] ?? null,
                description: $args[0]['description'] ?? null,
                mimeType: $args[0]['mimeType'] ?? null
            );
            $readCallback = $args[1] ?? null;
        } else {
            $readCallback = $args[0] ?? null;
        }

        if (!is_callable($readCallback)) {
            throw new \InvalidArgumentException("Resource callback must be callable");
        }

        if (is_string($uriOrTemplate)) {
            // Static resource
            if (isset($this->_registeredResources[$uriOrTemplate])) {
                throw new \Error("Resource {$uriOrTemplate} is already registered");
            }

            $callbackWrapper = new class($readCallback) implements ReadResourceCallback {
                private $callback;

                public function __construct(callable $callback)
                {
                    $this->callback = $callback;
                }

                public function __invoke($uri, RequestHandlerExtra $extra)
                {
                    return ($this->callback)($uri, $extra);
                }
            };

            return $this->_createRegisteredResource(
                $name,
                null, // title
                $uriOrTemplate,
                $metadata,
                $callbackWrapper
            );
        } else {
            // Resource template
            if (isset($this->_registeredResourceTemplates[$name])) {
                throw new \Error("Resource template {$name} is already registered");
            }

            $callbackWrapper = new class($readCallback) implements ReadResourceTemplateCallback {
                private $callback;

                public function __construct(callable $callback)
                {
                    $this->callback = $callback;
                }

                public function __invoke($uri, array $variables, RequestHandlerExtra $extra)
                {
                    return ($this->callback)($uri, $variables, $extra);
                }
            };

            return $this->_createRegisteredResourceTemplate(
                $name,
                null, // title
                $uriOrTemplate,
                $metadata,
                $callbackWrapper
            );
        }
    }

    /**
     * Register a resource with a config object and callback.
     */
    public function registerResource(
        string $name,
        $uriOrTemplate,
        array $config,
        callable $readCallback
    ) {
        $metadata = new ResourceMetadata(
            title: $config['title'] ?? null,
            description: $config['description'] ?? null,
            mimeType: $config['mimeType'] ?? null
        );

        if (is_string($uriOrTemplate)) {
            if (isset($this->_registeredResources[$uriOrTemplate])) {
                throw new \Error("Resource {$uriOrTemplate} is already registered");
            }

            $callbackWrapper = new class($readCallback) implements ReadResourceCallback {
                private $callback;

                public function __construct(callable $callback)
                {
                    $this->callback = $callback;
                }

                public function __invoke($uri, RequestHandlerExtra $extra)
                {
                    return ($this->callback)($uri, $extra);
                }
            };

            return $this->_createRegisteredResource(
                $name,
                $config['title'] ?? null,
                $uriOrTemplate,
                $metadata,
                $callbackWrapper
            );
        } else {
            if (isset($this->_registeredResourceTemplates[$name])) {
                throw new \Error("Resource template {$name} is already registered");
            }

            $callbackWrapper = new class($readCallback) implements ReadResourceTemplateCallback {
                private $callback;

                public function __construct(callable $callback)
                {
                    $this->callback = $callback;
                }

                public function __invoke($uri, array $variables, RequestHandlerExtra $extra)
                {
                    return ($this->callback)($uri, $variables, $extra);
                }
            };

            return $this->_createRegisteredResourceTemplate(
                $name,
                $config['title'] ?? null,
                $uriOrTemplate,
                $metadata,
                $callbackWrapper
            );
        }
    }

    private function _createRegisteredResource(
        string $name,
        ?string $title,
        string $uri,
        ?ResourceMetadata $metadata,
        ReadResourceCallback $readCallback
    ): RegisteredResource {
        $registeredResource = new RegisteredResource(
            name: $name,
            title: $title,
            metadata: $metadata,
            readCallback: $readCallback,
            enabled: true,
            onUpdate: function (array $updates) use (&$uri, &$registeredResource) {
                if (isset($updates['uri']) && $updates['uri'] !== $uri) {
                    unset($this->_registeredResources[$uri]);
                    if ($updates['uri'] !== null) {
                        $this->_registeredResources[$updates['uri']] = $registeredResource;
                        $uri = $updates['uri'];
                    }
                }
                $this->sendResourceListChanged();
            },
            onRemove: function () use (&$uri) {
                unset($this->_registeredResources[$uri]);
                $this->sendResourceListChanged();
            }
        );

        $this->_registeredResources[$uri] = $registeredResource;
        $this->setResourceRequestHandlers();
        $this->sendResourceListChanged();

        return $registeredResource;
    }

    private function _createRegisteredResourceTemplate(
        string $name,
        ?string $title,
        ResourceTemplate $template,
        ?ResourceMetadata $metadata,
        ReadResourceTemplateCallback $readCallback
    ): RegisteredResourceTemplate {
        $registeredResourceTemplate = new RegisteredResourceTemplate(
            resourceTemplate: $template,
            title: $title,
            metadata: $metadata,
            readCallback: $readCallback,
            enabled: true,
            onUpdate: function (array $updates) use (&$name, &$registeredResourceTemplate) {
                if (isset($updates['name']) && $updates['name'] !== $name) {
                    unset($this->_registeredResourceTemplates[$name]);
                    if ($updates['name'] !== null) {
                        $this->_registeredResourceTemplates[$updates['name']] = $registeredResourceTemplate;
                        $name = $updates['name'];
                    }
                }
                $this->sendResourceListChanged();
            },
            onRemove: function () use (&$name) {
                unset($this->_registeredResourceTemplates[$name]);
                $this->sendResourceListChanged();
            }
        );

        $this->_registeredResourceTemplates[$name] = $registeredResourceTemplate;
        $this->setResourceRequestHandlers();
        $this->sendResourceListChanged();

        return $registeredResourceTemplate;
    }

    // Prompt Methods

    private function setPromptRequestHandlers(): void
    {
        if ($this->_promptHandlersInitialized) {
            return;
        }

        $this->server->registerCapabilities(new ServerCapabilities(
            prompts: ['listChanged' => true]
        ));

        $this->server->assertCanSetRequestHandler(ListPromptsRequest::METHOD);
        $this->server->assertCanSetRequestHandler(GetPromptRequest::METHOD);

        $this->server->setRequestHandler(
            ListPromptsRequest::class,
            function (ListPromptsRequest $request, RequestHandlerExtra $extra): ListPromptsResult {
                $prompts = [];

                foreach ($this->_registeredPrompts as $name => $prompt) {
                    if (!$prompt->enabled) {
                        continue;
                    }

                    $promptData = [
                        'name' => $name,
                    ];

                    if ($prompt->title !== null) {
                        $promptData['title'] = $prompt->title;
                    }

                    if ($prompt->description !== null) {
                        $promptData['description'] = $prompt->description;
                    }

                    if ($prompt->argsSchema !== null) {
                        $promptData['arguments'] = $this->promptArgumentsFromSchema($prompt->argsSchema);
                    }

                    $prompts[] = Prompt::fromArray($promptData);
                }

                return new ListPromptsResult($prompts);
            }
        );

        $this->server->setRequestHandler(
            GetPromptRequest::class,
            function (GetPromptRequest $request, RequestHandlerExtra $extra): \Amp\Future {
                return async(function () use ($request, $extra) {
                    $promptName = $request->getName();
                    $prompt = $this->_registeredPrompts[$promptName] ?? null;

                    if ($prompt === null) {
                        throw new McpError(
                            ErrorCode::InvalidParams,
                            "Prompt {$promptName} not found"
                        );
                    }

                    if (!$prompt->enabled) {
                        throw new McpError(
                            ErrorCode::InvalidParams,
                            "Prompt {$promptName} disabled"
                        );
                    }

                    if ($prompt->argsSchema !== null) {
                        // Validate arguments
                        $args = $request->getArguments();
                        JsonSchemaValidator::validate($args, JsonSchemaValidator::normalizeSchema($prompt->argsSchema));

                        $callback = $prompt->callback;
                        $result = $callback($args, $extra);
                    } else {
                        $callback = $prompt->callback;
                        $result = $callback($extra);
                    }

                    if ($result instanceof \Amp\Future) {
                        $result = $result->await();
                    }

                    return $result;
                });
            }
        );

        $this->setCompletionRequestHandler();

        $this->_promptHandlersInitialized = true;
    }

    /**
     * Register a prompt with the server
     * Multiple overloads are supported to match TypeScript SDK
     */
    public function prompt(string $name, ...$args): RegisteredPrompt
    {
        if (isset($this->_registeredPrompts[$name])) {
            throw new \Error("Prompt {$name} is already registered");
        }

        $description = null;
        $argsSchema = null;
        $callback = null;

        // Check if first arg is description (string)
        if (isset($args[0]) && is_string($args[0])) {
            $description = $args[0];
            array_shift($args);
        }

        // Check if we have an args schema
        if (count($args) > 1) {
            $argsSchema = $args[0];
            $callback = $args[1];
        } else {
            $callback = $args[0];
        }

        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("Prompt callback must be callable");
        }

        $callbackWrapper = new class($callback) implements PromptCallback {
            private $callback;

            public function __construct(callable $callback)
            {
                $this->callback = $callback;
            }

            public function __invoke($argsOrExtra, $extra = null)
            {
                return ($this->callback)($argsOrExtra, $extra);
            }
        };

        $registeredPrompt = $this->_createRegisteredPrompt(
            $name,
            null, // title
            $description,
            $argsSchema,
            $callbackWrapper
        );

        $this->setPromptRequestHandlers();
        $this->sendPromptListChanged();

        return $registeredPrompt;
    }

    /**
     * Register a prompt with a config object and callback.
     * 
     * @param array{
     *   title?: string,
     *   description?: string,
     *   argsSchema?: mixed
     * } $config
     */
    public function registerPrompt(string $name, array $config, callable $callback): RegisteredPrompt
    {
        if (isset($this->_registeredPrompts[$name])) {
            throw new \Error("Prompt {$name} is already registered");
        }

        $title = $config['title'] ?? null;
        $description = $config['description'] ?? null;
        $argsSchema = $config['argsSchema'] ?? null;

        $callbackWrapper = new class($callback) implements PromptCallback {
            private $callback;

            public function __construct(callable $callback)
            {
                $this->callback = $callback;
            }

            public function __invoke($argsOrExtra, $extra = null)
            {
                return ($this->callback)($argsOrExtra, $extra);
            }
        };

        $registeredPrompt = $this->_createRegisteredPrompt(
            $name,
            $title,
            $description,
            $argsSchema,
            $callbackWrapper
        );

        $this->setPromptRequestHandlers();
        $this->sendPromptListChanged();

        return $registeredPrompt;
    }

    private function _createRegisteredPrompt(
        string $name,
        ?string $title,
        ?string $description,
        $argsSchema,
        PromptCallback $callback
    ): RegisteredPrompt {
        $registeredPrompt = new RegisteredPrompt(
            title: $title,
            description: $description,
            argsSchema: $argsSchema,
            callback: $callback,
            enabled: true,
            onUpdate: function (array $updates) use (&$name, &$registeredPrompt) {
                if (isset($updates['name']) && $updates['name'] !== $name) {
                    unset($this->_registeredPrompts[$name]);
                    if ($updates['name'] !== null) {
                        $this->_registeredPrompts[$updates['name']] = $registeredPrompt;
                        $name = $updates['name'];
                    }
                }
                $this->sendPromptListChanged();
            },
            onRemove: function () use (&$name) {
                unset($this->_registeredPrompts[$name]);
                $this->sendPromptListChanged();
            }
        );

        $this->_registeredPrompts[$name] = $registeredPrompt;

        return $registeredPrompt;
    }

    // Completion Methods

    private function setCompletionRequestHandler(): void
    {
        if ($this->_completionHandlerInitialized) {
            return;
        }

        $this->server->registerCapabilities(new ServerCapabilities(
            completions: []
        ));

        $this->server->assertCanSetRequestHandler(CompleteRequest::METHOD);

        $this->server->setRequestHandler(
            CompleteRequest::class,
            function (CompleteRequest $request, RequestHandlerExtra $extra): \Amp\Future {
                return async(function () use ($request, $extra) {
                    $ref = $request->getRef();

                    if ($ref instanceof PromptReference) {
                        return $this->handlePromptCompletion($request, $ref);
                    } elseif ($ref instanceof ResourceTemplateReference) {
                        return $this->handleResourceCompletion($request, $ref);
                    } else {
                        throw new McpError(
                            ErrorCode::InvalidParams,
                            "Invalid completion reference type"
                        );
                    }
                });
            }
        );

        $this->_completionHandlerInitialized = true;
    }

    private function handlePromptCompletion(CompleteRequest $request, PromptReference $ref): CompleteResult
    {
        $prompt = $this->_registeredPrompts[$ref->getName()] ?? null;

        if ($prompt === null) {
            throw new McpError(
                ErrorCode::InvalidParams,
                "Prompt {$ref->getName()} not found"
            );
        }

        if (!$prompt->enabled) {
            throw new McpError(
                ErrorCode::InvalidParams,
                "Prompt {$ref->getName()} disabled"
            );
        }

        if ($prompt->argsSchema === null) {
            return CompleteResult::fromArray(EMPTY_COMPLETION_RESULT);
        }

        // Check if the argument field has a completable wrapper
        $argumentName = $request->getArgument()['name'] ?? '';
        $field = $this->getSchemaField($prompt->argsSchema, $argumentName);

        if (!($field instanceof Completable)) {
            return CompleteResult::fromArray(EMPTY_COMPLETION_RESULT);
        }

        $completeCallback = $field->getCompleteCallback();
        $value = $request->getArgument()['value'] ?? '';
        $context = $request->getContext();

        $suggestions = $completeCallback($value, $context);

        if ($suggestions instanceof \Amp\Future) {
            $suggestions = $suggestions->await();
        }

        return $this->createCompletionResult($suggestions);
    }

    private function handleResourceCompletion(CompleteRequest $request, ResourceTemplateReference $ref): CompleteResult
    {
        $template = null;
        foreach ($this->_registeredResourceTemplates as $t) {
            if ((string)$t->resourceTemplate->getUriTemplate() === $ref->getUri()) {
                $template = $t;
                break;
            }
        }

        if ($template === null) {
            // Check if it's a fixed resource URI
            if (isset($this->_registeredResources[$ref->getUri()])) {
                // Attempting to autocomplete a fixed resource URI is not an error
                return CompleteResult::fromArray(EMPTY_COMPLETION_RESULT);
            }

            throw new McpError(
                ErrorCode::InvalidParams,
                "Resource template {$ref->getUri()} not found"
            );
        }

        $argumentName = $request->getArgument()['name'] ?? '';
        $completer = $template->resourceTemplate->getCompleteCallback($argumentName);

        if ($completer === null) {
            return CompleteResult::fromArray(EMPTY_COMPLETION_RESULT);
        }

        $value = $request->getArgument()['value'] ?? '';
        $context = $request->getContext();

        $suggestions = $completer($value, $context);

        if ($suggestions instanceof \Amp\Future) {
            $suggestions = $suggestions->await();
        }

        return $this->createCompletionResult($suggestions);
    }

    private function createCompletionResult(array $suggestions): CompleteResult
    {
        return CompleteResult::fromArray([
            'completion' => [
                'values' => array_slice($suggestions, 0, 100),
                'total' => count($suggestions),
                'hasMore' => count($suggestions) > 100,
            ],
        ]);
    }

    // Notification Methods

    /**
     * Sends a logging message to the client, if connected.
     * Note: You only need to send the parameters object, not the entire JSON RPC message
     * 
     * @param array{level: string, logger?: string, data?: mixed, timestamp?: string} $params
     * @param string|null $sessionId optional for stateless and backward compatibility
     */
    public function sendLoggingMessage(array $params, ?string $sessionId = null): \Amp\Future
    {
        return $this->server->sendLoggingMessage($params, $sessionId);
    }

    /**
     * Sends a resource list changed event to the client, if connected.
     */
    public function sendResourceListChanged(): void
    {
        if ($this->isConnected()) {
            $this->server->sendResourceListChanged();
        }
    }

    /**
     * Sends a tool list changed event to the client, if connected.
     */
    public function sendToolListChanged(): void
    {
        if ($this->isConnected()) {
            $this->server->sendToolListChanged();
        }
    }

    /**
     * Sends a prompt list changed event to the client, if connected.
     */
    public function sendPromptListChanged(): void
    {
        if ($this->isConnected()) {
            $this->server->sendPromptListChanged();
        }
    }

    // Helper Methods

    /**
     * Check if a value looks like a schema (has validation properties)
     */
    private function isSchema($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        // Simple heuristic: check for common schema properties
        return isset($value['type']) ||
            isset($value['properties']) ||
            isset($value['required']) ||
            isset($value['parse']) ||
            isset($value['safeParse']);
    }

    /**
     * Convert schema to JSON Schema format
     */
    private function schemaToJsonSchema($schema): array
    {
        return JsonSchemaValidator::normalizeSchema($schema);
    }

    /**
     * Extract prompt arguments from schema
     */
    private function promptArgumentsFromSchema($schema): array
    {
        return JsonSchemaValidator::extractPromptArguments($schema);
    }

    /**
     * Get a field from a schema by name
     */
    private function getSchemaField($schema, string $fieldName)
    {
        return JsonSchemaValidator::getSchemaField($schema, $fieldName);
    }
}
