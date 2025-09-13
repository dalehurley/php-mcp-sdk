<?php

declare(strict_types=1);

namespace MCP\Client;

use MCP\Client\Auth\OAuthClientProvider;
use MCP\Client\Middleware\MiddlewareInterface;
use MCP\Types\Implementation;
use Psr\Log\LoggerInterface;

/**
 * Fluent builder for creating MCP clients with middleware.
 */
class ClientBuilder
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    public function __construct(
        private readonly Implementation $clientInfo,
        private readonly ?ClientOptions $options = null
    ) {
    }

    /**
     * Add custom middleware.
     */
    public function withMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Add OAuth authentication middleware.
     */
    public function withOAuth(OAuthClientProvider $provider, ?string $baseUrl = null): self
    {
        $oauthMiddleware = new \MCP\Client\Middleware\OAuthMiddleware($provider, $baseUrl);
        return $this->withMiddleware($oauthMiddleware);
    }

    /**
     * Add retry middleware.
     */
    public function withRetry(int $maxRetries = 3, float $baseDelay = 1.0): self
    {
        $retryMiddleware = new \MCP\Client\Middleware\RetryMiddleware($maxRetries, $baseDelay);
        return $this->withMiddleware($retryMiddleware);
    }

    /**
     * Add logging middleware.
     */
    public function withLogging(LoggerInterface $logger, string $logLevel = 'info'): self
    {
        $loggingMiddleware = new \MCP\Client\Middleware\LoggingMiddleware($logger, $logLevel);
        return $this->withMiddleware($loggingMiddleware);
    }

    /**
     * Build the client with all configured middleware.
     */
    public function build(): Client
    {
        $client = new Client($this->clientInfo, $this->options);

        foreach ($this->middleware as $middleware) {
            $client->addMiddleware($middleware);
        }

        return $client;
    }

    /**
     * Create a client builder with default configuration.
     */
    public static function create(Implementation $clientInfo, ?ClientOptions $options = null): self
    {
        return new self($clientInfo, $options);
    }
}
