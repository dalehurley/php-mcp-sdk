<?php

declare(strict_types=1);

namespace MCP\Tests\Client\Transport;

use MCP\Client\Transport\WebSocketClientTransport;
use MCP\Client\Transport\WebSocketClientTransportOptions;
use PHPUnit\Framework\TestCase;

class WebSocketClientTransportTest extends TestCase
{
    public function testConstructor(): void
    {
        $transport = new WebSocketClientTransport('ws://localhost:8080');

        $this->assertInstanceOf(WebSocketClientTransport::class, $transport);
        $this->assertEquals('ws://localhost:8080', $transport->getUrl());
    }

    public function testConstructorWithOptions(): void
    {
        $options = new WebSocketClientTransportOptions(
            headers: ['Authorization' => 'Bearer token'],
            subprotocols: ['mcp'],
            maxMessageSize: 1024,
            heartbeatInterval: 15000
        );

        $transport = new WebSocketClientTransport('wss://example.com:443/mcp', $options);

        $this->assertInstanceOf(WebSocketClientTransport::class, $transport);
        $this->assertEquals('wss://example.com:443/mcp', $transport->getUrl());
        $this->assertEquals($options, $transport->getOptions());
    }

    public function testOptionsDefaults(): void
    {
        $options = new WebSocketClientTransportOptions();

        $this->assertNull($options->headers);
        $this->assertNull($options->subprotocols);
        $this->assertEquals(4 * 1024 * 1024, $options->maxMessageSize);
        $this->assertEquals(30000, $options->heartbeatInterval);
    }

    public function testOptionsWithCustomValues(): void
    {
        $headers = ['X-Custom' => 'value'];
        $subprotocols = ['mcp', 'custom'];
        $maxMessageSize = 2048;
        $heartbeatInterval = 10000;

        $options = new WebSocketClientTransportOptions(
            headers: $headers,
            subprotocols: $subprotocols,
            maxMessageSize: $maxMessageSize,
            heartbeatInterval: $heartbeatInterval
        );

        $this->assertEquals($headers, $options->headers);
        $this->assertEquals($subprotocols, $options->subprotocols);
        $this->assertEquals($maxMessageSize, $options->maxMessageSize);
        $this->assertEquals($heartbeatInterval, $options->heartbeatInterval);
    }

    public function testMethodsThrowNotImplementedException(): void
    {
        $transport = new WebSocketClientTransport('ws://localhost:8080');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WebSocket transport not yet implemented');

        $transport->start()->await();
    }
}
