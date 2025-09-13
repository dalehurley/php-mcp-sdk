<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

/**
 * Options for configuring WebSocket client transport.
 */
class WebSocketOptions
{
    public function __construct(
        public readonly ?array $headers = null,
        public readonly ?array $subprotocols = null,
        public readonly int $timeout = 30,
        public readonly bool $autoReconnect = true,
        public readonly int $maxReconnectAttempts = 5,
        public readonly int $reconnectDelay = 1000,
        public readonly int $heartbeatInterval = 30,
        public readonly bool $enablePing = true,
        public readonly int $pingInterval = 30,
        public readonly int $pongTimeout = 5,
        public readonly ?string $origin = null,
        public readonly bool $validateCertificate = true
    ) {
    }

    /**
     * Create options with custom headers.
     */
    public static function withHeaders(array $headers): self
    {
        return new self(headers: $headers);
    }

    /**
     * Create options with authentication header.
     */
    public static function withAuth(string $token, string $type = 'Bearer'): self
    {
        return new self(headers: ['Authorization' => "{$type} {$token}"]);
    }

    /**
     * Create options with custom subprotocols.
     */
    public static function withSubprotocols(array $subprotocols): self
    {
        return new self(subprotocols: $subprotocols);
    }

    /**
     * Create options for development (no SSL validation).
     */
    public static function development(): self
    {
        return new self(validateCertificate: false);
    }

    /**
     * Convert to array for use with WebSocket client libraries.
     */
    public function toArray(): array
    {
        return [
            'headers' => $this->headers ?? [],
            'subprotocols' => $this->subprotocols ?? [],
            'timeout' => $this->timeout,
            'origin' => $this->origin,
            'verify_peer' => $this->validateCertificate,
            'verify_peer_name' => $this->validateCertificate,
        ];
    }
}
