<?php

declare(strict_types=1);

namespace MCP\Server\Transport;

use function Amp\async;

use Amp\DeferredCancellation;

use function Amp\delay;

use Amp\Future;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket;
use Amp\Socket\InternetAddress;
use Amp\Websocket\Server\Websocket;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\WebsocketClient;
use Amp\Websocket\WebsocketMessage;
use MCP\Shared\ReadBuffer;
use MCP\Shared\Transport;
use MCP\Types\ErrorCode;
use MCP\Types\McpError;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * WebSocket server transport for MCP communication.
 *
 * This transport implements the MCP transport interface over WebSocket connections,
 * supporting multiple concurrent clients, automatic connection management, and
 * proper JSON-RPC message handling.
 */
class WebSocketServerTransport implements Transport
{
    private bool $started = false;

    private ?HttpServer $server = null;

    private ?DeferredCancellation $cancellation = null;

    /** @var array<string, WebsocketClient> */
    private array $connections = [];

    /** @var callable(array): void|null */
    private $messageHandler = null;

    /** @var callable(): void|null */
    private $closeHandler = null;

    /** @var callable(\Throwable): void|null */
    private $errorHandler = null;

    public function __construct(
        private readonly WebSocketServerTransportOptions $options = new WebSocketServerTransportOptions(),
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function start(): Future
    {
        return async(function () {
            if ($this->started) {
                throw new \RuntimeException(
                    'WebSocketServerTransport already started! If using Server class, ' .
                        'note that connect() calls start() automatically.'
                );
            }

            $this->started = true;
            $this->cancellation = new DeferredCancellation();

            try {
                $this->logger->info("Starting WebSocket server on {$this->options->getAddress()}");

                // Create socket server
                $socket = $this->createSocket();

                // Create WebSocket gateway with MCP message handling
                $gateway = $this->createWebSocketGateway();

                // Create HTTP server with WebSocket upgrade support
                $this->server = SocketHttpServer::createForDirectAccess($this->logger);
                $this->server->expose($socket);

                // Start the server with WebSocket support
                $this->server->start(new Websocket($gateway), []);

                $this->logger->info("WebSocket server started successfully on {$this->options->getAddress()}");

                // Start heartbeat if enabled
                if ($this->options->enablePing) {
                    $this->startHeartbeat();
                }
            } catch (\Throwable $error) {
                $this->started = false;
                $this->logger->error("Failed to start WebSocket server: {$error->getMessage()}");

                if ($this->errorHandler) {
                    ($this->errorHandler)($error);
                }

                throw new McpError(
                    ErrorCode::InternalError,
                    "Failed to start WebSocket server: {$error->getMessage()}",
                    $error
                );
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $message): Future
    {
        return async(function () use ($message) {
            if (!$this->started) {
                throw new McpError(ErrorCode::InternalError, 'WebSocket server not started');
            }

            if (empty($this->connections)) {
                $this->logger->debug('No WebSocket connections available to send message');

                return;
            }

            try {
                $json = ReadBuffer::serializeMessage($message);

                // Send to all connected clients
                $futures = [];
                foreach ($this->connections as $connectionId => $client) {
                    $futures[] = async(function () use ($client, $json, $connectionId) {
                        try {
                            $client->sendText($json);
                            $this->logger->debug("Sent message to WebSocket connection {$connectionId}");
                        } catch (\Throwable $error) {
                            $this->logger->warning("Failed to send message to connection {$connectionId}: {$error->getMessage()}");
                            $this->removeConnection($connectionId);

                            if ($this->errorHandler) {
                                ($this->errorHandler)($error);
                            }
                        }
                    });
                }

                // Wait for all sends to complete
                Future\awaitAll($futures);
            } catch (\Throwable $error) {
                $this->logger->error("Failed to send WebSocket message: {$error->getMessage()}");

                if ($this->errorHandler) {
                    ($this->errorHandler)($error);
                }

                throw new McpError(
                    ErrorCode::InternalError,
                    "Failed to send WebSocket message: {$error->getMessage()}",
                    $error
                );
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function close(): Future
    {
        return async(function () {
            if (!$this->started) {
                return;
            }

            $this->logger->info('Closing WebSocket server');

            // Cancel any ongoing operations
            if ($this->cancellation) {
                $this->cancellation->cancel();
            }

            // Close all connections
            foreach ($this->connections as $connectionId => $client) {
                try {
                    $client->close();
                } catch (\Throwable $error) {
                    $this->logger->warning("Error closing connection {$connectionId}: {$error->getMessage()}");
                }
            }
            $this->connections = [];

            // Stop the server
            if ($this->server) {
                try {
                    $this->server->stop();
                } catch (\Throwable $error) {
                    $this->logger->warning("Error stopping HTTP server: {$error->getMessage()}");
                }
                $this->server = null;
            }

            $this->started = false;

            // Notify close handler
            if ($this->closeHandler) {
                ($this->closeHandler)();
            }

            $this->logger->info('WebSocket server closed');
        });
    }

    /**
     * {@inheritDoc}
     */
    public function setMessageHandler(callable $handler): void
    {
        $this->messageHandler = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function setCloseHandler(callable $handler): void
    {
        $this->closeHandler = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function setErrorHandler(callable $handler): void
    {
        $this->errorHandler = $handler;
    }

    /**
     * Get the number of active connections.
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Get server status information.
     */
    public function getStatus(): array
    {
        return [
            'started' => $this->started,
            'address' => $this->options->getAddress(),
            'connections' => count($this->connections),
            'maxConnections' => $this->options->maxConnections,
        ];
    }

    /**
     * Create the socket server based on configuration.
     */
    private function createSocket(): Socket\ServerSocket
    {
        $address = new InternetAddress($this->options->host, $this->options->port);

        if ($this->options->enableTls) {
            // TLS configuration would go here
            // For now, we'll use a simple socket
            throw new \RuntimeException('TLS support not yet implemented for WebSocket server');
        }

        return Socket\listen($address);
    }

    /**
     * Create the WebSocket gateway with MCP message handling.
     */
    private function createWebSocketGateway(): WebsocketGateway
    {
        return new WebsocketClientGateway(new class ($this) implements WebsocketClientHandler {
            public function __construct(
                private readonly WebSocketServerTransport $transport
            ) {
            }

            public function handleClient(WebsocketClient $client, Request $request, Response $response): void
            {
                // Validate request headers for security
                $validationError = $this->validateRequest($request);
                if ($validationError) {
                    $this->transport->logger->warning("Connection rejected: {$validationError}");
                    $client->close();

                    return;
                }

                $connectionId = $this->generateConnectionId($client);

                // Check connection limit
                if (count($this->transport->connections) >= $this->transport->options->maxConnections) {
                    $this->transport->logger->warning('Connection limit reached, rejecting new connection');
                    $client->close();

                    return;
                }

                $this->transport->addConnection($connectionId, $client);
                $this->transport->logger->info("WebSocket connection established: {$connectionId}");

                // Handle incoming messages
                async(function () use ($client, $connectionId) {
                    try {
                        while ($message = $client->receive()) {
                            $this->handleMessage($connectionId, $message);
                        }
                    } catch (\Throwable $error) {
                        $this->transport->logger->warning("Connection {$connectionId} error: {$error->getMessage()}");
                        if ($this->transport->errorHandler) {
                            ($this->transport->errorHandler)($error);
                        }
                    } finally {
                        $this->transport->removeConnection($connectionId);
                    }
                });
            }

            private function validateRequest(Request $request): ?string
            {
                // Validate origin if specified
                if ($this->transport->options->allowedOrigins !== null) {
                    $origin = $request->getHeader('Origin');
                    if ($origin && !in_array($origin, $this->transport->options->allowedOrigins, true)) {
                        return "Origin not allowed: {$origin}";
                    }
                }

                // Validate host if specified
                if ($this->transport->options->allowedHosts !== null) {
                    $host = $request->getHeader('Host');
                    if ($host && !in_array($host, $this->transport->options->allowedHosts, true)) {
                        return "Host not allowed: {$host}";
                    }
                }

                // DNS rebinding protection
                if ($this->transport->options->enableDnsRebindingProtection) {
                    $host = $request->getHeader('Host');
                    if ($host && !$this->isValidHost($host)) {
                        return "Invalid host for DNS rebinding protection: {$host}";
                    }
                }

                return null;
            }

            private function isValidHost(string $host): bool
            {
                // Basic DNS rebinding protection - allow localhost and configured host
                $allowedHosts = [
                    'localhost',
                    '127.0.0.1',
                    '[::1]',
                    $this->transport->options->host,
                    $this->transport->options->host . ':' . $this->transport->options->port,
                ];

                return in_array($host, $allowedHosts, true);
            }

            private function generateConnectionId(WebsocketClient $client): string
            {
                return uniqid('ws_', true);
            }

            private function handleMessage(string $connectionId, WebsocketMessage $message): void
            {
                try {
                    $payload = $message->buffer();
                    $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                    if ($this->transport->messageHandler) {
                        ($this->transport->messageHandler)($data);
                    }

                    $this->transport->logger->debug("Received message from connection {$connectionId}");
                } catch (\JsonException $error) {
                    $this->transport->logger->error("Invalid JSON received from connection {$connectionId}: {$error->getMessage()}");

                    if ($this->transport->errorHandler) {
                        ($this->transport->errorHandler)(new McpError(
                            ErrorCode::ParseError,
                            "Invalid JSON in WebSocket message: {$error->getMessage()}",
                            $error
                        ));
                    }
                } catch (\Throwable $error) {
                    $this->transport->logger->error("Error handling message from connection {$connectionId}: {$error->getMessage()}");

                    if ($this->transport->errorHandler) {
                        ($this->transport->errorHandler)($error);
                    }
                }
            }
        });
    }

    /**
     * Add a new WebSocket connection.
     */
    private function addConnection(string $connectionId, WebsocketClient $client): void
    {
        $this->connections[$connectionId] = $client;
        $this->logger->debug("Added WebSocket connection {$connectionId} (total: " . count($this->connections) . ')');
    }

    /**
     * Remove a WebSocket connection.
     */
    private function removeConnection(string $connectionId): void
    {
        if (isset($this->connections[$connectionId])) {
            unset($this->connections[$connectionId]);
            $this->logger->debug("Removed WebSocket connection {$connectionId} (total: " . count($this->connections) . ')');
        }
    }

    /**
     * Start heartbeat ping/pong mechanism.
     */
    private function startHeartbeat(): void
    {
        if (!$this->options->enablePing) {
            return;
        }

        async(function () {
            while ($this->started && !$this->cancellation?->getCancellation()->isRequested()) {
                try {
                    delay($this->options->heartbeatInterval);

                    if (!$this->started) {
                        break;
                    }

                    // Send ping to all connections
                    foreach ($this->connections as $connectionId => $client) {
                        try {
                            $client->ping();
                            $this->logger->debug("Sent ping to connection {$connectionId}");
                        } catch (\Throwable $error) {
                            $this->logger->warning("Failed to ping connection {$connectionId}: {$error->getMessage()}");
                            $this->removeConnection($connectionId);
                        }
                    }
                } catch (\Throwable $error) {
                    $this->logger->error("Heartbeat error: {$error->getMessage()}");
                    if ($this->errorHandler) {
                        ($this->errorHandler)($error);
                    }
                }
            }
        });
    }
}
