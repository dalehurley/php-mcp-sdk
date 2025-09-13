<?php

declare(strict_types=1);

namespace MCP\Shared;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use MCP\Server\Transport\StreamableHttpServerTransport;
use MCP\Server\Transport\StreamableHttpServerTransportOptions;
use Amp\Future;

use function Amp\async;

/**
 * Interface for adapting MCP transports to PSR-7 HTTP messages
 * This enables integration with Laravel, Symfony, and other PSR-7 compatible frameworks
 */
interface HttpTransportAdapter
{
    /**
     * Handle a PSR-7 HTTP request and return a PSR-7 response
     *
     * @param ServerRequestInterface $request The incoming PSR-7 request
     * @param ResponseInterface $response The PSR-7 response to modify
     * @return ResponseInterface The modified PSR-7 response
     */
    public function handlePsr7Request(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface;

    /**
     * Handle a PSR-7 HTTP request asynchronously and return a Future<ResponseInterface>
     *
     * @param ServerRequestInterface $request The incoming PSR-7 request
     * @param ResponseInterface $response The PSR-7 response to modify
     * @return Future<ResponseInterface> The modified PSR-7 response
     */
    public function handlePsr7RequestAsync(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): Future;
}

/**
 * Adapter for StreamableHttpServerTransport to work with PSR-7 HTTP messages
 * This enables easy integration with Laravel, Symfony, and other PSR-7 frameworks
 *
 * Note: This is a placeholder implementation. Full integration would require
 * exposing public methods from the transport classes or creating a bridge.
 */
class StreamableHttpTransportAdapter implements HttpTransportAdapter
{
    private StreamableHttpServerTransport $transport;

    public function __construct(
        ?StreamableHttpServerTransportOptions $options = null
    ) {
        $this->transport = new StreamableHttpServerTransport($options ?? new StreamableHttpServerTransportOptions());
    }

    /**
     * {@inheritDoc}
     */
    public function handlePsr7Request(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->handlePsr7RequestAsync($request, $response)->await();
    }

    /**
     * {@inheritDoc}
     */
    public function handlePsr7RequestAsync(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): Future {
        return async(function () use ($request, $response) {
            // For now, return a placeholder response
            // Full implementation would require transport API exposure
            $body = json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32601,
                    'message' => 'Transport adapter not fully implemented yet'
                ]
            ]);

            $response->getBody()->write($body);
            return $response
                ->withStatus(501)
                ->withHeader('Content-Type', 'application/json');
        });
    }

    /**
     * Get the underlying transport instance
     */
    public function getTransport(): StreamableHttpServerTransport
    {
        return $this->transport;
    }
}
