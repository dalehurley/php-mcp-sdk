<?php

declare(strict_types=1);

namespace MCP\Tests\Utils;

use MCP\Shared\Transport;
use Amp\Future;
use Evenement\EventEmitter;
use function Amp\async;

/**
 * In-memory transport for testing purposes
 * Allows direct message passing without network I/O
 */
class InMemoryTransport extends EventEmitter implements Transport
{
    private array $messageQueue = [];
    private $messageHandler = null;
    private $closeHandler = null;
    private $errorHandler = null;
    private bool $started = false;
    private bool $closed = false;

    public function start(): Future
    {
        return async(function () {
            if ($this->closed) {
                throw new \RuntimeException('Transport is closed');
            }

            $this->started = true;
            $this->emit('start');
            return null;
        });
    }

    public function send(array $message): Future
    {
        return async(function () use ($message) {
            if (!$this->started || $this->closed) {
                throw new \RuntimeException('Transport not ready');
            }

            $this->messageQueue[] = $message;
            $this->emit('message_sent', [$message]);
            return null;
        });
    }

    public function close(): Future
    {
        return async(function () {
            if ($this->closed) {
                return null;
            }

            $this->closed = true;
            $this->started = false;

            if ($this->closeHandler) {
                ($this->closeHandler)();
            }

            $this->emit('close');
            return null;
        });
    }

    public function setMessageHandler(callable $handler): void
    {
        $this->messageHandler = $handler;
    }

    public function setCloseHandler(callable $handler): void
    {
        $this->closeHandler = $handler;
    }

    public function setErrorHandler(callable $handler): void
    {
        $this->errorHandler = $handler;
    }

    // Test helper methods

    /**
     * Simulate receiving a message from the other end
     */
    public function simulateMessage(array $message): void
    {
        if ($this->messageHandler && $this->started && !$this->closed) {
            ($this->messageHandler)($message);
        }
    }

    /**
     * Simulate an error condition
     */
    public function simulateError(\Throwable $error): void
    {
        if ($this->errorHandler) {
            ($this->errorHandler)($error);
        }
        $this->emit('error', [$error]);
    }

    /**
     * Get all messages that were sent through this transport
     */
    public function getSentMessages(): array
    {
        return $this->messageQueue;
    }

    /**
     * Clear the sent messages queue
     */
    public function clearSentMessages(): void
    {
        $this->messageQueue = [];
    }

    /**
     * Check if transport is started
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Check if transport is closed
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Get the number of sent messages
     */
    public function getSentMessageCount(): int
    {
        return count($this->messageQueue);
    }

    /**
     * Create a pair of connected transports for testing
     * Messages sent on one will be received on the other
     */
    public static function createConnectedPair(): array
    {
        $transport1 = new self();
        $transport2 = new self();

        // Connect them bidirectionally
        $transport1->on('message_sent', function (array $message) use ($transport2) {
            $transport2->simulateMessage($message);
        });

        $transport2->on('message_sent', function (array $message) use ($transport1) {
            $transport1->simulateMessage($message);
        });

        return [$transport1, $transport2];
    }
}
