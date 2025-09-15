<?php

declare(strict_types=1);

namespace MCP\Server\Transport;

use Amp\Future;

/**
 * Interface for resumability support via event storage.
 */
interface EventStore
{
    /**
     * Stores an event for later retrieval.
     *
     * @param string $streamId ID of the stream the event belongs to
     * @param \MCP\Types\JsonRpc\JSONRPCMessage $message The JSON-RPC message to store
     *
     * @return Future<string> The generated event ID for the stored event
     */
    public function storeEvent(string $streamId, \MCP\Types\JsonRpc\JSONRPCMessage $message): Future;

    /**
     * Replay events after a specific event ID.
     *
     * @param string $lastEventId The last event ID received by client
     * @param callable(string, \MCP\Types\JsonRpc\JSONRPCMessage): Future<void> $send Callback to send events
     *
     * @return Future<string> The stream ID
     */
    public function replayEventsAfter(string $lastEventId, callable $send): Future;
}

/**
 * Configuration options for StreamableHttpServerTransport.
 */
class StreamableHttpServerTransportOptions
{
    /**
     * @param callable(): string|null $sessionIdGenerator Function that generates session IDs (null for stateless)
     * @param callable(string): void|Future<void>|null $onsessioninitialized Callback when session is initialized
     * @param callable(string): void|Future<void>|null $onsessionclosed Callback when session is closed
     * @param bool $enableJsonResponse If true, return JSON responses instead of SSE streams
     * @param EventStore|null $eventStore Event store for resumability support
     * @param array<string>|null $allowedHosts List of allowed host headers
     * @param array<string>|null $allowedOrigins List of allowed origin headers
     * @param bool $enableDnsRebindingProtection Enable DNS rebinding protection
     */
    public function __construct(
        public readonly mixed $sessionIdGenerator = null,
        public readonly mixed $onsessioninitialized = null,
        public readonly mixed $onsessionclosed = null,
        public readonly bool $enableJsonResponse = false,
        public readonly ?EventStore $eventStore = null,
        public readonly ?array $allowedHosts = null,
        public readonly ?array $allowedOrigins = null,
        public readonly bool $enableDnsRebindingProtection = false
    ) {
    }
}
