<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

/**
 * Configuration options for WebSocket client transport
 *
 * @todo Implement WebSocket transport
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
    ) {}
}
