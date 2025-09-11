<?php

/**
 * Transport Test Example
 * 
 * This example tests the stdio transport implementations directly
 * without the full client/server classes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Server\Transport\StdioServerTransport;
use function Amp\async;
use function Amp\delay;

// Simple echo server using StdioServerTransport
async(function () {
    $transport = new StdioServerTransport();
    
    // Set up message handler
    $transport->setMessageHandler(function (array $message) use ($transport) {
        echo "Server received: " . json_encode($message) . "\n";
        
        // Echo the message back with a "echo_" prefix
        if (isset($message['method'])) {
            $response = [
                'jsonrpc' => '2.0',
                'id' => $message['id'] ?? null,
                'result' => [
                    'echo' => $message,
                    'timestamp' => time()
                ]
            ];
            
            $transport->send($response)->await();
        }
    });
    
    $transport->setErrorHandler(function (\Throwable $error) {
        error_log("Transport error: " . $error->getMessage());
    });
    
    $transport->setCloseHandler(function () {
        echo "Transport closed\n";
    });
    
    echo "Starting stdio echo server...\n";
    echo "Type JSON-RPC messages and press Enter. Type 'exit' to quit.\n";
    echo "Example: {\"jsonrpc\":\"2.0\",\"method\":\"test\",\"params\":{\"message\":\"hello\"},\"id\":1}\n\n";
    
    // Start the transport
    $transport->start()->await();
    
    // Keep the server running
    while (true) {
        delay(0.1);
    }
})->await();
