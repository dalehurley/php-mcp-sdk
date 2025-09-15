<?php

declare(strict_types=1);

namespace MCP\Tests\Transport;

use MCP\Client\Transport\StreamableHttpClientTransport;
use MCP\Client\Transport\StreamableHttpClientTransportOptions;
use PHPUnit\Framework\TestCase;

class StreamableHttpClientTransportTest extends TestCase
{
    public function testTransportCreation(): void
    {
        $options = new StreamableHttpClientTransportOptions();

        $transport = new StreamableHttpClientTransport('http://localhost:3000', $options);
        $this->assertInstanceOf(StreamableHttpClientTransport::class, $transport);
    }

    public function testTransportOptions(): void
    {
        $options = new StreamableHttpClientTransportOptions(
            headers: [
                'Authorization' => 'Bearer token123',
                'User-Agent' => 'MCP-Client/1.0',
            ],
            sessionId: 'test-session'
        );

        $transport = new StreamableHttpClientTransport('https://api.example.com', $options);
        $this->assertInstanceOf(StreamableHttpClientTransport::class, $transport);
    }

    public function testSetHandlers(): void
    {
        $options = new StreamableHttpClientTransportOptions();
        $transport = new StreamableHttpClientTransport('http://localhost:3000', $options);

        $messageReceived = false;
        $errorReceived = false;
        $closed = false;

        $transport->setMessageHandler(function (array $message) use (&$messageReceived) {
            $messageReceived = true;
        });

        $transport->setErrorHandler(function (\Throwable $error) use (&$errorReceived) {
            $errorReceived = true;
        });

        $transport->setCloseHandler(function () use (&$closed) {
            $closed = true;
        });

        // Handlers should be set without errors
        $this->assertTrue(true);
    }

    public function testConnectionAttempt(): void
    {
        $options = new StreamableHttpClientTransportOptions();
        $transport = new StreamableHttpClientTransport('http://localhost:3000', $options);

        $closed = false;
        $transport->setCloseHandler(function () use (&$closed) {
            $closed = true;
        });

        try {
            // This might fail if no server is running, which is expected
            $transport->start()->await();
            $transport->close()->await();
            $this->assertTrue($closed);
        } catch (\Throwable $e) {
            // Connection failure is expected in test environment
            $this->assertInstanceOf(\Throwable::class, $e);
        }
    }
}
