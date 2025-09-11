<?php

/**
 * Client Transport Test Example
 * 
 * This example tests the StdioClientTransport by connecting to the echo server
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Transport\StdioServerParameters;
use function Amp\async;
use function Amp\delay;

async(function () {
    // Configure server parameters
    $serverParams = new StdioServerParameters(
        command: 'php',
        args: [__DIR__ . '/transport-test.php'],
        cwd: __DIR__
    );

    // Create client transport
    $transport = new StdioClientTransport($serverParams);

    // Set up handlers
    $transport->setMessageHandler(function (array $message) {
        echo "Client received response: " . json_encode($message, JSON_PRETTY_PRINT) . "\n\n";
    });

    $transport->setErrorHandler(function (\Throwable $error) {
        error_log("Client transport error: " . $error->getMessage());
    });

    $transport->setCloseHandler(function () {
        echo "Server closed connection\n";
        exit(0);
    });

    echo "Starting client transport...\n";

    try {
        // Start the transport (spawns the server process)
        $transport->start()->await();

        echo "Connected to server (PID: " . $transport->getPid() . ")\n\n";

        // Send some test messages
        $messages = [
            [
                'jsonrpc' => '2.0',
                'method' => 'hello',
                'params' => ['name' => 'World'],
                'id' => 1
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'calculate',
                'params' => ['expression' => '2 + 2'],
                'id' => 2
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'ping',
                'id' => 3
            ]
        ];

        foreach ($messages as $message) {
            echo "Sending: " . json_encode($message) . "\n";
            $transport->send($message)->await();

            // Wait a bit between messages
            delay(1);
        }

        // Keep running for a bit to receive responses
        echo "\nWaiting for responses...\n";
        delay(3);

        // Close the transport
        echo "\nClosing connection...\n";
        $transport->close()->await();

        echo "Done!\n";
    } catch (\Throwable $e) {
        error_log("Error: " . $e->getMessage());
        error_log($e->getTraceAsString());

        // Try to close transport on error
        try {
            $transport->close()->await();
        } catch (\Throwable $closeError) {
            // Ignore close errors
        }

        exit(1);
    }
})->await();
