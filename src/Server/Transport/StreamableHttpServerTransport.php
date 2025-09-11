<?php

declare(strict_types=1);

namespace MCP\Server\Transport;

use MCP\Shared\Transport;
use MCP\Types\JsonRpc\JSONRPCMessage;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCResponse;
use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\Protocol;
use MCP\Types\RequestId;
use MCP\Types\McpError;
use MCP\Types\ErrorCode;
use MCP\Types\Supporting\MessageExtraInfo;
use MCP\Types\Supporting\RequestInfo;
use MCP\Shared\ReadBuffer;
use Amp\Future;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\HttpStatus;
use function Amp\async;
use function Amp\delay;

/**
 * Interface for resumability support via event storage
 */
interface EventStore
{
    /**
     * Stores an event for later retrieval
     * 
     * @param string $streamId ID of the stream the event belongs to
     * @param JSONRPCMessage $message The JSON-RPC message to store
     * @return Future<string> The generated event ID for the stored event
     */
    public function storeEvent(string $streamId, JSONRPCMessage $message): Future;

    /**
     * Replay events after a specific event ID
     * 
     * @param string $lastEventId The last event ID received by client
     * @param callable(string, JSONRPCMessage): Future<void> $send Callback to send events
     * @return Future<string> The stream ID
     */
    public function replayEventsAfter(string $lastEventId, callable $send): Future;
}

/**
 * Configuration options for StreamableHttpServerTransport
 */
class StreamableHttpServerTransportOptions
{
    /**
     * @param callable(): string|null $sessionIdGenerator Function that generates session IDs (null for stateless)
     * @param callable(string): void|Future<void>|null $onsessioninitialized Callback when session is initialized
     * @param callable(string): void|Future<void>|null $onsessionclosed Callback when session is closed
     * @param bool $enableJsonResponse If true, return JSON responses instead of SSE streams
     * @param EventStore|null $eventStore Event store for resumability support
     * @param array<string>|null $allowedHosts List of allowed host headers
     * @param array<string>|null $allowedOrigins List of allowed origin headers
     * @param bool $enableDnsRebindingProtection Enable DNS rebinding protection
     */
    public function __construct(
        public readonly mixed $sessionIdGenerator = null,
        public readonly mixed $onsessioninitialized = null,
        public readonly mixed $onsessionclosed = null,
        public readonly bool $enableJsonResponse = false,
        public readonly ?EventStore $eventStore = null,
        public readonly ?array $allowedHosts = null,
        public readonly ?array $allowedOrigins = null,
        public readonly bool $enableDnsRebindingProtection = false
    ) {}
}

/**
 * Server transport for Streamable HTTP: this implements the MCP Streamable HTTP transport specification.
 * It supports both SSE streaming and direct HTTP responses.
 * 
 * In stateful mode:
 * - Session ID is generated and included in response headers
 * - Session ID is always included in initialization responses
 * - Requests with invalid session IDs are rejected with 404 Not Found
 * - Non-initialization requests without a session ID are rejected with 400 Bad Request
 * - State is maintained in-memory (connections, message history)
 * 
 * In stateless mode:
 * - No Session ID is included in any responses
 * - No session validation is performed
 */
class StreamableHttpServerTransport implements Transport
{
    private const MAXIMUM_MESSAGE_SIZE = 4 * 1024 * 1024; // 4MB
    private const STANDALONE_SSE_STREAM_ID = '_GET_stream';

    private StreamableHttpServerTransportOptions $_options;
    private bool $_started = false;
    private bool $_initialized = false;

    /** @var ?string Session ID when in stateful mode */
    public ?string $sessionId = null;

    /** @var array<string, Response> Active SSE streams mapped by stream ID */
    private array $_streamMapping = [];

    /** @var array<string, string> Request ID to stream ID mapping */
    private array $_requestToStreamMapping = [];

    /** @var array<string, JSONRPCMessage> Pending responses by request ID */
    private array $_requestResponseMap = [];

    /** @var callable(array, array|null): void|null */
    private $onmessage = null;

    /** @var callable(): void|null */
    private $onclose = null;

    /** @var callable(\Throwable): void|null */
    private $onerror = null;

    public function __construct(StreamableHttpServerTransportOptions $options)
    {
        $this->_options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function setMessageHandler(callable $handler): void
    {
        $this->onmessage = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function setCloseHandler(callable $handler): void
    {
        $this->onclose = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function setErrorHandler(callable $handler): void
    {
        $this->onerror = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function start(): Future
    {
        return async(function () {
            if ($this->_started) {
                throw new \RuntimeException("Transport already started");
            }
            $this->_started = true;
        });
    }

    /**
     * Handles an incoming HTTP request
     * 
     * @param Request $request The Amp HTTP request
     * @param mixed $parsedBody Pre-parsed body (optional)
     * @param array|null $authInfo Authentication information
     * @return Future<Response>
     */
    public function handleRequest(Request $request, $parsedBody = null, ?array $authInfo = null): Future
    {
        return async(function () use ($request, $parsedBody, $authInfo) {
            // Validate request headers for DNS rebinding protection
            $validationError = $this->validateRequestHeaders($request);
            if ($validationError !== null) {
                return $this->jsonErrorResponse(
                    HttpStatus::FORBIDDEN,
                    -32000,
                    $validationError
                );
            }

            $method = $request->getMethod();

            switch ($method) {
                case 'POST':
                    return $this->handlePostRequest($request, $parsedBody, $authInfo);

                case 'GET':
                    return $this->handleGetRequest($request);

                case 'DELETE':
                    return $this->handleDeleteRequest($request);

                default:
                    return new Response(
                        HttpStatus::METHOD_NOT_ALLOWED,
                        ['Allow' => 'GET, POST, DELETE'],
                        json_encode([
                            'jsonrpc' => '2.0',
                            'error' => [
                                'code' => -32000,
                                'message' => 'Method not allowed.'
                            ],
                            'id' => null
                        ])
                    );
            }
        });
    }

    /**
     * Validates request headers for DNS rebinding protection
     * 
     * @return string|null Error message if validation fails
     */
    private function validateRequestHeaders(Request $request): ?string
    {
        if (!$this->_options->enableDnsRebindingProtection) {
            return null;
        }

        // Validate Host header
        if ($this->_options->allowedHosts !== null && count($this->_options->allowedHosts) > 0) {
            $hostHeader = $request->getHeader('Host');
            if (!$hostHeader || !in_array($hostHeader, $this->_options->allowedHosts, true)) {
                return "Invalid Host header: $hostHeader";
            }
        }

        // Validate Origin header
        if ($this->_options->allowedOrigins !== null && count($this->_options->allowedOrigins) > 0) {
            $originHeader = $request->getHeader('Origin');
            if (!$originHeader || !in_array($originHeader, $this->_options->allowedOrigins, true)) {
                return "Invalid Origin header: $originHeader";
            }
        }

        return null;
    }

    /**
     * Handle GET requests for SSE stream
     */
    private function handleGetRequest(Request $request): Future
    {
        return async(function () use ($request) {
            // Check Accept header
            $acceptHeader = $request->getHeader('Accept') ?? '';
            if (!str_contains($acceptHeader, 'text/event-stream')) {
                return $this->jsonErrorResponse(
                    HttpStatus::NOT_ACCEPTABLE,
                    -32000,
                    'Not Acceptable: Client must accept text/event-stream'
                );
            }

            // Validate session and protocol version
            $sessionValidation = $this->validateSession($request);
            if ($sessionValidation !== null) {
                return $sessionValidation;
            }

            $protocolValidation = $this->validateProtocolVersion($request);
            if ($protocolValidation !== null) {
                return $protocolValidation;
            }

            // Handle resumability if event store is available
            if ($this->_options->eventStore !== null) {
                $lastEventId = $request->getHeader('Last-Event-ID');
                if ($lastEventId !== null) {
                    return $this->replayEvents($lastEventId, $request);
                }
            }

            // Check if there's already an active standalone SSE stream
            if (isset($this->_streamMapping[self::STANDALONE_SSE_STREAM_ID])) {
                return $this->jsonErrorResponse(
                    HttpStatus::CONFLICT,
                    -32000,
                    'Conflict: Only one SSE stream is allowed per session'
                );
            }

            // Create SSE response
            $headers = [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'Connection' => 'keep-alive',
            ];

            if ($this->sessionId !== null) {
                $headers['Mcp-Session-Id'] = $this->sessionId;
            }

            // Create streaming response
            $stream = new \Amp\ByteStream\WritableIterableStream(8192);
            $response = new Response(HttpStatus::OK, $headers, $stream->getIterator());

            // Store the stream
            $this->_streamMapping[self::STANDALONE_SSE_STREAM_ID] = $response;

            // Clean up on stream close
            $stream->onClose(function () {
                unset($this->_streamMapping[self::STANDALONE_SSE_STREAM_ID]);
            });

            return $response;
        });
    }

    /**
     * Handle POST requests containing JSON-RPC messages
     */
    private function handlePostRequest(Request $request, $parsedBody, ?array $authInfo): Future
    {
        return async(function () use ($request, $parsedBody, $authInfo) {
            try {
                // Validate Accept header
                $acceptHeader = $request->getHeader('Accept') ?? '';
                if (
                    !str_contains($acceptHeader, 'application/json') ||
                    !str_contains($acceptHeader, 'text/event-stream')
                ) {
                    return $this->jsonErrorResponse(
                        HttpStatus::NOT_ACCEPTABLE,
                        -32000,
                        'Not Acceptable: Client must accept both application/json and text/event-stream'
                    );
                }

                // Validate Content-Type
                $contentType = $request->getHeader('Content-Type') ?? '';
                if (!str_contains($contentType, 'application/json')) {
                    return $this->jsonErrorResponse(
                        HttpStatus::UNSUPPORTED_MEDIA_TYPE,
                        -32000,
                        'Unsupported Media Type: Content-Type must be application/json'
                    );
                }

                // Parse body if not already parsed
                $rawMessage = $parsedBody;
                if ($rawMessage === null) {
                    $body = $request->getBody()->buffer();
                    $rawMessage = json_decode($body, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \RuntimeException("Invalid JSON: " . json_last_error_msg());
                    }
                }

                // Handle batch and single messages
                $messages = [];
                if (is_array($rawMessage) && isset($rawMessage[0])) {
                    // Batch request
                    foreach ($rawMessage as $msg) {
                        $messages[] = JSONRPCMessage::fromArray($msg);
                    }
                } else {
                    // Single request
                    $messages[] = JSONRPCMessage::fromArray($rawMessage);
                }

                // Check if this is an initialization request
                $isInitializationRequest = false;
                foreach ($messages as $message) {
                    if ($message instanceof JSONRPCRequest && $message->getMethod() === 'initialize') {
                        $isInitializationRequest = true;
                        break;
                    }
                }

                if ($isInitializationRequest) {
                    // Validate initialization request
                    if ($this->_initialized && $this->sessionId !== null) {
                        return $this->jsonErrorResponse(
                            HttpStatus::BAD_REQUEST,
                            -32600,
                            'Invalid Request: Server already initialized'
                        );
                    }

                    if (count($messages) > 1) {
                        return $this->jsonErrorResponse(
                            HttpStatus::BAD_REQUEST,
                            -32600,
                            'Invalid Request: Only one initialization request is allowed'
                        );
                    }

                    // Generate session ID if configured
                    if ($this->_options->sessionIdGenerator !== null) {
                        $this->sessionId = ($this->_options->sessionIdGenerator)();
                    }
                    $this->_initialized = true;

                    // Call session initialized handler if provided
                    if ($this->sessionId !== null && $this->_options->onsessioninitialized !== null) {
                        $handler = $this->_options->onsessioninitialized;
                        $result = $handler($this->sessionId);
                        if ($result instanceof Future) {
                            $result->await();
                        }
                    }
                } else {
                    // Validate session and protocol version for non-initialization requests
                    $sessionValidation = $this->validateSession($request);
                    if ($sessionValidation !== null) {
                        return $sessionValidation;
                    }

                    $protocolValidation = $this->validateProtocolVersion($request);
                    if ($protocolValidation !== null) {
                        return $protocolValidation;
                    }
                }

                // Check if it contains requests
                $hasRequests = false;
                foreach ($messages as $message) {
                    if ($message instanceof JSONRPCRequest) {
                        $hasRequests = true;
                        break;
                    }
                }

                // Prepare extra info for message handler
                $requestInfo = new RequestInfo(['headers' => $this->getHeadersArray($request)]);
                $extra = ['authInfo' => $authInfo, 'requestInfo' => $requestInfo];

                if (!$hasRequests) {
                    // Only notifications or responses, return 202 Accepted
                    foreach ($messages as $message) {
                        if ($this->onmessage !== null) {
                            ($this->onmessage)($message->jsonSerialize(), $extra);
                        }
                    }

                    return new Response(HttpStatus::ACCEPTED);
                } else {
                    // Has requests - need to send responses
                    $streamId = bin2hex(random_bytes(16)); // Generate unique stream ID

                    if (!$this->_options->enableJsonResponse) {
                        // SSE response
                        $headers = [
                            'Content-Type' => 'text/event-stream',
                            'Cache-Control' => 'no-cache',
                            'Connection' => 'keep-alive',
                        ];

                        if ($this->sessionId !== null) {
                            $headers['Mcp-Session-Id'] = $this->sessionId;
                        }

                        // Create streaming response
                        $stream = new \Amp\ByteStream\WritableIterableStream(8192);
                        $response = new Response(HttpStatus::OK, $headers, $stream->getIterator());

                        // Store the stream and mappings
                        $this->_streamMapping[$streamId] = $response;

                        foreach ($messages as $message) {
                            if ($message instanceof JSONRPCRequest) {
                                $this->_requestToStreamMapping[$message->getId()->jsonSerialize()] = $streamId;
                            }
                        }

                        // Clean up on stream close
                        $stream->onClose(function () use ($streamId) {
                            unset($this->_streamMapping[$streamId]);
                        });

                        // Process messages asynchronously
                        async(function () use ($messages, $extra) {
                            foreach ($messages as $message) {
                                if ($this->onmessage !== null) {
                                    ($this->onmessage)($message->jsonSerialize(), $extra);
                                }
                            }
                        });

                        return $response;
                    } else {
                        // Store request mappings for JSON response mode
                        foreach ($messages as $message) {
                            if ($message instanceof JSONRPCRequest) {
                                $this->_requestToStreamMapping[$message->getId()->jsonSerialize()] = $streamId;
                            }
                        }

                        // Process messages and collect responses
                        foreach ($messages as $message) {
                            if ($this->onmessage !== null) {
                                ($this->onmessage)($message->jsonSerialize(), $extra);
                            }
                        }

                        // Wait for all responses to be ready
                        $requestIds = [];
                        foreach ($messages as $message) {
                            if ($message instanceof JSONRPCRequest) {
                                $requestIds[] = $message->getId()->jsonSerialize();
                            }
                        }

                        // Poll for responses (with timeout)
                        $timeout = 30; // 30 seconds timeout
                        $start = time();

                        while (time() - $start < $timeout) {
                            $allReady = true;
                            foreach ($requestIds as $id) {
                                if (!isset($this->_requestResponseMap[$id])) {
                                    $allReady = false;
                                    break;
                                }
                            }

                            if ($allReady) {
                                break;
                            }

                            delay(0.1); // Small delay to avoid busy waiting
                        }

                        // Collect responses
                        $responses = [];
                        foreach ($requestIds as $id) {
                            if (isset($this->_requestResponseMap[$id])) {
                                $responses[] = $this->_requestResponseMap[$id];
                                unset($this->_requestResponseMap[$id]);
                                unset($this->_requestToStreamMapping[$id]);
                            }
                        }

                        // Return JSON response
                        $headers = ['Content-Type' => 'application/json'];
                        if ($this->sessionId !== null) {
                            $headers['Mcp-Session-Id'] = $this->sessionId;
                        }

                        $body = count($responses) === 1
                            ? json_encode($responses[0] instanceof \JsonSerializable ? $responses[0]->jsonSerialize() : (array)$responses[0])
                            : json_encode(array_map(fn($r) => $r instanceof \JsonSerializable ? $r->jsonSerialize() : (array)$r, $responses));

                        return new Response(HttpStatus::OK, $headers, $body);
                    }
                }
            } catch (\Throwable $error) {
                if ($this->onerror !== null) {
                    ($this->onerror)($error);
                }

                return $this->jsonErrorResponse(
                    HttpStatus::BAD_REQUEST,
                    -32700,
                    'Parse error',
                    (string)$error
                );
            }
        });
    }

    /**
     * Handle DELETE requests to terminate sessions
     */
    private function handleDeleteRequest(Request $request): Future
    {
        return async(function () use ($request) {
            $sessionValidation = $this->validateSession($request);
            if ($sessionValidation !== null) {
                return $sessionValidation;
            }

            $protocolValidation = $this->validateProtocolVersion($request);
            if ($protocolValidation !== null) {
                return $protocolValidation;
            }

            // Call session closed handler if provided
            if ($this->sessionId !== null && $this->_options->onsessionclosed !== null) {
                $handler = $this->_options->onsessionclosed;
                $result = $handler($this->sessionId);
                if ($result instanceof Future) {
                    $result->await();
                }
            }

            // Close the transport
            $this->close()->await();

            return new Response(HttpStatus::OK);
        });
    }

    /**
     * Validates session ID for non-initialization requests
     * 
     * @return Response|null Response if validation fails, null if valid
     */
    private function validateSession(Request $request): ?Response
    {
        // Skip validation in stateless mode
        if ($this->_options->sessionIdGenerator === null) {
            return null;
        }

        if (!$this->_initialized) {
            return $this->jsonErrorResponse(
                HttpStatus::BAD_REQUEST,
                -32000,
                'Bad Request: Server not initialized'
            );
        }

        $sessionId = $request->getHeader('Mcp-Session-Id');

        if ($sessionId === null) {
            return $this->jsonErrorResponse(
                HttpStatus::BAD_REQUEST,
                -32000,
                'Bad Request: Mcp-Session-Id header is required'
            );
        }

        if ($sessionId !== $this->sessionId) {
            return $this->jsonErrorResponse(
                HttpStatus::NOT_FOUND,
                -32001,
                'Session not found'
            );
        }

        return null;
    }

    /**
     * Validates protocol version header
     * 
     * @return Response|null Response if validation fails, null if valid
     */
    private function validateProtocolVersion(Request $request): ?Response
    {
        $protocolVersion = $request->getHeader('Mcp-Protocol-Version')
            ?? Protocol::DEFAULT_NEGOTIATED_PROTOCOL_VERSION;

        if (!in_array($protocolVersion, Protocol::SUPPORTED_PROTOCOL_VERSIONS, true)) {
            return $this->jsonErrorResponse(
                HttpStatus::BAD_REQUEST,
                -32000,
                'Bad Request: Unsupported protocol version (supported versions: ' .
                    implode(', ', Protocol::SUPPORTED_PROTOCOL_VERSIONS) . ')'
            );
        }

        return null;
    }

    /**
     * Replay events for resumability
     */
    private function replayEvents(string $lastEventId, Request $request): Future
    {
        return async(function () use ($lastEventId, $request) {
            if ($this->_options->eventStore === null) {
                return new Response(HttpStatus::NOT_IMPLEMENTED);
            }

            try {
                $headers = [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache, no-transform',
                    'Connection' => 'keep-alive',
                ];

                if ($this->sessionId !== null) {
                    $headers['Mcp-Session-Id'] = $this->sessionId;
                }

                // Create streaming response
                $stream = new \Amp\ByteStream\WritableIterableStream(8192);
                $response = new Response(HttpStatus::OK, $headers, $stream->getIterator());

                // Replay events
                $streamId = $this->_options->eventStore->replayEventsAfter(
                    $lastEventId,
                    function (string $eventId, JSONRPCMessage $message) use ($stream) {
                        return async(function () use ($stream, $eventId, $message) {
                            $data = "event: message\n";
                            $data .= "id: $eventId\n";
                            $data .= "data: " . json_encode($message instanceof \JsonSerializable ? $message->jsonSerialize() : (array)$message) . "\n\n";

                            $stream->write($data);
                        });
                    }
                )->await();

                $this->_streamMapping[$streamId] = $response;

                // Clean up on stream close
                $stream->onClose(function () use ($streamId) {
                    unset($this->_streamMapping[$streamId]);
                });

                return $response;
            } catch (\Throwable $error) {
                if ($this->onerror !== null) {
                    ($this->onerror)($error);
                }

                return new Response(HttpStatus::INTERNAL_SERVER_ERROR);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $message): Future
    {
        return async(function () use ($message) {
            $jsonrpcMessage = JSONRPCMessage::fromArray($message);
            $requestId = null;

            // Determine request ID
            if ($jsonrpcMessage instanceof JSONRPCResponse || $jsonrpcMessage instanceof JSONRPCError) {
                $requestId = $jsonrpcMessage->getId()->jsonSerialize();
            } elseif (isset($message['relatedRequestId'])) {
                $requestId = $message['relatedRequestId'];
            }

            // Send to standalone SSE stream if no request ID
            if ($requestId === null) {
                if ($jsonrpcMessage instanceof JSONRPCResponse || $jsonrpcMessage instanceof JSONRPCError) {
                    throw new \RuntimeException(
                        "Cannot send a response on a standalone SSE stream unless resuming a previous client request"
                    );
                }

                $response = $this->_streamMapping[self::STANDALONE_SSE_STREAM_ID] ?? null;
                if ($response === null) {
                    // No stream available, silently discard
                    return;
                }

                // Get the stream from the response
                $body = $response->getBody();
                if ($body instanceof \Amp\ByteStream\WritableIterableStream) {
                    // Generate event ID if event store is available
                    $eventId = null;
                    if ($this->_options->eventStore !== null) {
                        /** @var JSONRPCMessage $jsonrpcMessage */
                        $eventId = $this->_options->eventStore->storeEvent(
                            self::STANDALONE_SSE_STREAM_ID,
                            $jsonrpcMessage
                        )->await();
                    }

                    // Write SSE event
                    $data = "event: message\n";
                    if ($eventId !== null) {
                        $data .= "id: $eventId\n";
                    }
                    $data .= "data: " . json_encode($message) . "\n\n";

                    $body->write($data);
                }

                return;
            }

            // Get the stream ID for this request
            $streamId = $this->_requestToStreamMapping[$requestId] ?? null;
            if ($streamId === null) {
                throw new \RuntimeException("No connection established for request ID: $requestId");
            }

            // Handle SSE response mode
            if (!$this->_options->enableJsonResponse) {
                $response = $this->_streamMapping[$streamId] ?? null;
                if ($response !== null) {
                    $body = $response->getBody();
                    if ($body instanceof \Amp\ByteStream\WritableIterableStream) {
                        // Generate event ID if event store is available
                        $eventId = null;
                        if ($this->_options->eventStore !== null) {
                            /** @var JSONRPCMessage $jsonrpcMessage */
                            $eventId = $this->_options->eventStore->storeEvent(
                                $streamId,
                                $jsonrpcMessage
                            )->await();
                        }

                        // Write SSE event
                        $data = "event: message\n";
                        if ($eventId !== null) {
                            $data .= "id: $eventId\n";
                        }
                        $data .= "data: " . json_encode($message) . "\n\n";

                        $body->write($data);
                    }
                }
            }

            // Store response for JSON mode
            if ($jsonrpcMessage instanceof JSONRPCResponse || $jsonrpcMessage instanceof JSONRPCError) {
                $this->_requestResponseMap[$requestId] = $jsonrpcMessage;

                // In SSE mode, check if all responses are ready and close stream
                if (!$this->_options->enableJsonResponse) {
                    $relatedIds = [];
                    foreach ($this->_requestToStreamMapping as $id => $sid) {
                        if ($sid === $streamId) {
                            $relatedIds[] = $id;
                        }
                    }

                    $allReady = true;
                    foreach ($relatedIds as $id) {
                        if (!isset($this->_requestResponseMap[$id])) {
                            $allReady = false;
                            break;
                        }
                    }

                    if ($allReady) {
                        // Close the SSE stream
                        $response = $this->_streamMapping[$streamId] ?? null;
                        if ($response !== null) {
                            $body = $response->getBody();
                            if ($body instanceof \Amp\ByteStream\WritableIterableStream) {
                                $body->close();
                            }
                        }

                        // Clean up
                        foreach ($relatedIds as $id) {
                            unset($this->_requestResponseMap[$id]);
                            unset($this->_requestToStreamMapping[$id]);
                        }
                        unset($this->_streamMapping[$streamId]);
                    }
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function close(): Future
    {
        return async(function () {
            // Close all SSE connections
            foreach ($this->_streamMapping as $response) {
                $body = $response->getBody();
                if ($body instanceof \Amp\ByteStream\WritableIterableStream) {
                    $body->close();
                }
            }

            $this->_streamMapping = [];
            $this->_requestResponseMap = [];
            $this->_requestToStreamMapping = [];

            if ($this->onclose !== null) {
                ($this->onclose)();
            }
        });
    }

    /**
     * Create a JSON error response
     */
    private function jsonErrorResponse(int $status, int $code, string $message, $data = null): Response
    {
        $error = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message
            ],
            'id' => null
        ];

        if ($data !== null) {
            $error['error']['data'] = $data;
        }

        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($error)
        );
    }

    /**
     * Convert Amp Request headers to array format
     */
    private function getHeadersArray(Request $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
        }
        return $headers;
    }
}
