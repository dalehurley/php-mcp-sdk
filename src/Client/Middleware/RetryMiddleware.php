<?php

declare(strict_types=1);

namespace MCP\Client\Middleware;

use Amp\Future;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Amp\async;
use function Amp\delay;

/**
 * Middleware that retries failed requests with exponential backoff.
 */
class RetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $maxRetries = 3,
        private readonly float $baseDelay = 1.0,
        private readonly float $maxDelay = 30.0,
        private readonly array $retryableStatusCodes = [429, 502, 503, 504],
        private readonly array $retryableExceptions = [
            \Amp\Http\Client\HttpException::class,
            \Amp\Socket\ConnectException::class,
        ]
    ) {}

    public function process(RequestInterface $request, callable $next): Future
    {
        return async(function () use ($request, $next) {
            $attempt = 0;
            $lastException = null;

            while ($attempt <= $this->maxRetries) {
                try {
                    $response = $next($request)->await();

                    // Check if we should retry based on status code
                    if ($attempt < $this->maxRetries && $this->shouldRetryResponse($response)) {
                        $delay = $this->calculateDelay($attempt);
                        delay($delay);
                        $attempt++;
                        continue;
                    }

                    return $response;
                } catch (\Throwable $exception) {
                    $lastException = $exception;

                    // Check if we should retry based on exception type
                    if ($attempt < $this->maxRetries && $this->shouldRetryException($exception)) {
                        $delay = $this->calculateDelay($attempt);
                        delay($delay);
                        $attempt++;
                        continue;
                    }

                    throw $exception;
                }
            }

            // If we get here, we've exhausted all retries
            if ($lastException) {
                throw $lastException;
            }

            // This shouldn't happen, but just in case
            throw new \RuntimeException('Unexpected retry loop termination');
        });
    }

    private function shouldRetryResponse(ResponseInterface $response): bool
    {
        return in_array($response->getStatusCode(), $this->retryableStatusCodes, true);
    }

    private function shouldRetryException(\Throwable $exception): bool
    {
        foreach ($this->retryableExceptions as $retryableClass) {
            if ($exception instanceof $retryableClass) {
                return true;
            }
        }

        return false;
    }

    private function calculateDelay(int $attempt): float
    {
        // Exponential backoff with jitter
        $delay = $this->baseDelay * (2 ** $attempt);
        $jitter = $delay * 0.1 * (random_int(0, 100) / 100); // 10% jitter
        $delayWithJitter = $delay + $jitter;

        return min($delayWithJitter, $this->maxDelay);
    }
}
