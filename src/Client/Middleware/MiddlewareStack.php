<?php

declare(strict_types=1);

namespace MCP\Client\Middleware;

use Amp\Future;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Amp\async;

/**
 * Middleware stack that chains multiple middleware together.
 * 
 * Middleware are executed in the order they were added to the stack.
 * Each middleware can modify the request, call the next middleware,
 * and then modify the response.
 */
class MiddlewareStack implements MiddlewareInterface
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    public function __construct(
        private readonly ClientInterface $httpClient
    ) {}

    /**
     * Add middleware to the stack.
     */
    public function add(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Process a request through the middleware stack.
     */
    public function process(RequestInterface $request, callable $next): Future
    {
        return $this->createHandler(0)($request);
    }

    /**
     * Execute the middleware stack with an HTTP request.
     */
    public function execute(RequestInterface $request): Future
    {
        return async(function () use ($request) {
            $handler = $this->createHandler(0);
            return $handler($request)->await();
        });
    }

    /**
     * Create a handler for the middleware at the given index.
     */
    private function createHandler(int $index): callable
    {
        return function (RequestInterface $request) use ($index): Future {
            return async(function () use ($request, $index) {
                // If we've reached the end of the middleware stack, execute the HTTP request
                if ($index >= count($this->middleware)) {
                    return $this->httpClient->sendRequest($request);
                }

                // Get the current middleware and create the next handler
                $middleware = $this->middleware[$index];
                $next = $this->createHandler($index + 1);

                // Process the request through the current middleware
                return $middleware->process($request, $next)->await();
            });
        };
    }

    /**
     * Get the number of middleware in the stack.
     */
    public function count(): int
    {
        return count($this->middleware);
    }

    /**
     * Check if the stack is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->middleware);
    }

    /**
     * Clear all middleware from the stack.
     */
    public function clear(): self
    {
        $this->middleware = [];
        return $this;
    }

    /**
     * Create a new middleware stack with the given middleware.
     */
    public static function create(ClientInterface $httpClient, MiddlewareInterface ...$middleware): self
    {
        $stack = new self($httpClient);
        foreach ($middleware as $mw) {
            $stack->add($mw);
        }
        return $stack;
    }
}
