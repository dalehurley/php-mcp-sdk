<?php

declare(strict_types=1);

namespace MCP\Tests\Transport;

use PHPUnit\Framework\TestCase;
use MCP\Server\Transport\StdioServerTransport;
use Amp\Future;

class StdioServerTransportTest extends TestCase
{
    private StdioServerTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new StdioServerTransport();
    }

    public function testTransportCreation(): void
    {
        $this->assertInstanceOf(StdioServerTransport::class, $this->transport);
    }

    public function testTransportStart(): void
    {
        $future = $this->transport->start();
        $this->assertInstanceOf(Future::class, $future);

        // For stdio transport, start() should complete immediately
        $result = $future->await();
        $this->assertNull($result);
    }

    public function testCloseTransport(): void
    {
        // Start the transport first
        $this->transport->start()->await();

        $future = $this->transport->close();
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

    protected function tearDown(): void
    {
        // Ensure transport is closed
        try {
            $this->transport->close()->await();
        } catch (\Throwable $e) {
            // Ignore errors during cleanup
        }
    }
}
