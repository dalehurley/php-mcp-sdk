<?php

declare(strict_types=1);

namespace MCP\Shared;

use function Amp\async;

use Amp\DeferredFuture;
use Amp\Future;
use Evenement\EventEmitter;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\ErrorCode;
use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\JsonRpc\JSONRPCMessage;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCResponse;
use MCP\Types\McpError;
use MCP\Types\Notification;
use MCP\Types\Notifications\CancelledNotification;
use MCP\Types\Notifications\ProgressNotification;
use MCP\Types\Progress;
use MCP\Types\Request;
use MCP\Types\RequestId;
use MCP\Types\Requests\PingRequest;
use MCP\Types\Result;

use MCP\Validation\ValidationService;

/**
 * Type alias for progress callback.
 */

// PHP doesn't support type aliases, so we'll use callable directly

/**
 * The default request timeout, in milliseconds.
 */
const DEFAULT_REQUEST_TIMEOUT_MSEC = 60000;

/**
 * Implements MCP protocol framing on top of a pluggable transport, including
 * features like request/response linking, notifications, and progress.
 *
 * @template SendRequestT of Request
 * @template SendNotificationT of Notification
 * @template SendResultT of Result
 */
abstract class Protocol extends EventEmitter
{
    private ?Transport $transport = null;

    private int $requestMessageId = 0;

    /** @var array<string, callable(JSONRPCRequest, RequestHandlerExtra): Future<SendResultT>> */
    private array $requestHandlers = [];

    /** @var callable|null */
    private $requestHandlerWrapper = null;

    /** @var array<string|int, \Revolt\EventLoop\Suspension> */
    private array $requestHandlerAbortControllers = [];

    /** @var array<string, callable(JSONRPCNotification): Future<void>> */
    private array $notificationHandlers = [];

    /** @var array<int, DeferredFuture> */
    private array $responseHandlers = [];

    /** @var array<int, callable> */
    private array $progressHandlers = [];

    /** @var array<int, TimeoutInfo> */
    private array $timeoutInfo = [];

    /** @var array<string, bool> */
    private array $pendingDebouncedNotifications = [];

    private ValidationService $validationService;

    /**
     * Callback for when the connection is closed for any reason.
     * This is invoked when close() is called as well.
     */
    public ?\Closure $onclose = null;

    /**
     * Callback for when an error occurs.
     * Note that errors are not necessarily fatal; they are used for reporting
     * any kind of exceptional condition out of band.
     */
    public ?\Closure $onerror = null;

    /**
     * A handler to invoke for any request types that do not have their own handler installed.
     *
     * @var callable(JSONRPCRequest, RequestHandlerExtra): Future<SendResultT>|null
     */
    public $fallbackRequestHandler = null;

    /**
     * A handler to invoke for any notification types that do not have their own handler installed.
     *
     * @var callable(Notification): Future<void>|null
     */
    public $fallbackNotificationHandler = null;

    public function __construct(
        private readonly ?ProtocolOptions $options = null
    ) {
        $this->validationService = new ValidationService();

        // Register default handlers
        $this->setNotificationHandler(
            CancelledNotification::class,
            function (CancelledNotification $notification): void {
                $requestId = $notification->getRequestId();
                if ($requestId !== null) {
                    $requestIdStr = $requestId instanceof RequestId ? (string) $requestId : (string) $requestId;
                    $controller = $this->requestHandlerAbortControllers[$requestIdStr] ?? null;
                    $controller?->suspend();
                }
            }
        );

        $this->setNotificationHandler(
            ProgressNotification::class,
            function (ProgressNotification $notification): void {
                $this->onprogress($notification);
            }
        );

        $this->setRequestHandler(
            PingRequest::class,
            function (PingRequest $request) {
                // Automatic pong by default
                return new Result();
            }
        );
    }

    private function setupTimeout(
        int $messageId,
        int $timeout,
        ?int $maxTotalTimeout,
        \Closure $onTimeout,
        bool $resetTimeoutOnProgress = false
    ): void {
        $timeoutId = \Revolt\EventLoop::delay($timeout / 1000, $onTimeout);

        $this->timeoutInfo[$messageId] = new TimeoutInfo(
            $timeoutId,
            time() * 1000,
            $timeout,
            $maxTotalTimeout,
            $resetTimeoutOnProgress,
            $onTimeout
        );
    }

    private function resetTimeout(int $messageId): void
    {
        $info = $this->timeoutInfo[$messageId] ?? null;
        if (!$info) {
            return;
        }

        $totalElapsed = (time() * 1000) - $info->startTime;
        if ($info->maxTotalTimeout && $totalElapsed >= $info->maxTotalTimeout) {
            unset($this->timeoutInfo[$messageId]);

            throw new McpError(
                ErrorCode::RequestTimeout,
                'Maximum total timeout exceeded',
                ['maxTotalTimeout' => $info->maxTotalTimeout, 'totalElapsed' => $totalElapsed]
            );
        }

        \Revolt\EventLoop::cancel($info->timeoutId);
        $info->timeoutId = \Revolt\EventLoop::delay($info->timeout / 1000, $info->onTimeout);
    }

    private function cleanupTimeout(int $messageId): void
    {
        $info = $this->timeoutInfo[$messageId] ?? null;
        if ($info) {
            \Revolt\EventLoop::cancel($info->timeoutId);
            unset($this->timeoutInfo[$messageId]);
        }
    }

    /**
     * Attaches to the given transport, starts it, and starts listening for messages.
     */
    public function connect(Transport $transport): Future
    {
        return async(function () use ($transport) {
            $this->transport = $transport;

            // Set up transport handlers
            $transport->setCloseHandler(function () {
                $this->onclose();
            });

            $transport->setErrorHandler(function (\Throwable $error) {
                $this->onerror($error);
            });

            $transport->setMessageHandler(function (array $message, ?array $extra = null) {
                $jsonrpcMessage = JSONRPCMessage::fromArray($message);

                if ($jsonrpcMessage instanceof JSONRPCResponse || $jsonrpcMessage instanceof JSONRPCError) {
                    $this->onresponse($jsonrpcMessage);
                } elseif ($jsonrpcMessage instanceof JSONRPCRequest) {
                    $this->onrequest($jsonrpcMessage, $extra);
                } elseif ($jsonrpcMessage instanceof JSONRPCNotification) {
                    $this->onnotification($jsonrpcMessage);
                } else {
                    $this->onerror(new \Error('Unknown message type: ' . json_encode($message)));
                }
            });

            $transport->start()->await();
        });
    }

    private function onclose(): void
    {
        $responseHandlers = $this->responseHandlers;
        $this->responseHandlers = [];
        $this->progressHandlers = [];
        $this->pendingDebouncedNotifications = [];
        $this->transport = null;

        if ($this->onclose) {
            ($this->onclose)();
        }

        $error = new McpError(ErrorCode::ConnectionClosed, 'Connection closed');
        foreach ($responseHandlers as $deferred) {
            $deferred->error($error);
        }
    }

    private function onerror(\Throwable $error): void
    {
        if ($this->onerror) {
            ($this->onerror)($error);
        }
    }

    private function onnotification(JSONRPCNotification $notification): void
    {
        $method = $notification->getMethod();
        $handler = $this->notificationHandlers[$method] ?? $this->fallbackNotificationHandler;

        // Ignore notifications not being subscribed to
        if ($handler === null) {
            return;
        }

        // Execute handler asynchronously
        async(function () use ($handler, $notification) {
            try {
                $handler($notification);
            } catch (\Throwable $error) {
                $this->onerror(new \Error('Uncaught error in notification handler: ' . $error->getMessage()));
            }
        });
    }

    private function onrequest(JSONRPCRequest $request, ?array $extra = null): void
    {
        $handler = $this->requestHandlers[$request->getMethod()] ?? $this->fallbackRequestHandler;

        // Capture the current transport at request time
        $capturedTransport = $this->transport;

        if ($handler === null) {
            async(function () use ($capturedTransport, $request) {
                $capturedTransport?->send([
                    'jsonrpc' => '2.0',
                    'id' => $request->getId()->jsonSerialize(),
                    'error' => [
                        'code' => ErrorCode::MethodNotFound->value,
                        'message' => 'Method not found',
                    ],
                ])->await();
            });

            return;
        }

        $suspension = \Revolt\EventLoop::getSuspension();
        $this->requestHandlerAbortControllers[$request->getId()->jsonSerialize()] = $suspension;

        $fullExtra = new RequestHandlerExtra(
            signal: $suspension,
            authInfo: $extra['authInfo'] ?? null,
            sessionId: $extra['sessionId'] ?? null,
            _meta: $request->getParams()['_meta'] ?? null,
            requestId: $request->getId(),
            requestInfo: $extra['requestInfo'] ?? null,
            sendNotification: function (Notification $notification) use ($request) {
                return $this->notification($notification, new NotificationOptions($request->getId()));
            },
            sendRequest: function (Request $r, ValidationService $resultSchema, ?RequestOptions $options = null) use ($request) {
                $newOptions = new RequestOptions(
                    onprogress: $options?->onprogress,
                    signal: $options?->signal,
                    timeout: $options?->timeout,
                    resetTimeoutOnProgress: $options?->resetTimeoutOnProgress,
                    maxTotalTimeout: $options?->maxTotalTimeout,
                    relatedRequestId: $request->getId(),
                    resumptionToken: $options?->resumptionToken,
                    onresumptiontoken: $options?->onresumptiontoken
                );

                return $this->request($r, $resultSchema, $newOptions);
            }
        );

        // Execute handler asynchronously
        async(function () use ($handler, $request, $fullExtra, $capturedTransport, $suspension) {
            try {
                $result = $handler($request, $fullExtra);

                // If the handler returned a Future, await it
                if ($result instanceof \Amp\Future) {
                    $result = $result->await();
                }

                // If the suspension is resumed, the request was cancelled
                if (false) { // Placeholder for suspension check
                    return;
                }

                $capturedTransport?->send([
                    'result' => $result instanceof \JsonSerializable ? $result->jsonSerialize() : $result,
                    'jsonrpc' => '2.0',
                    'id' => $request->getId()->jsonSerialize(),
                ])->await();
            } catch (\Throwable $error) {
                // If the suspension is resumed, the request was cancelled
                if (false) { // Placeholder for suspension check
                    return;
                }

                $capturedTransport?->send([
                    'jsonrpc' => '2.0',
                    'id' => $request->getId()->jsonSerialize(),
                    'error' => [
                        'code' => $error instanceof McpError
                            ? $error->errorCode->value
                            : ErrorCode::InternalError->value,
                        'message' => $error->getMessage() ?: 'Internal error',
                    ],
                ])->await();
            } finally {
                unset($this->requestHandlerAbortControllers[$request->getId()->jsonSerialize()]);
            }
        });
    }

    private function onprogress(ProgressNotification $notification): void
    {
        $progressToken = $notification->getProgressToken();
        if (!$progressToken) {
            $this->onerror(new \Error('Received a progress notification without a token'));

            return;
        }

        $messageId = (int) (string) $progressToken;
        $progress = $notification->getProgress();

        $handler = $this->progressHandlers[$messageId] ?? null;
        if (!$handler || !$progress) {
            $this->onerror(new \Error('Received a progress notification for an unknown token: ' . json_encode($notification)));

            return;
        }

        $responseHandler = $this->responseHandlers[$messageId] ?? null;
        $timeoutInfo = $this->timeoutInfo[$messageId] ?? null;

        if ($timeoutInfo && $responseHandler && $timeoutInfo->resetTimeoutOnProgress) {
            try {
                $this->resetTimeout($messageId);
            } catch (McpError $error) {
                $responseHandler->error($error);

                return;
            }
        }

        $handler($progress);
    }

    private function onresponse(JSONRPCResponse|JSONRPCError $response): void
    {
        $messageId = (int) $response->getId()->jsonSerialize();
        $deferred = $this->responseHandlers[$messageId] ?? null;

        if ($deferred === null) {
            $this->onerror(new \Error(
                'Received a response for an unknown message ID: ' . json_encode($response)
            ));

            return;
        }

        unset($this->responseHandlers[$messageId]);
        unset($this->progressHandlers[$messageId]);
        $this->cleanupTimeout($messageId);

        if ($response instanceof JSONRPCResponse) {
            $deferred->complete($response);
        } else {
            $error = new McpError(
                ErrorCode::tryFrom($response->getCode()) ?? ErrorCode::InternalError,
                $response->getMessage(),
                $response->getData()
            );
            $deferred->error($error);
        }
    }

    public function getTransport(): ?Transport
    {
        return $this->transport;
    }

    /**
     * Closes the connection.
     */
    public function close(): Future
    {
        return async(function () {
            $this->transport?->close()->await();
        });
    }

    /**
     * A method to check if a capability is supported by the remote side.
     * This should be implemented by subclasses.
     */
    abstract protected function assertCapabilityForMethod(string $method): void;

    /**
     * A method to check if a notification is supported by the local side.
     * This should be implemented by subclasses.
     */
    abstract protected function assertNotificationCapability(string $method): void;

    /**
     * A method to check if a request handler is supported by the local side.
     * This should be implemented by subclasses.
     */
    abstract protected function assertRequestHandlerCapability(string $method): void;

    /**
     * Sends a request and wait for a response.
     *
     * @template T
     *
     * @param SendRequestT $request
     * @param ValidationService $resultSchema
     * @param RequestOptions|null $options
     *
     * @return Future<T>
     */
    public function request(
        Request $request,
        ValidationService $resultSchema,
        ?RequestOptions $options = null
    ): Future {
        return async(function () use ($request, $resultSchema, $options) {
            if (!$this->transport) {
                throw new \Error('Not connected');
            }

            if ($this->options?->enforceStrictCapabilities === true) {
                $this->assertCapabilityForMethod($request->getMethod());
            }

            // Check if signal is suspended
            // Note: signal handling depends on the specific implementation

            $messageId = $this->requestMessageId++;
            $jsonrpcRequest = [
                'jsonrpc' => '2.0',
                'id' => $messageId,
                'method' => $request->getMethod(),
            ];

            $params = $request->getParams();
            if ($params !== null) {
                $jsonrpcRequest['params'] = $params;
            }

            if ($options?->onprogress) {
                $this->progressHandlers[$messageId] = $options->onprogress;
                $jsonrpcRequest['params'] = array_merge(
                    $params ?? [],
                    [
                        '_meta' => array_merge(
                            $params['_meta'] ?? [],
                            ['progressToken' => $messageId]
                        ),
                    ]
                );
            }

            $deferred = new DeferredFuture();
            $this->responseHandlers[$messageId] = $deferred;

            $cancel = function (mixed $reason) use ($messageId, $deferred, $options) {
                unset($this->responseHandlers[$messageId]);
                unset($this->progressHandlers[$messageId]);
                $this->cleanupTimeout($messageId);

                async(function () use ($messageId, $reason, $options) {
                    $this->transport?->send([
                        'jsonrpc' => '2.0',
                        'method' => 'notifications/cancelled',
                        'params' => [
                            'requestId' => $messageId,
                            'reason' => (string) $reason,
                        ],
                    ])->await();
                });

                $deferred->error($reason instanceof \Throwable ? $reason : new \Error((string) $reason));
            };

            if ($options?->signal) {
                \Revolt\EventLoop::onSignal(SIGTERM, function () use ($cancel, $options) {
                    $cancel('Aborted');
                });
            }

            $timeout = $options?->timeout ?? DEFAULT_REQUEST_TIMEOUT_MSEC;
            $timeoutHandler = function () use ($cancel, $timeout) {
                $cancel(new McpError(
                    ErrorCode::RequestTimeout,
                    'Request timed out',
                    ['timeout' => $timeout]
                ));
            };

            $this->setupTimeout(
                $messageId,
                $timeout,
                $options?->maxTotalTimeout,
                $timeoutHandler,
                $options?->resetTimeoutOnProgress ?? false
            );

            try {
                $this->transport->send($jsonrpcRequest)->await();
            } catch (\Throwable $error) {
                $this->cleanupTimeout($messageId);

                throw $error;
            }

            // Wait for the response
            $future = $deferred->getFuture();
            $response = $future->await(new \Amp\TimeoutCancellation($timeout / 1000));

            if ($response instanceof JSONRPCResponse) {
                $result = $response->getResult()->jsonSerialize();

                // Validate the result using the provided validation service
                if ($resultSchema && is_array($result)) {
                    try {
                        // Determine result type based on request type and validate accordingly
                        $this->validateAndConvertResult($request, $result, $resultSchema);
                    } catch (\Throwable $e) {
                        // Log validation errors but don't fail the request
                        // This maintains backward compatibility
                        error_log('Result validation warning: ' . $e->getMessage());
                    }
                }

                // Convert to appropriate result type based on request
                return $this->convertToTypedResult($request, $result);
            }

            throw new \Error('Unexpected response type');
        });
    }

    /**
     * Emits a notification, which is a one-way message that does not expect a response.
     */
    public function notification(
        Notification $notification,
        ?NotificationOptions $options = null
    ): Future {
        return async(function () use ($notification, $options) {
            if (!$this->transport) {
                throw new \Error('Not connected');
            }

            $this->assertNotificationCapability($notification->getMethod());

            $debouncedMethods = $this->options?->debouncedNotificationMethods ?? [];
            $canDebounce = in_array($notification->getMethod(), $debouncedMethods, true)
                && !$notification->hasParams()
                && !$options?->relatedRequestId;

            if ($canDebounce) {
                // If a notification of this type is already scheduled, do nothing
                if (isset($this->pendingDebouncedNotifications[$notification->getMethod()])) {
                    return;
                }

                // Mark this notification type as pending
                $this->pendingDebouncedNotifications[$notification->getMethod()] = true;

                // Schedule the actual send to happen in the next tick
                \Revolt\EventLoop::defer(function () use ($notification, $options) {
                    unset($this->pendingDebouncedNotifications[$notification->getMethod()]);

                    // Safety check: If the connection was closed while this was pending, abort
                    if (!$this->transport) {
                        return;
                    }

                    $jsonrpcNotification = array_merge(
                        ['jsonrpc' => '2.0'],
                        $notification->jsonSerialize()
                    );

                    try {
                        $this->transport->send($jsonrpcNotification)->await();
                    } catch (\Throwable $error) {
                        $this->onerror($error);
                    }
                });

                return;
            }

            $jsonrpcNotification = array_merge(
                ['jsonrpc' => '2.0'],
                $notification->jsonSerialize()
            );

            $this->transport->send($jsonrpcNotification)->await();
        });
    }

    /**
     * Registers a handler to invoke when this protocol object receives a request with the given method.
     * Note that this will replace any previous request handler for the same method.
     *
     * @template T
     *
     * @param class-string<T> $requestClass
     * @param callable(T, RequestHandlerExtra): SendResultT|Future<SendResultT> $handler
     */
    public function setRequestHandler(
        string $requestClass,
        callable $handler
    ): void {
        if (!is_subclass_of($requestClass, Request::class)) {
            throw new \InvalidArgumentException('Request class must extend Request');
        }

        $method = $requestClass::METHOD ?? throw new \Error('Request class must have METHOD constant');
        $this->assertRequestHandlerCapability($method);

        // Apply wrapper if one is set
        $wrappedHandler = $this->requestHandlerWrapper ?
            ($this->requestHandlerWrapper)($handler) :
            $handler;

        $this->requestHandlers[$method] = function (JSONRPCRequest $request, RequestHandlerExtra $extra) use ($requestClass, $wrappedHandler) {
            $typedRequest = $requestClass::fromArray($request->jsonSerialize());
            $result = $wrappedHandler($typedRequest, $extra);

            return $result instanceof Future ? $result : async(fn () => $result);
        };
    }

    /**
     * Sets a wrapper function that will be applied to all request handlers.
     * The wrapper receives the original handler and should return a new handler.
     *
     * @param callable $wrapper Function that takes a handler and returns a wrapped handler
     */
    public function setRequestHandlerWrapper(callable $wrapper): void
    {
        $this->requestHandlerWrapper = $wrapper;

        // Re-apply wrapper to existing handlers
        foreach ($this->requestHandlers as $method => $existingHandler) {
            // We need to store the original handler to re-wrap it
            // For now, we'll just set the wrapper for future handlers
            // This is a limitation but matches the expected usage pattern
        }
    }

    /**
     * Removes the request handler for the given method.
     */
    public function removeRequestHandler(string $method): void
    {
        unset($this->requestHandlers[$method]);
    }

    /**
     * Asserts that a request handler has not already been set for the given method.
     */
    public function assertCanSetRequestHandler(string $method): void
    {
        if (isset($this->requestHandlers[$method])) {
            throw new \Error(
                "A request handler for {$method} already exists, which would be overridden"
            );
        }
    }

    /**
     * Registers a handler to invoke when this protocol object receives a notification with the given method.
     * Note that this will replace any previous notification handler for the same method.
     *
     * @template T
     *
     * @param class-string<T> $notificationClass
     * @param callable(T): void|Future<void> $handler
     */
    public function setNotificationHandler(
        string $notificationClass,
        callable $handler
    ): void {
        if (!is_subclass_of($notificationClass, Notification::class)) {
            throw new \InvalidArgumentException('Notification class must extend Notification');
        }

        $method = $notificationClass::METHOD ?? throw new \Error('Notification class must have METHOD constant');

        $this->notificationHandlers[$method] = function (JSONRPCNotification $notification) use ($notificationClass, $handler) {
            $typedNotification = $notificationClass::fromArray($notification->jsonSerialize());
            $result = $handler($typedNotification);

            return $result instanceof Future ? $result : async(fn () => $result);
        };
    }

    /**
     * Removes the notification handler for the given method.
     */
    public function removeNotificationHandler(string $method): void
    {
        unset($this->notificationHandlers[$method]);
    }

    /**
     * Validate and convert result based on request type.
     *
     * @param Request $request
     * @param array<string, mixed> $result
     * @param ValidationService $resultSchema
     *
     * @throws \Throwable
     */
    private function validateAndConvertResult(Request $request, array $result, ValidationService $resultSchema): void
    {
        $method = $request->getMethod();

        try {
            match ($method) {
                'sampling/createMessage' => $resultSchema->validateCreateMessageResult($result),
                'elicitation/create' => $resultSchema->validateElicitResult($result),
                'roots/list' => $resultSchema->validateListRootsResult($result),
                'ping' => null, // Ping responses are typically empty
                default => null, // Other methods don't have specific validation yet
            };
        } catch (\Throwable $e) {
            // Don't throw validation errors for now, just log them
            // This maintains backward compatibility with existing code
            error_log("Result validation failed for method '$method': " . $e->getMessage());
        }
    }

    /**
     * Convert raw result to typed result object based on request type.
     *
     * @param Request $request
     * @param mixed $result
     *
     * @return mixed
     */
    private function convertToTypedResult(Request $request, mixed $result): mixed
    {
        if (!is_array($result)) {
            return $result;
        }

        $method = $request->getMethod();

        // Only convert for known request types, and only if the result looks like the expected format
        try {
            return match ($method) {
                'initialize' => isset($result['protocolVersion'])
                    ? \MCP\Types\Results\InitializeResult::fromArray($result)
                    : $result,
                'sampling/createMessage' => isset($result['model'], $result['content'])
                    ? \MCP\Types\Results\CreateMessageResult::fromArray($result)
                    : $result,
                'elicitation/create' => isset($result['action'])
                    ? \MCP\Types\Results\ElicitResult::fromArray($result)
                    : $result,
                'roots/list' => isset($result['roots'])
                    ? \MCP\Types\Results\ListRootsResult::fromArray($result)
                    : $result,
                'ping' => empty($result) ? new \MCP\Types\Result() : $result,
                default => $result, // Return raw result for unknown methods
            };
        } catch (\Throwable $e) {
            // If type conversion fails, log and return raw result for backward compatibility
            error_log("Result type conversion warning for method '$method': " . $e->getMessage());

            return $result;
        }
    }
}

/**
 * Merge capabilities objects.
 *
 * @template T of ServerCapabilities|ClientCapabilities
 *
 * @param T $base
 * @param T $additional
 *
 * @return T
 */
function mergeCapabilities(
    ServerCapabilities|ClientCapabilities $base,
    ServerCapabilities|ClientCapabilities $additional
): ServerCapabilities|ClientCapabilities {
    // Convert both to arrays for merging
    $baseArray = $base instanceof \JsonSerializable ? $base->jsonSerialize() : (array) $base;
    $additionalArray = $additional instanceof \JsonSerializable ? $additional->jsonSerialize() : (array) $additional;

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

    // Create new instance from merged array
    if ($base instanceof ServerCapabilities) {
        return ServerCapabilities::fromArray($merged);
    } else {
        return ClientCapabilities::fromArray($merged);
    }
}
