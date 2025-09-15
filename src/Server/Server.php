<?php

declare(strict_types=1);

namespace MCP\Server;

use function Amp\async;

use MCP\Shared\Protocol;
use MCP\Shared\RequestHandlerExtra;
use MCP\Shared\RequestOptions;

use MCP\Shared\Transport;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Implementation;
use MCP\Types\LoggingLevel;
use MCP\Types\Notification;
use MCP\Types\Notifications\InitializedNotification;
use MCP\Types\Notifications\ResourceUpdatedNotification;
use MCP\Types\Protocol as ProtocolConstants;
use MCP\Types\Request;
use MCP\Types\Requests\CreateMessageRequest;
use MCP\Types\Requests\ElicitRequest;
use MCP\Types\Requests\InitializeRequest;
use MCP\Types\Requests\ListRootsRequest;
use MCP\Types\Requests\SetLevelRequest;
use MCP\Types\Result;
use MCP\Types\Results\InitializeResult;
use MCP\Types\ServerNotification;
use MCP\Types\ServerRequest;
use MCP\Types\ServerResult;

use MCP\Validation\ValidationService;

/**
 * An MCP server on top of a pluggable transport.
 *
 * This server will automatically respond to the initialization flow as initiated from the client.
 *
 * @template RequestT of Request
 * @template NotificationT of Notification
 * @template ResultT of Result
 *
 * @extends Protocol<ServerRequest|RequestT, ServerNotification|NotificationT, ServerResult|ResultT>
 */
class Server extends Protocol
{
    private ?ClientCapabilities $_clientCapabilities = null;

    private ?Implementation $_clientVersion = null;

    private ServerCapabilities $_capabilities;

    private ?string $_instructions;

    /** @var array<string, LoggingLevel> Map log levels by session id */
    private array $_loggingLevels = [];

    /**
     * Callback for when initialization has fully completed (i.e., the client has sent an `initialized` notification).
     *
     * @var callable|null
     */
    public $oninitialized = null;

    /**
     * Initializes this server with the given name and version information.
     */
    public function __construct(
        private readonly Implementation $_serverInfo,
        ?ServerOptions $options = null
    ) {
        parent::__construct($options);
        $this->_capabilities = $options?->capabilities ?? new ServerCapabilities();
        $this->_instructions = $options?->instructions;

        // Set up initialization handlers
        $this->setRequestHandler(
            InitializeRequest::class,
            function (InitializeRequest $request, RequestHandlerExtra $extra) {
                return $this->_oninitialize($request);
            }
        );

        $this->setNotificationHandler(
            InitializedNotification::class,
            function (InitializedNotification $notification) {
                if ($this->oninitialized !== null) {
                    ($this->oninitialized)();
                }
            }
        );

        // Set up logging level handler if logging capability is enabled
        if ($this->_capabilities->hasLogging()) {
            $this->setRequestHandler(
                SetLevelRequest::class,
                function (SetLevelRequest $request, RequestHandlerExtra $extra) {
                    $transportSessionId = $extra->sessionId ??
                        ($extra->requestInfo['headers']['mcp-session-id'] ?? null);

                    $level = $request->getLevel();
                    if ($transportSessionId && $level !== null) {
                        $this->_loggingLevels[$transportSessionId] = $level;
                    }

                    return new Result();
                }
            );
        }
    }

    /**
     * Check if a message with the given level is ignored for the given session id.
     */
    private function isMessageIgnored(LoggingLevel $level, string $sessionId): bool
    {
        $currentLevel = $this->_loggingLevels[$sessionId] ?? null;
        if ($currentLevel === null) {
            return false;
        }

        return $level->getSeverity() < $currentLevel->getSeverity();
    }

    /**
     * Registers new capabilities. This can only be called before connecting to a transport.
     *
     * The new capabilities will be merged with any existing capabilities previously given (e.g., at initialization).
     */
    public function registerCapabilities(ServerCapabilities $capabilities): void
    {
        if ($this->getTransport() !== null) {
            throw new \Error('Cannot register capabilities after connecting to transport');
        }

        $this->_capabilities = \MCP\Shared\mergeCapabilities($this->_capabilities, $capabilities);
    }

    /**
     * {@inheritDoc}
     */
    protected function assertCapabilityForMethod(string $method): void
    {
        switch ($method) {
            case 'sampling/createMessage':
                if ($this->_clientCapabilities === null || $this->_clientCapabilities->getSampling() === null) {
                    throw new \Error("Client does not support sampling (required for {$method})");
                }
                break;

            case 'elicitation/create':
                if ($this->_clientCapabilities === null || !$this->_clientCapabilities->hasElicitation()) {
                    throw new \Error("Client does not support elicitation (required for {$method})");
                }
                break;

            case 'roots/list':
                if ($this->_clientCapabilities === null || !$this->_clientCapabilities->hasRoots()) {
                    throw new \Error("Client does not support listing roots (required for {$method})");
                }
                break;

            case 'ping':
                // No specific capability required for ping
                break;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function assertNotificationCapability(string $method): void
    {
        switch ($method) {
            case 'notifications/message':
                if (!$this->_capabilities->hasLogging()) {
                    throw new \Error("Server does not support logging (required for {$method})");
                }
                break;

            case 'notifications/resources/updated':
            case 'notifications/resources/list_changed':
                if (!$this->_capabilities->hasResources()) {
                    throw new \Error("Server does not support notifying about resources (required for {$method})");
                }
                break;

            case 'notifications/tools/list_changed':
                if (!$this->_capabilities->hasTools()) {
                    throw new \Error("Server does not support notifying of tool list changes (required for {$method})");
                }
                break;

            case 'notifications/prompts/list_changed':
                if (!$this->_capabilities->hasPrompts()) {
                    throw new \Error("Server does not support notifying of prompt list changes (required for {$method})");
                }
                break;

            case 'notifications/cancelled':
            case 'notifications/progress':
                // Cancellation and progress notifications are always allowed
                break;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function assertRequestHandlerCapability(string $method): void
    {
        switch ($method) {
            case 'sampling/createMessage':
                if ($this->_capabilities->getAdditionalProperties()['sampling'] ?? null === null) {
                    throw new \Error("Server does not support sampling (required for {$method})");
                }
                break;

            case 'logging/setLevel':
                if (!$this->_capabilities->hasLogging()) {
                    throw new \Error("Server does not support logging (required for {$method})");
                }
                break;

            case 'prompts/get':
            case 'prompts/list':
                if (!$this->_capabilities->hasPrompts()) {
                    throw new \Error("Server does not support prompts (required for {$method})");
                }
                break;

            case 'resources/list':
            case 'resources/templates/list':
            case 'resources/read':
                if (!$this->_capabilities->hasResources()) {
                    throw new \Error("Server does not support resources (required for {$method})");
                }
                break;

            case 'tools/call':
            case 'tools/list':
                if (!$this->_capabilities->hasTools()) {
                    throw new \Error("Server does not support tools (required for {$method})");
                }
                break;

            case 'ping':
            case 'initialize':
                // No specific capability required for these methods
                break;
        }
    }

    /**
     * Handle the initialize request from the client.
     */
    private function _oninitialize(InitializeRequest $request): InitializeResult
    {
        $requestedVersion = $request->getProtocolVersion();

        $this->_clientCapabilities = $request->getCapabilities();
        $this->_clientVersion = $request->getClientInfo();

        $protocolVersion = in_array($requestedVersion ?? '', ProtocolConstants::SUPPORTED_PROTOCOL_VERSIONS, true)
            ? $requestedVersion
            : ProtocolConstants::LATEST_PROTOCOL_VERSION;

        return new InitializeResult(
            protocolVersion: $protocolVersion,
            capabilities: $this->getCapabilities(),
            serverInfo: $this->_serverInfo,
            instructions: $this->_instructions
        );
    }

    /**
     * After initialization has completed, this will be populated with the client's reported capabilities.
     */
    public function getClientCapabilities(): ?ClientCapabilities
    {
        return $this->_clientCapabilities;
    }

    /**
     * After initialization has completed, this will be populated with information about the client's name and version.
     */
    public function getClientVersion(): ?Implementation
    {
        return $this->_clientVersion;
    }

    /**
     * Get the current server capabilities.
     */
    private function getCapabilities(): ServerCapabilities
    {
        return $this->_capabilities;
    }

    /**
     * Send a ping request to the client.
     */
    public function ping(): \Amp\Future
    {
        return $this->request(
            new Request('ping'),
            new ValidationService(),
            null
        );
    }

    /**
     * Request the client to create a message.
     */
    public function createMessage(
        array $params,
        ?RequestOptions $options = null
    ): \Amp\Future {
        $validationService = new ValidationService();

        return $this->request(
            CreateMessageRequest::fromArray(['params' => $params]),
            $validationService,
            $options
        );
    }

    /**
     * Request the client to elicit input from the user.
     */
    public function elicitInput(
        array $params,
        ?RequestOptions $options = null
    ): \Amp\Future {
        return async(function () use ($params, $options) {
            $validationService = new ValidationService();
            $result = $this->request(
                ElicitRequest::fromArray(['params' => $params]),
                $validationService,
                $options
            )->await();

            // Validate the response content against the requested schema if action is "accept"
            if (is_array($result) && ($result['action'] ?? null) === 'accept' && isset($result['content'])) {
                // Validate the result structure
                $validationService->validateElicitResult($result);

                // Additional content validation could be added here based on the requested schema
                // from the original ElicitRequest parameters
            }

            return $result;
        });
    }

    /**
     * Request the client to list roots.
     */
    public function listRoots(
        ?array $params = null,
        ?RequestOptions $options = null
    ): \Amp\Future {
        $validationService = new ValidationService();

        return $this->request(
            ListRootsRequest::fromArray(['params' => $params]),
            $validationService,
            $options
        );
    }

    /**
     * Sends a logging message to the client, if connected.
     * Note: You only need to send the parameters object, not the entire JSON RPC message.
     *
     * @param array{level: string, logger?: string, data?: mixed, timestamp?: string} $params
     * @param string|null $sessionId optional for stateless and backward compatibility
     */
    public function sendLoggingMessage(array $params, ?string $sessionId = null): \Amp\Future
    {
        return async(function () use ($params, $sessionId) {
            if ($this->_capabilities->hasLogging()) {
                if (isset($params['level'])) {
                    $level = LoggingLevel::from($params['level']);
                    if (!$sessionId || !$this->isMessageIgnored($level, $sessionId)) {
                        return $this->notification(
                            new Notification('notifications/message', $params)
                        )->await();
                    }
                } else {
                    // No level specified, just send the notification
                    return $this->notification(
                        new Notification('notifications/message', $params)
                    )->await();
                }
            }
        });
    }

    /**
     * Send a resource updated notification.
     */
    public function sendResourceUpdated(array $params): \Amp\Future
    {
        return $this->notification(
            new ResourceUpdatedNotification($params)
        );
    }

    /**
     * Send a resource list changed notification.
     */
    public function sendResourceListChanged(): \Amp\Future
    {
        return $this->notification(
            new \MCP\Types\Notifications\ResourceListChangedNotification()
        );
    }

    /**
     * Send a tool list changed notification.
     */
    public function sendToolListChanged(): \Amp\Future
    {
        return $this->notification(
            new \MCP\Types\Notifications\ToolListChangedNotification()
        );
    }

    /**
     * Send a prompt list changed notification.
     */
    public function sendPromptListChanged(): \Amp\Future
    {
        return $this->notification(
            new \MCP\Types\Notifications\PromptListChangedNotification()
        );
    }
}
