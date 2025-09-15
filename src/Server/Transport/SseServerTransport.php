<?php

declare(strict_types=1);

namespace MCP\Server\Transport;

use function Amp\async;

use Amp\ByteStream\WritableIterableStream;
use Amp\Future;
use Amp\Http\HttpStatus;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use MCP\Shared\Transport;

use MCP\Types\JsonRpc\JSONRPCMessage;

/**
 * Configuration options for SSEServerTransport.
 *
 * @deprecated Use StreamableHttpServerTransport instead
 *
 * @internal This class will be removed in a future version
 */
class SseServerTransportOptions
{
    /**
     * @param array<string>|null $allowedHosts List of allowed host headers
     * @param array<string>|null $allowedOrigins List of allowed origin headers
     * @param bool $enableDnsRebindingProtection Enable DNS rebinding protection
     */
    public function __construct(
        public readonly ?array $allowedHosts = null,
        public readonly ?array $allowedOrigins = null,
        public readonly bool $enableDnsRebindingProtection = false
    ) {
    }
}

/**
 * Server transport for SSE: this will send messages over an SSE connection
 * and receive messages from HTTP POST requests.
 *
 * @deprecated Use StreamableHttpServerTransport instead. SSE transport is deprecated
 * in favor of the more feature-rich Streamable HTTP transport.
 */
class SseServerTransport implements Transport
{
    private string $_endpoint;

    /** @SuppressWarnings(PHPMD.DeprecatedClass) */
    private SseServerTransportOptions $_options;

    private ?string $_sessionId = null;

    private ?WritableIterableStream $_sseStream = null;

    private bool $_started = false;

    /** @var callable(array, array|null): void|null */
    private $onmessage = null;

    /** @var callable(): void|null */
    private $onclose = null;

    /** @var callable(\Throwable): void|null */
    private $onerror = null;

    /**
     * Creates a new SSE server transport.
     *
     * @param string $endpoint The endpoint URL where clients should POST messages
     * @param SseServerTransportOptions|null $options Configuration options
     *
     * @deprecated Use StreamableHttpServerTransport instead
     */
    public function __construct(
        string $endpoint,
        ?SseServerTransportOptions $options = null
    ) {
        $this->_endpoint = $endpoint;
        $this->_options = $options ?? new SseServerTransportOptions();
        $this->_sessionId = bin2hex(random_bytes(16));

        trigger_error(
            'SseServerTransport is deprecated. Use StreamableHttpServerTransport instead.',
            E_USER_DEPRECATED
        );
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
                    'SSEServerTransport already started! If using Server class, ' .
                        'note that connect() calls start() automatically.'
                );
            }

            $this->_started = true;
        });
    }

    /**
     * Handle the initial SSE connection request.
     *
     * @param Request $request The HTTP request
     *
     * @return Response The SSE response
     */
    public function handleSseRequest(Request $request): Response
    {
        if ($this->_sseStream !== null) {
            throw new \RuntimeException('SSE stream already established');
        }

        // Validate request headers
        $validationError = $this->validateRequestHeaders($request);
        if ($validationError !== null) {
            return new Response(
                HttpStatus::FORBIDDEN,
                ['Content-Type' => 'text/plain'],
                $validationError
            );
        }

        // Create SSE response
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
        ];

        $this->_sseStream = new WritableIterableStream(8192);

        // Send endpoint event with session ID
        $endpointUrl = $this->_endpoint;
        if (str_contains($endpointUrl, '?')) {
            $endpointUrl .= '&sessionId=' . $this->_sessionId;
        } else {
            $endpointUrl .= '?sessionId=' . $this->_sessionId;
        }

        $this->_sseStream->write("event: endpoint\ndata: $endpointUrl\n\n");

        // Set up close handler
        $this->_sseStream->onClose(function () {
            $this->_sseStream = null;
            if ($this->onclose !== null) {
                ($this->onclose)();
            }
        });

        return new Response(HttpStatus::OK, $headers, $this->_sseStream->getIterator());
    }

    /**
     * Handle incoming POST messages.
     *
     * @param Request $request The HTTP request
     * @param mixed $parsedBody Pre-parsed body (optional)
     * @param array|null $authInfo Authentication information
     *
     * @return Future<Response>
     */
    public function handlePostMessage(
        Request $request,
        $parsedBody = null,
        ?array $authInfo = null
    ): Future {
        return async(function () use ($request, $parsedBody, $authInfo) {
            if ($this->_sseStream === null) {
                return new Response(
                    HttpStatus::INTERNAL_SERVER_ERROR,
                    ['Content-Type' => 'text/plain'],
                    'SSE connection not established'
                );
            }

            // Validate request headers
            $validationError = $this->validateRequestHeaders($request);
            if ($validationError !== null) {
                if ($this->onerror !== null) {
                    ($this->onerror)(new \Error($validationError));
                }

                return new Response(
                    HttpStatus::FORBIDDEN,
                    ['Content-Type' => 'text/plain'],
                    $validationError
                );
            }

            // Check content type
            $contentType = $request->getHeader('Content-Type') ?? '';
            if (!str_contains($contentType, 'application/json')) {
                return new Response(
                    HttpStatus::BAD_REQUEST,
                    ['Content-Type' => 'text/plain'],
                    "Unsupported content-type: $contentType"
                );
            }

            try {
                // Parse body if not already parsed
                $body = $parsedBody;
                if ($body === null) {
                    $bodyContent = $request->getBody()->buffer();
                    $body = json_decode($bodyContent, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
                    }
                }

                // Prepare extra info
                $requestInfo = ['headers' => $this->getHeadersArray($request)];
                $extra = ['authInfo' => $authInfo, 'requestInfo' => $requestInfo];

                // Handle the message
                $this->handleMessage($body, $extra);

                return new Response(
                    HttpStatus::ACCEPTED,
                    ['Content-Type' => 'text/plain'],
                    'Accepted'
                );
            } catch (\Throwable $error) {
                if ($this->onerror !== null) {
                    ($this->onerror)($error);
                }

                return new Response(
                    HttpStatus::BAD_REQUEST,
                    ['Content-Type' => 'text/plain'],
                    'Invalid message: ' . $error->getMessage()
                );
            }
        });
    }

    /**
     * Handle a client message.
     *
     * @param mixed $message The message data
     * @param array|null $extra Extra information
     */
    private function handleMessage($message, ?array $extra = null): void
    {
        try {
            $parsedMessage = JSONRPCMessage::fromArray($message);

            if ($this->onmessage !== null) {
                ($this->onmessage)($parsedMessage->jsonSerialize(), $extra);
            }
        } catch (\Throwable $error) {
            if ($this->onerror !== null) {
                ($this->onerror)($error);
            }

            throw $error;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $message): Future
    {
        return async(function () use ($message) {
            if ($this->_sseStream === null) {
                throw new \RuntimeException('Not connected');
            }

            $data = "event: message\n";
            $data .= 'data: ' . json_encode($message) . "\n\n";

            $this->_sseStream->write($data);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function close(): Future
    {
        return async(function () {
            $this->_sseStream?->close();
            $this->_sseStream = null;

            if ($this->onclose !== null) {
                ($this->onclose)();
            }
        });
    }

    /**
     * Get the session ID for this transport.
     */
    public function getSessionId(): string
    {
        return $this->_sessionId;
    }

    /**
     * Validate request headers for DNS rebinding protection.
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
     * Convert Amp Request headers to array format.
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
