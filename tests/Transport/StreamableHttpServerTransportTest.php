<?php

declare(strict_types=1);

namespace MCP\Tests\Transport;

use Amp\Future;
use MCP\Server\Transport\StreamableHttpServerTransport;
use MCP\Server\Transport\StreamableHttpServerTransportOptions;
use PHPUnit\Framework\TestCase;

class StreamableHttpServerTransportTest extends TestCase
{
    private StreamableHttpServerTransport $transport;

    protected function setUp(): void
    {
        $options = new StreamableHttpServerTransportOptions(
            sessionIdGenerator: fn () => 'test-session-' . uniqid(),
            enableJsonResponse: true
        );

        $this->transport = new StreamableHttpServerTransport($options);
    }

    public function testTransportCreation(): void
    {
        $this->assertInstanceOf(StreamableHttpServerTransport::class, $this->transport);
    }

    public function testTransportStart(): void
    {
        $future = $this->transport->start();
        $this->assertInstanceOf(Future::class, $future);

        $result = $future->await();
        $this->assertNull($result);
    }

    public function testSetHandlers(): void
    {
        $messageReceived = false;
        $errorReceived = false;
        $closed = false;

        $this->transport->setMessageHandler(function (array $message) use (&$messageReceived) {
            $messageReceived = true;
        });

        $this->transport->setErrorHandler(function (\Throwable $error) use (&$errorReceived) {
            $errorReceived = true;
        });

        $this->transport->setCloseHandler(function () use (&$closed) {
            $closed = true;
        });

        // Handlers should be set without errors
        $this->assertTrue(true);
    }

    public function testCloseTransport(): void
    {
        $this->transport->start()->await();

        $closed = false;
        $this->transport->setCloseHandler(function () use (&$closed) {
            $closed = true;
        });

        $future = $this->transport->close();
        $result = $future->await();
        $this->assertNull($result);

        $this->assertTrue($closed);
    }

    protected function tearDown(): void
    {
        try {
            $this->transport->close()->await();
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }
}
