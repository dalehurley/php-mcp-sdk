<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

/**
 * Configuration options for WebSocket client transport
 *
 * This class provides comprehensive configuration options for WebSocket client transport,
 * including connection parameters, reconnection behavior, heartbeat settings, and message handling.
 */
class WebSocketClientTransportOptions
{
    /**
     * @param array<string, string>|null $headers Additional headers to send with WebSocket handshake
     * @param array<string>|null $subprotocols WebSocket subprotocols to request
     * @param int $maxMessageSize Maximum message size in bytes
     * @param int $heartbeatInterval Heartbeat interval in milliseconds
     * @param bool $autoReconnect Whether to automatically reconnect on connection loss
     * @param int $maxReconnectAttempts Maximum number of reconnection attempts
     * @param int $reconnectDelay Base delay between reconnection attempts in milliseconds
     * @param bool $enablePing Whether to enable ping/pong heartbeat
     * @param int $pingInterval Interval between ping messages in milliseconds
     */
    public function __construct(
        public readonly ?array $headers = null,
        public readonly ?array $subprotocols = null,
        public readonly int $maxMessageSize = 4 * 1024 * 1024, // 4MB
        public readonly int $heartbeatInterval = 30000, // 30 seconds
        public readonly bool $autoReconnect = true,
        public readonly int $maxReconnectAttempts = 5,
        public readonly int $reconnectDelay = 1000, // 1 second
        public readonly bool $enablePing = true,
        public readonly int $pingInterval = 30000 // 30 seconds
    ) {
        $this->validate();
    }

    /**
     * Validate the configuration options.
     *
     * @throws \InvalidArgumentException if any option is invalid
     */
    private function validate(): void
    {
        if ($this->maxMessageSize <= 0) {
            throw new \InvalidArgumentException('maxMessageSize must be positive');
        }

        if ($this->heartbeatInterval <= 0) {
            throw new \InvalidArgumentException('heartbeatInterval must be positive');
        }

        if ($this->maxReconnectAttempts < 0) {
            throw new \InvalidArgumentException('maxReconnectAttempts must be non-negative');
        }

        if ($this->reconnectDelay < 0) {
            throw new \InvalidArgumentException('reconnectDelay must be non-negative');
        }

        if ($this->pingInterval <= 0) {
            throw new \InvalidArgumentException('pingInterval must be positive');
        }

        if ($this->headers !== null) {
            foreach ($this->headers as $name => $value) {
                if (!is_string($name) || !is_string($value)) {
                    throw new \InvalidArgumentException('All headers must be string key-value pairs');
                }
            }
        }

        if ($this->subprotocols !== null) {
            foreach ($this->subprotocols as $protocol) {
                if (!is_string($protocol)) {
                    throw new \InvalidArgumentException('All subprotocols must be strings');
                }
            }
        }
    }

    /**
     * Create options with custom headers.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            headers: $headers,
            subprotocols: $this->subprotocols,
            maxMessageSize: $this->maxMessageSize,
            heartbeatInterval: $this->heartbeatInterval,
            autoReconnect: $this->autoReconnect,
            maxReconnectAttempts: $this->maxReconnectAttempts,
            reconnectDelay: $this->reconnectDelay,
            enablePing: $this->enablePing,
            pingInterval: $this->pingInterval
        );
    }

    /**
     * Create options with custom subprotocols.
     *
     * @param array<string> $subprotocols
     */
    public function withSubprotocols(array $subprotocols): self
    {
        return new self(
            headers: $this->headers,
            subprotocols: $subprotocols,
            maxMessageSize: $this->maxMessageSize,
            heartbeatInterval: $this->heartbeatInterval,
            autoReconnect: $this->autoReconnect,
            maxReconnectAttempts: $this->maxReconnectAttempts,
            reconnectDelay: $this->reconnectDelay,
            enablePing: $this->enablePing,
            pingInterval: $this->pingInterval
        );
    }

    /**
     * Create options with disabled auto-reconnect.
     */
    public function withoutAutoReconnect(): self
    {
        return new self(
            headers: $this->headers,
            subprotocols: $this->subprotocols,
            maxMessageSize: $this->maxMessageSize,
            heartbeatInterval: $this->heartbeatInterval,
            autoReconnect: false,
            maxReconnectAttempts: 0,
            reconnectDelay: $this->reconnectDelay,
            enablePing: $this->enablePing,
            pingInterval: $this->pingInterval
        );
    }

    /**
     * Create options with custom timeout settings.
     */
    public function withTimeouts(
        int $heartbeatInterval,
        int $pingInterval,
        int $reconnectDelay
    ): self {
        return new self(
            headers: $this->headers,
            subprotocols: $this->subprotocols,
            maxMessageSize: $this->maxMessageSize,
            heartbeatInterval: $heartbeatInterval,
            autoReconnect: $this->autoReconnect,
            maxReconnectAttempts: $this->maxReconnectAttempts,
            reconnectDelay: $reconnectDelay,
            enablePing: $this->enablePing,
            pingInterval: $pingInterval
        );
    }

    /**
     * Create default options for production use.
     */
    public static function forProduction(): self
    {
        return new self(
            maxMessageSize: 8 * 1024 * 1024, // 8MB for production
            heartbeatInterval: 30000,
            autoReconnect: true,
            maxReconnectAttempts: 10,
            reconnectDelay: 5000, // 5 seconds
            enablePing: true,
            pingInterval: 30000
        );
    }

    /**
     * Create options for development/testing.
     */
    public static function forDevelopment(): self
    {
        return new self(
            maxMessageSize: 1024 * 1024, // 1MB for development
            heartbeatInterval: 10000, // 10 seconds
            autoReconnect: true,
            maxReconnectAttempts: 3,
            reconnectDelay: 1000, // 1 second
            enablePing: true,
            pingInterval: 10000
        );
    }
}
