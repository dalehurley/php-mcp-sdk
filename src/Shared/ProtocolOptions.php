<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * Additional initialization options.
 */
class ProtocolOptions
{
    /**
     * @param array<string> $debouncedNotificationMethods
     */
    public function __construct(
        public readonly bool $enforceStrictCapabilities = false,
        public readonly array $debouncedNotificationMethods = []
    ) {}
}
