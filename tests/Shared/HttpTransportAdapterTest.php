<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use PHPUnit\Framework\TestCase;
use MCP\Shared\StreamableHttpTransportAdapter;
use MCP\Server\Transport\StreamableHttpServerTransportOptions;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

class HttpTransportAdapterTest extends TestCase
{
    public function testConstructor(): void
    {
        $adapter = new StreamableHttpTransportAdapter();

        $this->assertInstanceOf(StreamableHttpTransportAdapter::class, $adapter);
    }

    public function testConstructorWithOptions(): void
    {
        $options = new StreamableHttpServerTransportOptions(
            sessionIdGenerator: fn() => 'test-session',
            enableJsonResponse: true
        );

        $adapter = new StreamableHttpTransportAdapter($options);

        $this->assertInstanceOf(StreamableHttpTransportAdapter::class, $adapter);
    }

    public function testGetTransport(): void
    {
        $adapter = new StreamableHttpTransportAdapter();
        $transport = $adapter->getTransport();

        $this->assertInstanceOf(\MCP\Server\Transport\StreamableHttpServerTransport::class, $transport);
    }

    public function testHandlePsr7Request(): void
    {
        $adapter = new StreamableHttpTransportAdapter();

        // Create mock PSR-7 request
        $uri = $this->createMock(UriInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $body->method('__toString')->willReturn('');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaders')->willReturn([]);
        $request->method('getBody')->willReturn($body);
        $request->method('getParsedBody')->willReturn([]);

        // Create mock PSR-7 response
        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->expects($this->once())->method('write');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('withStatus')->willReturnSelf();
        $response->method('withHeader')->willReturnSelf();

        $result = $adapter->handlePsr7Request($request, $response);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testHandlePsr7RequestAsync(): void
    {
        $adapter = new StreamableHttpTransportAdapter();

        // Create mock PSR-7 request
        $uri = $this->createMock(UriInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $body->method('__toString')->willReturn('');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaders')->willReturn([]);
        $request->method('getBody')->willReturn($body);
        $request->method('getParsedBody')->willReturn([]);

        // Create mock PSR-7 response
        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->expects($this->once())->method('write');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('withStatus')->willReturnSelf();
        $response->method('withHeader')->willReturnSelf();

        $future = $adapter->handlePsr7RequestAsync($request, $response);

        $this->assertInstanceOf(\Amp\Future::class, $future);

        $result = $future->await();
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
