<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

/**
 * Configuration options for reconnection behavior
 */
class StreamableHttpReconnectionOptions
{
    public function __construct(
        public readonly int $maxReconnectionDelay = 30000,
        public readonly int $initialReconnectionDelay = 1000,
        public readonly float $reconnectionDelayGrowFactor = 1.5,
        public readonly int $maxRetries = 2
    ) {}
}
