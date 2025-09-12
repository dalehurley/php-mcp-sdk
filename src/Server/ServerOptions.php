<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Shared\ProtocolOptions;
use MCP\Types\Capabilities\ServerCapabilities;

/**
 * Options for creating a Server instance.
 */
class ServerOptions extends ProtocolOptions
{
    /**
     * @param ServerCapabilities|null $capabilities Capabilities to advertise as being supported by this server
     * @param string|null $instructions Optional instructions describing how to use the server and its features
     * @param int|null $requestTimeout Request timeout in milliseconds (default: 60000)
     * @param bool $enforceStrictCapabilities Whether to enforce strict capability checking
     * @param array<string> $debouncedNotificationMethods Notification methods to debounce
     */
    public function __construct(
        public readonly ?ServerCapabilities $capabilities = null,
        public readonly ?string $instructions = null,
        public readonly ?int $requestTimeout = null,
        bool $enforceStrictCapabilities = false,
        array $debouncedNotificationMethods = []
    ) {
        parent::__construct($enforceStrictCapabilities, $debouncedNotificationMethods);
    }
}
