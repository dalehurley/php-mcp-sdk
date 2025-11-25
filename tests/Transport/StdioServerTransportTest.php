<?php

declare(strict_types=1);

namespace MCP\Tests\Transport;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\WritableBuffer;
use Amp\Future;
use MCP\Server\Transport\StdioServerTransport;
use PHPUnit\Framework\TestCase;

class StdioServerTransportTest extends TestCase
{
    private StdioServerTransport $transport;

    private ReadableBuffer $mockStdin;

    private WritableBuffer $mockStdout;

    protected function setUp(): void
    {
        // Use mock streams to avoid blocking on real stdin/stdout
        $this->mockStdin = new ReadableBuffer('');
        $this->mockStdout = new WritableBuffer();
        $this->transport = new StdioServerTransport($this->mockStdin, $this->mockStdout);
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

        // Give event loop time to process
        \Amp\delay(0.001);

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
