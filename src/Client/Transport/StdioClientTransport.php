<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

use MCP\Shared\Transport;
use MCP\Shared\ReadBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;
use Amp\Process\Process;
use Amp\Future;
use Amp\DeferredFuture;
use Amp\Cancellation;
use Amp\TimeoutCancellation;
use Amp\DeferredCancellation;
use function Amp\async;
use function Amp\ByteStream\buffer;

/**
 * Parameters for starting a stdio server process
 */
class StdioServerParameters
{
    /**
     * @param string $command The executable to run to start the server
     * @param array<string>|null $args Command line arguments to pass to the executable
     * @param array<string, string>|null $env Environment variables (if not specified, default environment will be used)
     * @param string|null $cwd The working directory to use when spawning the process
     * @param bool $inheritStderr Whether to inherit stderr (default: true)
     */
    public function __construct(
        public readonly string $command,
        public readonly ?array $args = null,
        public readonly ?array $env = null,
        public readonly ?string $cwd = null,
        public readonly bool $inheritStderr = true
    ) {}
}

/**
 * Client transport for stdio: this will connect to a server by spawning a 
 * process and communicating with it over stdin/stdout.
 */
class StdioClientTransport implements Transport
{
    private ?Process $_process = null;
    private ReadBuffer $_readBuffer;
    private StdioServerParameters $_serverParams;
    private bool $_started = false;
    
    /** @var callable(array): void|null */
    private $onmessage = null;
    
    /** @var callable(): void|null */
    private $onclose = null;
    
    /** @var callable(\Throwable): void|null */
    private $onerror = null;
    
    /** @var DeferredCancellation|null */
    private ?DeferredCancellation $deferredCancellation = null;
    
    /**
     * Default environment variables to inherit for security
     */
    private const DEFAULT_INHERITED_ENV_VARS = [
        'HOME',
        'LOGNAME', 
        'PATH',
        'SHELL',
        'TERM',
        'USER',
        'LANG',
        'LC_ALL',
        'LC_CTYPE',
        'TZ'
    ];
    
    /**
     * Windows-specific environment variables
     */
    private const WINDOWS_ENV_VARS = [
        'APPDATA',
        'HOMEDRIVE',
        'HOMEPATH',
        'LOCALAPPDATA',
        'PATH',
        'PROCESSOR_ARCHITECTURE',
        'SYSTEMDRIVE',
        'SYSTEMROOT',
        'TEMP',
        'USERNAME',
        'USERPROFILE',
        'PROGRAMFILES'
    ];
    
    public function __construct(StdioServerParameters $serverParams)
    {
        $this->_serverParams = $serverParams;
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
     * Get default environment variables deemed safe to inherit
     */
    private function getDefaultEnvironment(): array
    {
        $env = [];
        $varsToInherit = PHP_OS_FAMILY === 'Windows' 
            ? self::WINDOWS_ENV_VARS 
            : self::DEFAULT_INHERITED_ENV_VARS;
        
        foreach ($varsToInherit as $key) {
            $value = getenv($key);
            if ($value !== false && !str_starts_with($value, '()')) {
                // Skip function definitions for security
                $env[$key] = $value;
            }
        }
        
        return $env;
    }
    
    /**
     * {@inheritDoc}
     */
    public function start(): Future
    {
        return async(function () {
            if ($this->_started) {
                throw new \RuntimeException(
                    "StdioClientTransport already started! If using Client class, " .
                    "note that connect() calls start() automatically."
                );
            }
            
            $this->_started = true;
            
            // Build command with arguments
            $command = [$this->_serverParams->command];
            if ($this->_serverParams->args !== null) {
                $command = array_merge($command, $this->_serverParams->args);
            }
            
            // Merge default env with specified env
            $env = array_merge(
                $this->getDefaultEnvironment(),
                $this->_serverParams->env ?? []
            );
            
            // Start the process
            $this->_process = Process::start(
                $command,
                $this->_serverParams->cwd,
                $env
            );
            
            // Check if process started successfully
            if (!$this->_process->isRunning()) {
                throw new \RuntimeException("Failed to start server process");
            }
            
            // Create cancellation token
            $this->deferredCancellation = new DeferredCancellation();
            
            // Set up stdout reading
            $this->readStdout();
            
            // Set up stderr handling if needed
            if ($this->_serverParams->inheritStderr) {
                $this->handleStderr();
            }
            
            // Monitor process for exit
            async(function () {
                $exitCode = $this->_process->join(null);
                $this->_process = null;
                
                if ($this->onclose !== null) {
                    ($this->onclose)();
                }
            });
        });
    }
    
    /**
     * Read from process stdout in the background
     */
    private function readStdout(): void
    {
        $cancellation = $this->deferredCancellation->getCancellation();
        
        async(function () use ($cancellation) {
            try {
                $stdout = $this->_process->getStdout();
                
                while (($chunk = $stdout->read($cancellation)) !== null) {
                    $this->_readBuffer->append($chunk);
                    $this->processReadBuffer();
                }
            } catch (\Amp\CancelledException $e) {
                // Transport was closed, this is expected
            } catch (\Throwable $e) {
                if ($this->onerror !== null) {
                    ($this->onerror)($e);
                }
            }
        });
    }
    
    /**
     * Handle stderr output
     */
    private function handleStderr(): void
    {
        async(function () {
            try {
                $stderr = $this->_process->getStderr();
                
                // Read stderr and write to current process stderr
                while (($chunk = $stderr->read()) !== null) {
                    fwrite(STDERR, $chunk);
                }
            } catch (\Throwable $e) {
                // Ignore stderr read errors
            }
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
            if ($this->_process === null || !$this->_process->isRunning()) {
                throw new \RuntimeException("Not connected");
            }
            
            try {
                $json = ReadBuffer::serializeMessage($message);
                $stdin = $this->_process->getStdin();
                $stdin->write($json);
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
            // Cancel read operations
            if ($this->deferredCancellation !== null) {
                $this->deferredCancellation->cancel();
            }
            
            // Terminate the process if still running
            if ($this->_process !== null && $this->_process->isRunning()) {
                $this->_process->signal(15); // SIGTERM
                
                // Give process time to exit gracefully
                $timeout = new \Amp\TimeoutCancellation(5); // 5 seconds
                try {
                    $this->_process->join($timeout);
                } catch (\Amp\CancelledException $e) {
                    // Force kill if it didn't exit gracefully
                    $this->_process->signal(9); // SIGKILL
                }
            }
            
            $this->_process = null;
            $this->_readBuffer->clear();
            
            // Notify close handler
            if ($this->onclose !== null) {
                ($this->onclose)();
            }
        });
    }
    
    /**
     * Get the process ID of the spawned server
     * 
     * @return int|null The PID or null if process not started
     */
    public function getPid(): ?int
    {
        return $this->_process?->getPid();
    }
    
    /**
     * Check if the server process is running
     */
    public function isRunning(): bool
    {
        return $this->_process !== null && $this->_process->isRunning();
    }
}
