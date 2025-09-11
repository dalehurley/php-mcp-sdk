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
     */
    public function __construct(
        public readonly ?array $headers = null,
        public readonly ?array $subprotocols = null,
        public readonly int $maxMessageSize = 4 * 1024 * 1024, // 4MB
        public readonly int $heartbeatInterval = 30000 // 30 seconds
    ) {}
}
