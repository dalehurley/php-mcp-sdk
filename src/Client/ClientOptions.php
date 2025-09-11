<?php

declare(strict_types=1);

namespace MCP\Client;

use MCP\Shared\ProtocolOptions;
use MCP\Types\Capabilities\ClientCapabilities;

/**
 * Options for configuring an MCP client.
 */
class ClientOptions extends ProtocolOptions
{
    /**
     * @param ClientCapabilities|null $capabilities Capabilities to advertise as being supported by this client
     * @param bool $enforceStrictCapabilities Whether to enforce strict capability checking
     * @param array<string> $debouncedNotificationMethods Methods to debounce notifications for
     */
    public function __construct(
        public readonly ?ClientCapabilities $capabilities = null,
        bool $enforceStrictCapabilities = false,
        array $debouncedNotificationMethods = []
    ) {
        parent::__construct($enforceStrictCapabilities, $debouncedNotificationMethods);
    }
}
