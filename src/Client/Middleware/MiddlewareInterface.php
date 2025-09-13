<?php

declare(strict_types=1);

namespace MCP\Client\Middleware;

use Amp\Future;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface for HTTP middleware that can intercept and modify requests/responses.
 *
 * Middleware follows a chain-of-responsibility pattern where each middleware
 * can process the request, call the next middleware in the chain, and then
 * process the response.
 */
interface MiddlewareInterface
{
    /**
     * Process an HTTP request through the middleware chain.
     *
     * @param RequestInterface $request The HTTP request to process
     * @param callable $next The next middleware handler in the chain
     * @return Future<ResponseInterface> The HTTP response
     */
    public function process(RequestInterface $request, callable $next): Future;
}
