<?php

declare(strict_types=1);

namespace MCP\Shared;

use Amp\Future;

/**
 * Transport interface for MCP communication
 *
 * This interface defines the contract for all transport implementations
 * (stdio, HTTP, WebSocket, etc.) using Amphp for async operations.
 */
interface Transport
{
    /**
     * Start the transport connection
     *
     * @return Future<void>
     */
    public function start(): Future;

    /**
     * Send a message through the transport
     *
     * @param array $message The message to send (will be JSON encoded)
     * @return Future<void>
     */
    public function send(array $message): Future;

    /**
     * Close the transport connection
     *
     * @return Future<void>
     */
    public function close(): Future;

    /**
     * Set handler for incoming messages
     *
     * @param callable(array): void $handler Handler function that receives decoded messages
     */
    public function setMessageHandler(callable $handler): void;

    /**
     * Set handler for connection close events
     *
     * @param callable(): void $handler Handler function called when connection closes
     */
    public function setCloseHandler(callable $handler): void;

    /**
     * Set handler for transport errors
     *
     * @param callable(\Throwable): void $handler Handler function that receives errors
     */
    public function setErrorHandler(callable $handler): void;
}
