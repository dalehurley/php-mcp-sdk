<?php

declare(strict_types=1);

namespace MCP\Client\Middleware;

use Amp\Future;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function Amp\async;

/**
 * Middleware that logs HTTP requests and responses.
 */
class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $logLevel = LogLevel::INFO,
        private readonly bool $includeRequestHeaders = false,
        private readonly bool $includeResponseHeaders = false,
        private readonly int $statusLevelThreshold = 0
    ) {}

    public function process(RequestInterface $request, callable $next): Future
    {
        return async(function () use ($request, $next) {
            $startTime = microtime(true);
            $method = $request->getMethod();
            $uri = (string)$request->getUri();

            try {
                $response = $next($request)->await();
                $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

                // Only log if status meets the threshold
                if ($response->getStatusCode() >= $this->statusLevelThreshold) {
                    $this->logRequest($method, $uri, $response->getStatusCode(), $response->getReasonPhrase(), $duration, $request, $response);
                }

                return $response;
            } catch (\Throwable $exception) {
                $duration = (microtime(true) - $startTime) * 1000;
                $this->logError($method, $uri, $duration, $exception, $request);
                throw $exception;
            }
        });
    }

    private function logRequest(
        string $method,
        string $uri,
        int $status,
        string $reasonPhrase,
        float $duration,
        RequestInterface $request,
        ResponseInterface $response
    ): void {
        $message = sprintf(
            'HTTP %s %s %d %s (%.2fms)',
            $method,
            $uri,
            $status,
            $reasonPhrase,
            $duration
        );

        $context = [
            'method' => $method,
            'uri' => $uri,
            'status' => $status,
            'duration_ms' => $duration,
        ];

        if ($this->includeRequestHeaders) {
            $context['request_headers'] = $this->formatHeaders($request->getHeaders());
        }

        if ($this->includeResponseHeaders) {
            $context['response_headers'] = $this->formatHeaders($response->getHeaders());
        }

        // Use error level for 4xx/5xx responses
        $level = $status >= 400 ? LogLevel::ERROR : $this->logLevel;
        $this->logger->log($level, $message, $context);
    }

    private function logError(
        string $method,
        string $uri,
        float $duration,
        \Throwable $exception,
        RequestInterface $request
    ): void {
        $message = sprintf(
            'HTTP %s %s failed: %s (%.2fms)',
            $method,
            $uri,
            $exception->getMessage(),
            $duration
        );

        $context = [
            'method' => $method,
            'uri' => $uri,
            'duration_ms' => $duration,
            'exception' => $exception,
        ];

        if ($this->includeRequestHeaders) {
            $context['request_headers'] = $this->formatHeaders($request->getHeaders());
        }

        $this->logger->error($message, $context);
    }

    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $values) {
            $formatted[$name] = implode(', ', $values);
        }
        return $formatted;
    }
}
