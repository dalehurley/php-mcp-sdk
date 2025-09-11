<?php

declare(strict_types=1);

namespace MCP\Server\Transport;

use MCP\Shared\Transport;
use MCP\Shared\ReadBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;
use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\getStdout;
use Amp\Future;
use Amp\DeferredFuture;
use Amp\DeferredCancellation;
use function Amp\async;

/**
 * Server transport for stdio: this communicates with a MCP client by reading 
 * from the current process' stdin and writing to stdout.
 */
class StdioServerTransport implements Transport
{
    private ReadBuffer $_readBuffer;
    private bool $_started = false;
    private ReadableStream $_stdin;
    private WritableStream $_stdout;
    
    /** @var callable(array): void|null */
    private $onmessage = null;
    
    /** @var callable(): void|null */
    private $onclose = null;
    
    /** @var callable(\Throwable): void|null */
    private $onerror = null;
    
    /** @var DeferredCancellation|null */
    private ?DeferredCancellation $deferredCancellation = null;
    
    /**
     * @param ReadableStream|null $stdin Input stream (defaults to STDIN)
     * @param WritableStream|null $stdout Output stream (defaults to STDOUT)
     */
    public function __construct(
        ?ReadableStream $stdin = null,
        ?WritableStream $stdout = null
    ) {
        $this->_stdin = $stdin ?? getStdin();
        $this->_stdout = $stdout ?? getStdout();
        $this->_readBuffer = new ReadBuffer();
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
            if ($this->_started) {
                throw new \RuntimeException(
                    "StdioServerTransport already started! If using Server class, " .
                    "note that connect() calls start() automatically."
                );
            }
            
            $this->_started = true;
            
            // Create cancellation token
            $this->deferredCancellation = new DeferredCancellation();
            $cancellation = $this->deferredCancellation->getCancellation();
            
            // Start reading from stdin in the background
            async(function () use ($cancellation) {
                try {
                    while (($chunk = $this->_stdin->read($cancellation)) !== null) {
                        $this->_readBuffer->append($chunk);
                        $this->processReadBuffer();
                    }
                    
                    // Stream ended, trigger close
                    if ($this->onclose !== null) {
                        ($this->onclose)();
                    }
                } catch (\Amp\CancelledException $e) {
                    // Transport was closed, this is expected
                } catch (\Throwable $e) {
                    if ($this->onerror !== null) {
                        ($this->onerror)($e);
                    }
                }
            });
        });
    }
    
    /**
     * Process buffered data and emit complete messages
     */
    private function processReadBuffer(): void
    {
        while (true) {
            try {
                $message = $this->_readBuffer->readMessage();
                if ($message === null) {
                    break;
                }
                
                if ($this->onmessage !== null) {
                    ($this->onmessage)($message->jsonSerialize());
                }
            } catch (\Throwable $error) {
                if ($this->onerror !== null) {
                    ($this->onerror)($error);
                }
            }
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function send(array $message): Future
    {
        return async(function () use ($message) {
            try {
                $json = ReadBuffer::serializeMessage($message);
                $this->_stdout->write($json);
            } catch (\Throwable $e) {
                if ($this->onerror !== null) {
                    ($this->onerror)($e);
                }
                throw $e;
            }
        });
    }
    
    /**
     * {@inheritDoc}
     */
    public function close(): Future
    {
        return async(function () {
            // Cancel the read loop
            if ($this->deferredCancellation !== null) {
                $this->deferredCancellation->cancel();
            }
            
            // Clear the buffer
            $this->_readBuffer->clear();
            
            // Close streams if they support it
            if (method_exists($this->_stdin, 'close')) {
                $this->_stdin->close();
            }
            
            if (method_exists($this->_stdout, 'close')) {
                $this->_stdout->close();
            }
            
            // Notify close handler
            if ($this->onclose !== null) {
                ($this->onclose)();
            }
        });
    }
}
