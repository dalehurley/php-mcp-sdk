<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

use Amp\Http\Client\HttpClient;

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
    ) {
    }
}
