<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

use MCP\Shared\Transport;
use MCP\Types\JsonRpc\JSONRPCMessage;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCResponse;
use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\Protocol;
use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\ByteStream\BufferedReader;
use Amp\DeferredCancellation;
use MCP\Client\Transport\StreamableHttpReconnectionOptions;
use MCP\Client\Transport\StreamableHttpClientTransportOptions;
use MCP\Client\Transport\StreamableHttpError;

use function Amp\async;
use function Amp\delay;

/**
 * Client transport for Streamable HTTP: this implements the MCP Streamable HTTP transport specification.
 * It will connect to a server using HTTP POST for sending messages and HTTP GET with Server-Sent Events
 * for receiving messages.
 */
class StreamableHttpClientTransport implements Transport
{
    private string $_url;
    private StreamableHttpClientTransportOptions $_options;
    private HttpClient $_httpClient;
    private ?string $_sessionId;
    private ?string $_protocolVersion = null;
    private bool $_started = false;

    /** @var DeferredCancellation|null */
    private ?DeferredCancellation $_cancellation = null;

    /** @var callable(array): void|null */
    private $onmessage = null;

    /** @var callable(): void|null */
    private $onclose = null;

    /** @var callable(\Throwable): void|null */
    private $onerror = null;

    /** @var string|null Last event ID for resumability */
    private ?string $_lastEventId = null;

    /** @var int Current reconnection attempt */
    private int $_reconnectAttempt = 0;

    public function __construct(string $url, ?StreamableHttpClientTransportOptions $options = null)
    {
        $this->_url = $url;
        $this->_options = $options ?? new StreamableHttpClientTransportOptions();
        $this->_httpClient = $this->_options->httpClient ?? HttpClientBuilder::buildDefault();
        $this->_sessionId = $this->_options->sessionId;
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
                throw new \RuntimeException(
                    "StreamableHttpClientTransport already started! If using Client class, " .
                        "note that connect() calls start() automatically."
                );
            }

            $this->_started = true;
            $this->_cancellation = new DeferredCancellation();

            // Try to start SSE stream (optional - server may not support it)
            try {
                $this->startSseStream();
            } catch (StreamableHttpError $e) {
                // 405 Method Not Allowed is expected if server doesn't support GET SSE
                if ($e->getCode() !== 405) {
                    throw $e;
                }
            }
        });
    }

    /**
     * Start or reconnect the SSE stream
     */
    private function startSseStream(?string $resumptionToken = null): void
    {
        async(function () use ($resumptionToken) {
            try {
                $request = new Request($this->_url, 'GET');

                // Set headers
                $this->applyCommonHeaders($request);
                $request->setHeader('Accept', 'text/event-stream');

                // Set Last-Event-ID for resumability
                if ($resumptionToken !== null) {
                    $request->setHeader('Last-Event-ID', $resumptionToken);
                }

                $response = $this->_httpClient->request($request, $this->_cancellation?->getCancellation());

                if ($response->getStatus() === 405) {
                    // Server doesn't support SSE on GET - this is OK
                    return;
                }

                if ($response->getStatus() !== 200) {
                    throw new StreamableHttpError(
                        $response->getStatus(),
                        "Failed to open SSE stream: " . $response->getReason()
                    );
                }

                // Handle SSE stream
                $this->handleSseStream($response);
            } catch (\Throwable $e) {
                if ($this->onerror !== null) {
                    ($this->onerror)($e);
                }

                // Attempt reconnection if not cancelled
                if ($this->_cancellation !== null && !$this->_cancellation->isCancelled()) {
                    $this->scheduleReconnection();
                }
            }
        });
    }

    /**
     * Handle Server-Sent Events stream
     */
    private function handleSseStream(Response $response): void
    {
        async(function () use ($response) {
            try {
                $reader = new BufferedReader($response->getBody());
                $eventData = '';
                $eventId = null;

                while (($line = $reader->readUntil("\n", $this->_cancellation?->getCancellation())) !== null) {
                    $line = rtrim($line, "\r\n");
                    // SSE format: field:value
                    if (empty($line)) {
                        // Empty line indicates end of event
                        if (!empty($eventData)) {
                            $this->processEvent($eventData, $eventId);
                            $eventData = '';
                            $eventId = null;
                        }
                        continue;
                    }

                    if (str_starts_with($line, 'data:')) {
                        $data = substr($line, 5);
                        if (str_starts_with($data, ' ')) {
                            $data = substr($data, 1);
                        }
                        $eventData .= $data . "\n";
                    } elseif (str_starts_with($line, 'id:')) {
                        $id = substr($line, 3);
                        if (str_starts_with($id, ' ')) {
                            $id = substr($id, 1);
                        }
                        $eventId = $id;
                        $this->_lastEventId = $id;
                    } elseif (str_starts_with($line, 'event:')) {
                        // We only handle 'message' events (default)
                        $eventType = substr($line, 6);
                        if (str_starts_with($eventType, ' ')) {
                            $eventType = substr($eventType, 1);
                        }
                        // Skip non-message events
                        if ($eventType !== 'message') {
                            $eventData = '';
                            $eventId = null;
                        }
                    }
                }
            } catch (\Amp\CancelledException $e) {
                // Stream was cancelled, expected during close
            } catch (\Throwable $e) {
                if ($this->onerror !== null) {
                    ($this->onerror)(new \Error("SSE stream disconnected: $e"));
                }

                // Attempt reconnection if not cancelled
                if ($this->_cancellation !== null && !$this->_cancellation->isCancelled()) {
                    $this->scheduleReconnection();
                }
            }
        });
    }

    /**
     * Process a complete SSE event
     */
    private function processEvent(string $data, ?string $eventId): void
    {
        try {
            $data = rtrim($data, "\n");
            $decoded = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON in SSE event: " . json_last_error_msg());
            }

            $message = JSONRPCMessage::fromArray($decoded);

            if ($this->onmessage !== null) {
                ($this->onmessage)($message->jsonSerialize());
            }
        } catch (\Throwable $e) {
            if ($this->onerror !== null) {
                ($this->onerror)($e);
            }
        }
    }

    /**
     * Schedule reconnection with exponential backoff
     */
    private function scheduleReconnection(): void
    {
        $options = $this->_options->reconnectionOptions ?? new StreamableHttpReconnectionOptions();

        if ($options->maxRetries > 0 && $this->_reconnectAttempt >= $options->maxRetries) {
            if ($this->onerror !== null) {
                ($this->onerror)(new \Error("Maximum reconnection attempts ({$options->maxRetries}) exceeded."));
            }
            return;
        }

        $delay = $this->getNextReconnectionDelay();

        async(function () use ($delay) {
            delay($delay / 1000); // Convert ms to seconds

            if ($this->_cancellation !== null && !$this->_cancellation->isCancelled()) {
                $this->_reconnectAttempt++;
                $this->startSseStream($this->_lastEventId);
            }
        });
    }

    /**
     * Calculate next reconnection delay with exponential backoff
     */
    private function getNextReconnectionDelay(): int
    {
        $options = $this->_options->reconnectionOptions ?? new StreamableHttpReconnectionOptions();

        $delay = $options->initialReconnectionDelay *
            pow($options->reconnectionDelayGrowFactor, $this->_reconnectAttempt);

        return (int) min($delay, $options->maxReconnectionDelay);
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $message): Future
    {
        return async(function () use ($message) {
            if (!$this->_started) {
                throw new \RuntimeException("Transport not started");
            }

            $request = new Request($this->_url, 'POST');

            // Set headers
            $this->applyCommonHeaders($request);
            $request->setHeader('Content-Type', 'application/json');
            $request->setHeader('Accept', 'application/json, text/event-stream');

            // Set body
            $request->setBody(json_encode($message));

            try {
                $response = $this->_httpClient->request($request, $this->_cancellation?->getCancellation());

                // Handle session ID from response
                $sessionId = $response->getHeader('Mcp-Session-Id');
                if ($sessionId !== null) {
                    $this->_sessionId = $sessionId;
                }

                if ($response->getStatus() === 401) {
                    throw new StreamableHttpError(401, "Unauthorized");
                }

                if ($response->getStatus() === 202) {
                    // Accepted - check if this was initialization
                    $jsonrpcMessage = JSONRPCMessage::fromArray($message);
                    if (
                        $jsonrpcMessage instanceof JSONRPCNotification &&
                        $jsonrpcMessage->getMethod() === 'initialized'
                    ) {
                        // Start SSE stream after initialization
                        $this->startSseStream();
                    }
                    return;
                }

                if (!in_array($response->getStatus(), [200, 202], true)) {
                    $body = $response->getBody()->buffer();
                    throw new StreamableHttpError(
                        $response->getStatus(),
                        "Error POSTing to endpoint (HTTP {$response->getStatus()}): $body"
                    );
                }

                // Check if we have requests in the message
                $messages = is_array($message) && isset($message[0]) ? $message : [$message];
                $hasRequests = false;

                foreach ($messages as $msg) {
                    if (isset($msg['method']) && isset($msg['id'])) {
                        $hasRequests = true;
                        break;
                    }
                }

                if ($hasRequests) {
                    $contentType = $response->getHeader('Content-Type') ?? '';

                    if (str_contains($contentType, 'text/event-stream')) {
                        // Handle SSE response
                        $this->handleSseStream($response);
                    } elseif (str_contains($contentType, 'application/json')) {
                        // Handle JSON response
                        $body = $response->getBody()->buffer();
                        $data = json_decode($body, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new \RuntimeException("Invalid JSON response: " . json_last_error_msg());
                        }

                        $responseMessages = is_array($data) && isset($data[0]) ? $data : [$data];

                        foreach ($responseMessages as $msg) {
                            $responseMessage = JSONRPCMessage::fromArray($msg);
                            if ($this->onmessage !== null) {
                                ($this->onmessage)($responseMessage->jsonSerialize());
                            }
                        }
                    } else {
                        throw new StreamableHttpError(-1, "Unexpected content type: $contentType");
                    }
                }
            } catch (\Throwable $e) {
                if ($this->onerror !== null) {
                    ($this->onerror)($e);
                }
                throw $e;
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function close(): Future
    {
        return async(function () {
            // Cancel any active operations
            $this->_cancellation?->cancel();

            // Optionally terminate session if we have one
            if ($this->_sessionId !== null) {
                try {
                    $this->terminateSession()->await();
                } catch (\Throwable $e) {
                    // Ignore termination errors
                }
            }

            if ($this->onclose !== null) {
                ($this->onclose)();
            }
        });
    }

    /**
     * Terminate the current session by sending a DELETE request
     */
    public function terminateSession(): Future
    {
        return async(function () {
            if ($this->_sessionId === null) {
                return; // No session to terminate
            }

            $request = new Request($this->_url, 'DELETE');
            $this->applyCommonHeaders($request);

            try {
                $response = $this->_httpClient->request($request);

                // 405 is valid - server doesn't support session termination
                if (!in_array($response->getStatus(), [200, 405], true)) {
                    throw new StreamableHttpError(
                        $response->getStatus(),
                        "Failed to terminate session: " . $response->getReason()
                    );
                }

                $this->_sessionId = null;
            } catch (\Throwable $e) {
                if ($this->onerror !== null) {
                    ($this->onerror)($e);
                }
                throw $e;
            }
        });
    }

    /**
     * Apply common headers to a request
     */
    private function applyCommonHeaders(Request $request): void
    {
        // Add custom headers
        if ($this->_options->headers !== null) {
            foreach ($this->_options->headers as $name => $value) {
                $request->setHeader($name, $value);
            }
        }

        // Add session ID if available
        if ($this->_sessionId !== null) {
            $request->setHeader('Mcp-Session-Id', $this->_sessionId);
        }

        // Add protocol version if set
        if ($this->_protocolVersion !== null) {
            $request->setHeader('Mcp-Protocol-Version', $this->_protocolVersion);
        }
    }

    /**
     * Get the current session ID
     */
    public function getSessionId(): ?string
    {
        return $this->_sessionId;
    }

    /**
     * Set the protocol version to use
     */
    public function setProtocolVersion(string $version): void
    {
        $this->_protocolVersion = $version;
    }

    /**
     * Get the protocol version
     */
    public function getProtocolVersion(): ?string
    {
        return $this->_protocolVersion;
    }
}
