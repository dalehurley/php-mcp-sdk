<?php

declare(strict_types=1);

namespace MCP\Server\Transport;

/**
 * Configuration options for WebSocketServerTransport.
 */
class WebSocketServerTransportOptions
{
    /**
     * @param string $host Host to bind to (default: 127.0.0.1)
     * @param int $port Port to bind to (default: 8080)
     * @param bool $enableTls Enable TLS/SSL (default: false)
     * @param string|null $tlsCertPath Path to TLS certificate file
     * @param string|null $tlsKeyPath Path to TLS private key file
     * @param array<string>|null $allowedOrigins List of allowed origin headers for CORS
     * @param array<string>|null $allowedHosts List of allowed host headers
     * @param bool $enableDnsRebindingProtection Enable DNS rebinding protection (default: false)
     * @param int $maxConnections Maximum number of concurrent connections (default: 100)
     * @param int $connectionTimeout Connection timeout in seconds (default: 30)
     * @param int $heartbeatInterval Heartbeat ping interval in seconds (default: 30)
     * @param bool $enablePing Enable WebSocket ping/pong heartbeat (default: true)
     * @param array<string, string>|null $headers Additional headers to send during handshake
     * @param array<string>|null $subprotocols Supported WebSocket subprotocols
     * @param int $maxMessageSize Maximum message size in bytes (default: 1MB)
     * @param int $maxFrameSize Maximum frame size in bytes (default: 64KB)
     * @param bool $enableCompression Enable per-message deflate compression (default: true)
     */
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 8080,
        public readonly bool $enableTls = false,
        public readonly ?string $tlsCertPath = null,
        public readonly ?string $tlsKeyPath = null,
        public readonly ?array $allowedOrigins = null,
        public readonly ?array $allowedHosts = null,
        public readonly bool $enableDnsRebindingProtection = false,
        public readonly int $maxConnections = 100,
        public readonly int $connectionTimeout = 30,
        public readonly int $heartbeatInterval = 30,
        public readonly bool $enablePing = true,
        public readonly ?array $headers = null,
        public readonly ?array $subprotocols = null,
        public readonly int $maxMessageSize = 1024 * 1024, // 1MB
        public readonly int $maxFrameSize = 64 * 1024, // 64KB
        public readonly bool $enableCompression = true
    ) {
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Port must be between 1 and 65535');
        }

        if ($maxConnections < 1) {
            throw new \InvalidArgumentException('Maximum connections must be at least 1');
        }

        if ($connectionTimeout < 1) {
            throw new \InvalidArgumentException('Connection timeout must be at least 1 second');
        }

        if ($heartbeatInterval < 1) {
            throw new \InvalidArgumentException('Heartbeat interval must be at least 1 second');
        }

        if ($maxMessageSize < 1024) {
            throw new \InvalidArgumentException('Maximum message size must be at least 1024 bytes');
        }

        if ($maxFrameSize < 1024) {
            throw new \InvalidArgumentException('Maximum frame size must be at least 1024 bytes');
        }

        if ($enableTls && ($tlsCertPath === null || $tlsKeyPath === null)) {
            throw new \InvalidArgumentException('TLS certificate and key paths are required when TLS is enabled');
        }
    }

    /**
     * Create options for development (default settings).
     */
    public static function development(): self
    {
        return new self();
    }

    /**
     * Create options for production with TLS.
     */
    public static function production(string $certPath, string $keyPath, int $port = 443): self
    {
        return new self(
            host: '0.0.0.0',
            port: $port,
            enableTls: true,
            tlsCertPath: $certPath,
            tlsKeyPath: $keyPath,
            enableDnsRebindingProtection: true,
            maxConnections: 1000
        );
    }

    /**
     * Create options with custom host and port.
     */
    public static function create(string $host, int $port): self
    {
        return new self(host: $host, port: $port);
    }

    /**
     * Create options with CORS configuration.
     */
    public static function withCors(array $allowedOrigins): self
    {
        return new self(allowedOrigins: $allowedOrigins);
    }

    /**
     * Get the server address string.
     */
    public function getAddress(): string
    {
        $scheme = $this->enableTls ? 'wss' : 'ws';

        return "{$scheme}://{$this->host}:{$this->port}";
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'enableTls' => $this->enableTls,
            'allowedOrigins' => $this->allowedOrigins,
            'allowedHosts' => $this->allowedHosts,
            'enableDnsRebindingProtection' => $this->enableDnsRebindingProtection,
            'maxConnections' => $this->maxConnections,
            'connectionTimeout' => $this->connectionTimeout,
            'heartbeatInterval' => $this->heartbeatInterval,
            'enablePing' => $this->enablePing,
            'subprotocols' => $this->subprotocols,
            'maxMessageSize' => $this->maxMessageSize,
            'maxFrameSize' => $this->maxFrameSize,
            'enableCompression' => $this->enableCompression,
        ];
    }
}
