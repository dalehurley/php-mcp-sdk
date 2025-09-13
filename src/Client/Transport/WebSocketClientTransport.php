<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\TimeoutCancellation;
use Evenement\EventEmitter;
use MCP\Shared\Transport;
use MCP\Types\ErrorCode;
use MCP\Types\McpError;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Amp\async;
use function Amp\delay;

/**
 * WebSocket client transport with automatic reconnection and heartbeat support.
 * 
 * This transport implements the MCP transport interface over WebSocket connections,
 * providing features like automatic reconnection, heartbeat/ping-pong, and proper
 * connection lifecycle management.
 */
class WebSocketClientTransport extends EventEmitter implements Transport
{
    private ?object $connection = null;
    private bool $isConnected = false;
    private bool $isConnecting = false;
    private bool $shouldReconnect = true;
    private int $reconnectAttempts = 0;
    /** @var Future<mixed>|null */
    private ?Future $heartbeatTask = null;
    /** @var Future<mixed>|null */
    private ?Future $reconnectTask = null;
    private ?DeferredCancellation $cancellation = null;
    /** @var callable|null */
    private $messageHandler = null;
    /** @var callable|null */
    private $closeHandler = null;
    /** @var callable|null */
    private $errorHandler = null;

    public function __construct(
        private readonly string $url,
        private readonly WebSocketOptions $options = new WebSocketOptions(),
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        // EventEmitter doesn't have a constructor, so we don't call parent::__construct()
    }

    public function start(): Future
    {
        return async(function () {
            if ($this->isConnected || $this->isConnecting) {
                return;
            }

            $this->shouldReconnect = true;
            $this->reconnectAttempts = 0;
            $this->cancellation = new DeferredCancellation();

            return $this->connect()->await();
        });
    }

    public function close(): Future
    {
        return async(function () {
            $this->shouldReconnect = false;

            // Cancel any ongoing operations
            $this->cancellation?->cancel();

            // Stop heartbeat
            $this->stopHeartbeat();

            // Close connection
            if ($this->connection && $this->isConnected) {
                try {
                    // Close the WebSocket connection
                    if (method_exists($this->connection, 'close')) {
                        $this->connection->close();
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning('Error closing WebSocket connection: ' . $e->getMessage());
                }
            }

            $this->isConnected = false;
            $this->isConnecting = false;
            $this->connection = null;

            $this->emit('close');
        });
    }

    public function send(array $message): Future
    {
        return async(function () use ($message) {
            if (!$this->isConnected || !$this->connection) {
                throw new McpError(
                    ErrorCode::InternalError,
                    'WebSocket connection is not established'
                );
            }

            $json = json_encode($message);
            if ($json === false) {
                throw new McpError(
                    ErrorCode::ParseError,
                    'Failed to encode message as JSON'
                );
            }

            try {
                // Send message through WebSocket
                if (method_exists($this->connection, 'send')) {
                    $this->connection->send($json);
                } else {
                    throw new McpError(
                        ErrorCode::InternalError,
                        'WebSocket connection does not support sending'
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send WebSocket message: ' . $e->getMessage());
                throw new McpError(
                    ErrorCode::InternalError,
                    'Failed to send WebSocket message: ' . $e->getMessage(),
                    $e
                );
            }
        });
    }

    public function setMessageHandler(callable $handler): void
    {
        $this->messageHandler = $handler;
    }

    public function setCloseHandler(callable $handler): void
    {
        $this->closeHandler = $handler;
    }

    public function setErrorHandler(callable $handler): void
    {
        $this->errorHandler = $handler;
    }

    /**
     * Connect to the WebSocket server.
     */
    private function connect(): Future
    {
        return async(function () {
            if ($this->isConnected || $this->isConnecting) {
                return;
            }

            $this->isConnecting = true;

            try {
                $this->logger->info("Connecting to WebSocket: {$this->url}");

                // This is a simplified implementation - in practice, you would use
                // a real WebSocket client library like ReactPHP/Pawl or Ratchet/Pawl
                $this->connection = $this->createWebSocketConnection();

                $this->isConnected = true;
                $this->isConnecting = false;
                $this->reconnectAttempts = 0;

                $this->logger->info('WebSocket connected successfully');
                $this->emit('connect');

                // Start heartbeat if enabled
                if ($this->options->enablePing) {
                    $this->startHeartbeat();
                }

                // Set up message handling
                $this->setupMessageHandling();
            } catch (\Throwable $e) {
                $this->isConnecting = false;
                $this->logger->error('WebSocket connection failed: ' . $e->getMessage());

                if ($this->errorHandler) {
                    ($this->errorHandler)($e);
                }

                // Attempt reconnection if enabled
                if ($this->options->autoReconnect && $this->shouldReconnect) {
                    $this->scheduleReconnect();
                } else {
                    throw new McpError(
                        ErrorCode::InternalError,
                        'WebSocket connection failed: ' . $e->getMessage(),
                        $e
                    );
                }
            }
        });
    }

    /**
     * Create a WebSocket connection (placeholder for actual implementation).
     */
    private function createWebSocketConnection(): object
    {
        // This is a placeholder implementation
        // In practice, you would use a library like:
        // - ReactPHP/Socket WebSocket client
        // - Ratchet/Pawl
        // - Amphp WebSocket client

        return new class {
            public function send(string $data): void
            {
                // Placeholder - would send data through actual WebSocket
            }

            public function close(): void
            {
                // Placeholder - would close the WebSocket connection
            }
        };
    }

    /**
     * Set up message handling for the WebSocket connection.
     */
    private function setupMessageHandling(): void
    {
        // This would set up actual message listeners on the WebSocket connection
        // For now, this is a placeholder
    }

    /**
     * Handle incoming WebSocket message.
     */
    private function handleMessage(string $data): void
    {
        try {
            $message = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            if ($this->messageHandler) {
                ($this->messageHandler)($message);
            }

            $this->emit('message', [$message]);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to parse WebSocket message: ' . $e->getMessage());

            if ($this->errorHandler) {
                $error = new McpError(
                    ErrorCode::ParseError,
                    'Failed to parse WebSocket message: ' . $e->getMessage(),
                    $e
                );
                ($this->errorHandler)($error);
            }
        }
    }

    /**
     * Handle WebSocket connection close.
     */
    private function handleClose(int $code = 1000, string $reason = ''): void
    {
        $this->isConnected = false;
        $this->connection = null;

        $this->stopHeartbeat();

        $this->logger->info("WebSocket connection closed: {$code} {$reason}");

        if ($this->closeHandler) {
            ($this->closeHandler)();
        }

        $this->emit('close', [$code, $reason]);

        // Attempt reconnection if enabled and not a normal close
        if ($this->options->autoReconnect && $this->shouldReconnect && $code !== 1000) {
            $this->scheduleReconnect();
        }
    }

    /**
     * Schedule a reconnection attempt.
     */
    private function scheduleReconnect(): void
    {
        if ($this->reconnectAttempts >= $this->options->maxReconnectAttempts) {
            $this->logger->error('Max reconnection attempts reached, giving up');
            $this->shouldReconnect = false;
            return;
        }

        $this->reconnectAttempts++;
        $delay = $this->calculateReconnectDelay();

        $this->logger->info("Scheduling reconnection attempt {$this->reconnectAttempts} in {$delay}ms");

        $this->reconnectTask = async(function () use ($delay) {
            try {
                delay($delay / 1000); // Convert to seconds

                if ($this->shouldReconnect && !$this->isConnected) {
                    $this->connect()->await();
                }
            } catch (\Throwable $e) {
                $this->logger->error('Reconnection attempt failed: ' . $e->getMessage());

                if ($this->shouldReconnect) {
                    $this->scheduleReconnect();
                }
            }
        });
    }

    /**
     * Calculate reconnection delay with exponential backoff.
     */
    private function calculateReconnectDelay(): int
    {
        $baseDelay = $this->options->reconnectDelay;
        $exponentialDelay = $baseDelay * (2 ** ($this->reconnectAttempts - 1));

        // Add jitter (±25%)
        $jitter = $exponentialDelay * 0.25 * (random_int(-100, 100) / 100);
        $delayWithJitter = $exponentialDelay + $jitter;

        // Cap at 30 seconds
        return (int)min($delayWithJitter, 30000);
    }

    /**
     * Start heartbeat/ping mechanism.
     */
    private function startHeartbeat(): void
    {
        if ($this->heartbeatTask || !$this->options->enablePing) {
            return;
        }

        $this->heartbeatTask = async(function () {
            while ($this->isConnected && $this->shouldReconnect) {
                try {
                    delay($this->options->pingInterval);
                    
                    if ($this->isConnected && $this->connection) {
                        // Send ping frame (implementation would depend on WebSocket library)
                        $this->logger->debug('Sending WebSocket ping');
                        // $this->connection->ping();
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Heartbeat failed: ' . $e->getMessage());
                    break;
                }
            }
        });
        
        // Prevent unused variable warning
        if ($this->heartbeatTask) {
            // Task is running in background
        }
    }

    /**
     * Stop heartbeat mechanism.
     */
    private function stopHeartbeat(): void
    {
        if ($this->heartbeatTask) {
            $this->heartbeatTask = null;
        }
    }

    /**
     * Get connection status.
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Get the WebSocket URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get connection options.
     */
    public function getOptions(): WebSocketOptions
    {
        return $this->options;
    }

    /**
     * Get reconnection attempt count.
     */
    public function getReconnectAttempts(): int
    {
        return $this->reconnectAttempts;
    }
}
