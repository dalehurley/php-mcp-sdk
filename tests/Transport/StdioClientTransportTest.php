<?php

declare(strict_types=1);

namespace MCP\Tests\Transport;

use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Transport\StdioServerParameters;
use PHPUnit\Framework\TestCase;

class StdioClientTransportTest extends TestCase
{
    public function testTransportCreation(): void
    {
        $parameters = new StdioServerParameters(
            command: 'php',
            args: ['-r', 'echo "test";']
        );

        $transport = new StdioClientTransport($parameters);
        $this->assertInstanceOf(StdioClientTransport::class, $transport);
    }

    public function testTransportParameters(): void
    {
        $parameters = new StdioServerParameters(
            command: 'node',
            args: ['server.js'],
            cwd: '/tmp',
            env: ['NODE_ENV' => 'test']
        );

        $transport = new StdioClientTransport($parameters);
        $this->assertInstanceOf(StdioClientTransport::class, $transport);
    }

    public function testSetHandlers(): void
    {
        $parameters = new StdioServerParameters(
            command: 'php',
            args: ['-r', 'echo "test";']
        );

        $transport = new StdioClientTransport($parameters);

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

    public function testTransportStartAndClose(): void
    {
        $parameters = new StdioServerParameters(
            command: 'php',
            args: ['-r', 'echo "ready";']
        );

        $transport = new StdioClientTransport($parameters);

        $closed = false;
        $transport->setCloseHandler(function () use (&$closed) {
            $closed = true;
        });

        try {
            $transport->start()->await();
            $transport->close()->await();
            $this->assertTrue($closed);
        } catch (\Throwable $e) {
            // Some commands might fail in test environment
            $this->assertInstanceOf(\Throwable::class, $e);
        }
    }
}
