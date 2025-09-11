<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

use MCP\Shared\Transport;
use MCP\Types\JsonRpc\JSONRPCMessage;
use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\ByteStream\BufferedReader;
use Amp\DeferredCancellation;
use function Amp\async;

/**
 * Configuration options for SSE client transport
 * 
 * @deprecated Use StreamableHttpClientTransportOptions instead
 */
class SseClientTransportOptions
{
    /**
     * @param array<string, string>|null $headers Additional headers to send with requests
     * @param HttpClient|null $httpClient Custom HTTP client instance
     */
    public function __construct(
        public readonly ?array $headers = null,
        public readonly ?HttpClient $httpClient = null
    ) {}
}

/**
 * Client transport for SSE: this will connect to a server using HTTP GET
 * for Server-Sent Events and send messages via HTTP POST.
 * 
 * @deprecated Use StreamableHttpClientTransport instead. SSE transport is deprecated
 * in favor of the more feature-rich Streamable HTTP transport.
 */
class SseClientTransport implements Transport
{
    private string $_url;
    private SseClientTransportOptions $_options;
    private HttpClient $_httpClient;
    private ?string $_postEndpoint = null;
    private bool $_started = false;

    /** @var DeferredCancellation|null */
    private ?DeferredCancellation $_cancellation = null;

    /** @var callable(array): void|null */
    private $onmessage = null;

    /** @var callable(): void|null */
    private $onclose = null;

    /** @var callable(\Throwable): void|null */
    private $onerror = null;

    /**
     * @param string $url The SSE endpoint URL
     * @param SseClientTransportOptions|null $options Configuration options
     * @deprecated Use StreamableHttpClientTransport instead
     */
    public function __construct(string $url, ?SseClientTransportOptions $options = null)
    {
        $this->_url = $url;
        $this->_options = $options ?? new SseClientTransportOptions();
        $this->_httpClient = $this->_options->httpClient ?? HttpClientBuilder::buildDefault();

        trigger_error(
            'SseClientTransport is deprecated. Use StreamableHttpClientTransport instead.',
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
                    "SseClientTransport already started! If using Client class, " .
                        "note that connect() calls start() automatically."
                );
            }

            $this->_started = true;
            $this->_cancellation = new DeferredCancellation();

            // Connect to SSE endpoint
            $this->connectToSse();
        });
    }

    /**
     * Connect to the SSE endpoint
     */
    private function connectToSse(): void
    {
        async(function () {
            try {
                $request = new Request($this->_url, 'GET');

                // Add custom headers
                if ($this->_options->headers !== null) {
                    foreach ($this->_options->headers as $name => $value) {
                        $request->setHeader($name, $value);
                    }
                }

                $response = $this->_httpClient->request($request, $this->_cancellation?->getCancellation());

                if ($response->getStatus() !== 200) {
                    throw new \RuntimeException(
                        "Failed to connect to SSE endpoint: HTTP {$response->getStatus()}"
                    );
                }

                // Handle SSE stream
                $this->handleSseStream($response);
            } catch (\Throwable $e) {
                if ($this->onerror !== null) {
                    ($this->onerror)($e);
                }
                throw $e;
            }
        });
    }

    /**
     * Handle the SSE stream
     */
    private function handleSseStream(Response $response): void
    {
        async(function () use ($response) {
            try {
                $reader = new BufferedReader($response->getBody());
                $eventType = null;
                $eventData = '';

                while (($line = $reader->readLine($this->_cancellation?->getCancellation())) !== null) {
                    if (empty($line)) {
                        // Empty line indicates end of event
                        if ($eventType !== null && !empty($eventData)) {
                            $this->processEvent($eventType, rtrim($eventData, "\n"));
                            $eventType = null;
                            $eventData = '';
                        }
                        continue;
                    }

                    if (str_starts_with($line, 'event:')) {
                        $eventType = substr($line, 6);
                        if (str_starts_with($eventType, ' ')) {
                            $eventType = substr($eventType, 1);
                        }
                    } elseif (str_starts_with($line, 'data:')) {
                        $data = substr($line, 5);
                        if (str_starts_with($data, ' ')) {
                            $data = substr($data, 1);
                        }
                        $eventData .= $data . "\n";
                    }
                }
            } catch (\Amp\CancelledException $e) {
                // Stream was cancelled, expected during close
            } catch (\Throwable $e) {
                if ($this->onerror !== null) {
                    ($this->onerror)($e);
                }
            }
        });
    }

    /**
     * Process an SSE event
     */
    private function processEvent(string $eventType, string $data): void
    {
        try {
            if ($eventType === 'endpoint') {
                // Store the endpoint for posting messages
                $this->_postEndpoint = $data;
            } elseif ($eventType === 'message') {
                // Process JSON-RPC message
                $decoded = json_decode($data, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException("Invalid JSON in SSE event: " . json_last_error_msg());
                }

                $message = JSONRPCMessage::fromArray($decoded);

                if ($this->onmessage !== null) {
                    ($this->onmessage)($message->jsonSerialize());
                }
            }
        } catch (\Throwable $e) {
            if ($this->onerror !== null) {
                ($this->onerror)($e);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $message): Future
    {
        return async(function () use ($message) {
            if (!$this->_started || $this->_postEndpoint === null) {
                throw new \RuntimeException("Not connected or endpoint not received");
            }

            // Resolve the POST endpoint URL relative to the original URL
            $baseUrl = parse_url($this->_url);
            $postUrl = $this->_postEndpoint;

            // If endpoint is relative, resolve it
            if (!preg_match('/^https?:\/\//', $postUrl)) {
                $scheme = $baseUrl['scheme'] ?? 'http';
                $host = $baseUrl['host'] ?? 'localhost';
                $port = isset($baseUrl['port']) ? ':' . $baseUrl['port'] : '';

                if (str_starts_with($postUrl, '/')) {
                    // Absolute path
                    $postUrl = "$scheme://$host$port$postUrl";
                } else {
                    // Relative path
                    $basePath = dirname($baseUrl['path'] ?? '/');
                    if ($basePath === '/') {
                        $postUrl = "$scheme://$host$port/$postUrl";
                    } else {
                        $postUrl = "$scheme://$host$port$basePath/$postUrl";
                    }
                }
            }

            $request = new Request($postUrl, 'POST');

            // Add headers
            $request->setHeader('Content-Type', 'application/json');
            if ($this->_options->headers !== null) {
                foreach ($this->_options->headers as $name => $value) {
                    $request->setHeader($name, $value);
                }
            }

            // Set body
            $request->setBody(json_encode($message));

            try {
                $response = $this->_httpClient->request($request, $this->_cancellation?->getCancellation());

                if ($response->getStatus() !== 202) {
                    $body = $response->getBody()->buffer();
                    throw new \RuntimeException(
                        "Failed to send message: HTTP {$response->getStatus()} - $body"
                    );
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

            if ($this->onclose !== null) {
                ($this->onclose)();
            }
        });
    }
}
