<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

use Amp\Http\Client\HttpClient;

/**
 * Configuration options for StreamableHttpClientTransport
 */
class StreamableHttpClientTransportOptions
{
    /**
     * @param array<string, string>|null $headers Additional headers to send with requests
     * @param string|null $sessionId Session ID for the connection
     * @param StreamableHttpReconnectionOptions|null $reconnectionOptions Reconnection configuration
     * @param HttpClient|null $httpClient Custom HTTP client instance
     */
    public function __construct(
        public readonly ?array $headers = null,
        public readonly ?string $sessionId = null,
        public readonly ?StreamableHttpReconnectionOptions $reconnectionOptions = null,
        public readonly ?HttpClient $httpClient = null
    ) {}
}
