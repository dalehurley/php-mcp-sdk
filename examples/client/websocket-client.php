<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use function Amp\delay;

use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Client\Transport\WebSocketClientTransport;
use MCP\Client\Transport\WebSocketOptions;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Implementation;
use Monolog\Handler\StreamHandler;

use Monolog\Logger;

/**
 * WebSocket client example demonstrating connection management and reconnection.
 */
function runWebSocketExample(): void
{
    // Create logger
    $logger = new Logger('websocket-client');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

    // Create client capabilities
    $capabilities = new ClientCapabilities();

    $clientInfo = new Implementation(
        name: 'WebSocket MCP Client',
        version: '1.0.0'
    );

    $options = new ClientOptions($capabilities);

    // Create client
    $client = new Client($clientInfo, $options);

    echo "WebSocket MCP Client Example\n";
    echo "============================\n\n";

    // WebSocket server URL (replace with actual MCP WebSocket server)
    $wsUrl = 'wss://api.example.com/mcp';

    // Create WebSocket options with authentication
    $wsOptions = WebSocketOptions::withAuth('your-api-token')
        ->withSubprotocols(['mcp-v1'])
        ->autoReconnect = true;

    // Alternative: create options with custom headers
    $wsOptions = new WebSocketOptions(
        headers: [
            'Authorization' => 'Bearer your-api-token',
            'User-Agent' => 'MCP-PHP-Client/1.0',
            'X-Client-Version' => '1.0.0',
        ],
        subprotocols: ['mcp-v1'],
        timeout: 30,
        autoReconnect: true,
        maxReconnectAttempts: 5,
        reconnectDelay: 1000,
        heartbeatInterval: 30
    );

    $transport = new WebSocketClientTransport($wsUrl, $wsOptions, $logger);

    // Set up event handlers
    $transport->on('connect', function () use ($logger) {
        $logger->info('WebSocket connected');
    });

    $transport->on('close', function (int $code, string $reason) use ($logger) {
        $logger->info("WebSocket closed: {$code} {$reason}");
    });

    $transport->on('message', function (array $message) use ($logger) {
        $logger->debug('Received message: ' . json_encode($message));
    });

    try {
        // Connect to WebSocket server
        echo "Connecting to WebSocket server: {$wsUrl}\n";
        $client->connect($transport)->await();
        echo "Connected successfully!\n\n";

        // Check connection status
        if ($transport->isConnected()) {
            echo "WebSocket is connected\n";
            echo "Connection URL: {$transport->getUrl()}\n";
            echo "Reconnect attempts: {$transport->getReconnectAttempts()}\n\n";
        }

        // List available tools
        echo "Listing available tools...\n";
        $toolsResult = $client->listTools()->await();

        echo "Available tools:\n";
        foreach ($toolsResult->getTools() as $tool) {
            echo "- {$tool->getName()}: {$tool->getDescription()}\n";
        }

        // Call a tool if available
        if (!empty($toolsResult->getTools())) {
            $firstTool = $toolsResult->getTools()[0];
            echo "\nCalling tool: {$firstTool->getName()}\n";

            $toolResult = $client->callToolByName($firstTool->getName(), [])->await();
            echo 'Tool result: ' . json_encode($toolResult->getContent(), JSON_PRETTY_PRINT) . "\n";
        }

        // Demonstrate connection resilience
        echo "\nTesting connection resilience...\n";
        echo "Sending ping to server...\n";

        $pingResult = $client->ping()->await();
        echo "Ping successful!\n";

        // Wait a bit to demonstrate heartbeat
        echo "Waiting 10 seconds to demonstrate heartbeat...\n";
        delay(10);

        echo 'Connection still active: ' . ($transport->isConnected() ? 'Yes' : 'No') . "\n";
    } catch (\Throwable $e) {
        echo "Error: {$e->getMessage()}\n";

        if ($e->getPrevious()) {
            echo "Caused by: {$e->getPrevious()->getMessage()}\n";
        }
    } finally {
        // Clean up
        echo "\nCleaning up...\n";
        $client->close()->await();
        echo "WebSocket client closed.\n";
    }
}

/**
 * Demonstrate WebSocket options and configuration.
 */
function demonstrateWebSocketOptions(): void
{
    echo "\nWebSocket Options Demonstration\n";
    echo "===============================\n";

    // Basic options
    $basicOptions = new WebSocketOptions();
    echo "Basic options:\n";
    echo "- Timeout: {$basicOptions->timeout}s\n";
    echo '- Auto-reconnect: ' . ($basicOptions->autoReconnect ? 'Yes' : 'No') . "\n";
    echo "- Max reconnect attempts: {$basicOptions->maxReconnectAttempts}\n";
    echo "- Heartbeat interval: {$basicOptions->heartbeatInterval}s\n\n";

    // Options with authentication
    $authOptions = WebSocketOptions::withAuth('token123', 'Bearer');
    echo 'Auth options array: ' . json_encode($authOptions->toArray(), JSON_PRETTY_PRINT) . "\n\n";

    // Options with custom headers
    $customOptions = WebSocketOptions::withHeaders([
        'X-API-Key' => 'api-key-123',
        'X-Client-ID' => 'client-456',
    ]);
    echo 'Custom headers options: ' . json_encode($customOptions->toArray(), JSON_PRETTY_PRINT) . "\n\n";

    // Development options (no SSL validation)
    $devOptions = WebSocketOptions::development();
    echo "Development options (no SSL validation):\n";
    echo '- Validate certificate: ' . ($devOptions->validateCertificate ? 'Yes' : 'No') . "\n\n";

    // Options with subprotocols
    $protocolOptions = WebSocketOptions::withSubprotocols(['mcp-v1', 'mcp-v2']);
    echo 'Subprotocol options: ' . json_encode($protocolOptions->toArray(), JSON_PRETTY_PRINT) . "\n\n";
}

/**
 * Demonstrate error handling and reconnection.
 */
function demonstrateErrorHandling(): void
{
    echo "\nError Handling Demonstration\n";
    echo "============================\n";

    $logger = new Logger('error-demo');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

    // Try to connect to an invalid WebSocket URL
    $invalidUrl = 'wss://invalid-server-that-does-not-exist.com/mcp';

    $options = new WebSocketOptions(
        timeout: 5,
        autoReconnect: true,
        maxReconnectAttempts: 2,
        reconnectDelay: 1000
    );

    $transport = new WebSocketClientTransport($invalidUrl, $options, $logger);

    $transport->on('error', function (\Throwable $error) {
        echo "WebSocket error: {$error->getMessage()}\n";
    });

    $transport->on('close', function (int $code, string $reason) {
        echo "Connection closed: {$code} {$reason}\n";
    });

    try {
        echo "Attempting to connect to invalid server...\n";
        $transport->start()->await();
    } catch (\Throwable $e) {
        echo "Expected error caught: {$e->getMessage()}\n";
        echo "Error handling working correctly!\n";
    }
}

// Run the examples
if (php_sapi_name() === 'cli') {
    echo "WebSocket Client Examples\n";
    echo "=========================\n\n";

    try {
        // Demonstrate WebSocket options
        demonstrateWebSocketOptions();

        // Demonstrate error handling
        demonstrateErrorHandling();

        // Note: The main WebSocket example is commented out because it requires
        // a real WebSocket server to connect to
        echo "Note: The main WebSocket connection example requires a real MCP WebSocket server.\n";
        echo "To run it, uncomment the line below and provide a valid WebSocket URL.\n\n";

        // runWebSocketExample();

    } catch (\Throwable $e) {
        echo "Example failed: {$e->getMessage()}\n";
        exit(1);
    }

    echo "WebSocket examples completed!\n";
} else {
    echo "This example must be run from the command line.\n";
}
