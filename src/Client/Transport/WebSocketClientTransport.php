<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

use MCP\Shared\Transport;
use Amp\Future;
use function Amp\async;

/**
 * Client transport for WebSocket: this will connect to a server using WebSocket protocol
 * for bidirectional real-time communication.
 * 
 * @todo Implement WebSocket transport
 * @internal This transport is not yet implemented and will throw an exception when used
 */
class WebSocketClientTransport implements Transport
{
    private string $_url;
    private WebSocketClientTransportOptions $_options;

    /** @var callable(array): void|null */
    private $onmessage = null;

    /** @var callable(): void|null */
    private $onclose = null;

    /** @var callable(\Throwable): void|null */
    private $onerror = null;

    /**
     * @param string $url WebSocket URL (ws:// or wss://)
     * @param WebSocketClientTransportOptions|null $options Configuration options
     */
    public function __construct(string $url, ?WebSocketClientTransportOptions $options = null)
    {
        $this->_url = $url;
        $this->_options = $options ?? new WebSocketClientTransportOptions();

        // WebSocket transport is not yet implemented - methods will throw when called
    }

    /**
     * {@inheritDoc}
     */
    public function setMessageHandler(callable $handler): void
    {
        $this->onmessage = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function setCloseHandler(callable $handler): void
    {
        $this->onclose = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function setErrorHandler(callable $handler): void
    {
        $this->onerror = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function start(): Future
    {
        return async(function () {
            throw new \RuntimeException('WebSocket transport not yet implemented');
        });
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $message): Future
    {
        return async(function () use ($message) {
            throw new \RuntimeException('WebSocket transport not yet implemented');
        });
    }

    /**
     * {@inheritDoc}
     */
    public function close(): Future
    {
        return async(function () {
            throw new \RuntimeException('WebSocket transport not yet implemented');
        });
    }

    /**
     * Get the WebSocket URL
     */
    public function getUrl(): string
    {
        return $this->_url;
    }

    /**
     * Get the transport options
     */
    public function getOptions(): WebSocketClientTransportOptions
    {
        return $this->_options;
    }
}
