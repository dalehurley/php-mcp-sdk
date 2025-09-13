<?php

declare(strict_types=1);

namespace MCP\Server\Transport;

/**
 * Configuration options for SSE server transport
 *
 * @deprecated Use StreamableHttpServerTransport instead
 * @internal This class will be removed in a future version
 */
class SseServerTransportOptions
{
    /**
     * @param array<string>|null $allowedHosts List of allowed host headers
     * @param array<string>|null $allowedOrigins List of allowed origin headers
     * @param bool $enableDnsRebindingProtection Enable DNS rebinding protection
     */
    public function __construct(
        public readonly ?array $allowedHosts = null,
        public readonly ?array $allowedOrigins = null,
        public readonly bool $enableDnsRebindingProtection = false
    ) {
    }
}
