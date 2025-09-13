<?php

declare(strict_types=1);

namespace MCP\Tests\Server\Transport;

use MCP\Server\Transport\WebSocketServerTransport;
use MCP\Server\Transport\WebSocketServerTransportOptions;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class WebSocketServerTransportTest extends TestCase
{
    public function testConstructor(): void
    {
        $options = new WebSocketServerTransportOptions();
        $transport = new WebSocketServerTransport($options, new NullLogger());

        $this->assertInstanceOf(WebSocketServerTransport::class, $transport);
    }

    public function testOptionsValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Port must be between 1 and 65535');

        new WebSocketServerTransportOptions(port: 0);
    }

    public function testOptionsDefaults(): void
    {
        $options = new WebSocketServerTransportOptions();

        $this->assertEquals('127.0.0.1', $options->host);
        $this->assertEquals(8080, $options->port);
        $this->assertFalse($options->enableTls);
        $this->assertEquals(100, $options->maxConnections);
        $this->assertTrue($options->enablePing);
    }

    public function testGetStatus(): void
    {
        $transport = new WebSocketServerTransport();
        $status = $transport->getStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('started', $status);
        $this->assertArrayHasKey('address', $status);
        $this->assertArrayHasKey('connections', $status);
        $this->assertArrayHasKey('maxConnections', $status);

        $this->assertFalse($status['started']);
        $this->assertEquals(0, $status['connections']);
    }

    public function testConnectionCount(): void
    {
        $transport = new WebSocketServerTransport();
        $this->assertEquals(0, $transport->getConnectionCount());
    }

    public function testOptionsFactoryMethods(): void
    {
        $devOptions = WebSocketServerTransportOptions::development();
        $this->assertEquals('127.0.0.1', $devOptions->host);
        $this->assertEquals(8080, $devOptions->port);

        $customOptions = WebSocketServerTransportOptions::create('0.0.0.0', 9000);
        $this->assertEquals('0.0.0.0', $customOptions->host);
        $this->assertEquals(9000, $customOptions->port);

        $corsOptions = WebSocketServerTransportOptions::withCors(['https://example.com']);
        $this->assertEquals(['https://example.com'], $corsOptions->allowedOrigins);
    }

    public function testGetAddress(): void
    {
        $options = new WebSocketServerTransportOptions(host: 'localhost', port: 9000);
        $this->assertEquals('ws://localhost:9000', $options->getAddress());

        $tlsOptions = new WebSocketServerTransportOptions(
            host: 'localhost',
            port: 9000,
            enableTls: true,
            tlsCertPath: '/path/to/cert.pem',
            tlsKeyPath: '/path/to/key.pem'
        );
        $this->assertEquals('wss://localhost:9000', $tlsOptions->getAddress());
    }
}
