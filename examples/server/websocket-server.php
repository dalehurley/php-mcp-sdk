#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\WebSocketServerTransport;
use MCP\Server\Transport\WebSocketServerTransportOptions;
use MCP\Types\Content\TextContent;
use MCP\Types\Tool;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * WebSocket MCP Server Example.
 *
 * This example demonstrates how to create an MCP server that communicates
 * over WebSocket connections, allowing multiple clients to connect simultaneously.
 *
 * Usage:
 *   php examples/server/websocket-server.php
 *
 * Then connect with a WebSocket client to: ws://localhost:8080
 *
 * Features demonstrated:
 * - WebSocket server transport with multiple client support
 * - Real-time bidirectional communication
 * - Tool registration and execution
 * - Connection management and heartbeat
 * - Comprehensive logging and error handling
 */

// Set up logging
$logger = new Logger('websocket-server');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

try {
    echo "🚀 Starting WebSocket MCP Server...\n";

    // Configure WebSocket transport options
    $transportOptions = new WebSocketServerTransportOptions(
        host: '127.0.0.1',
        port: 8080,
        maxConnections: 50,
        enablePing: true,
        heartbeatInterval: 30,
        allowedOrigins: [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'https://localhost:3000',
            'https://127.0.0.1:3000',
        ]
    );

    // Create WebSocket transport
    $transport = new WebSocketServerTransport($transportOptions, $logger);

    // Create MCP server
    $server = new McpServer(
        name: 'WebSocket Example Server',
        version: '1.0.0'
    );

    // Register some example tools
    $server->tool(
        'echo',
        'Echo back the provided text',
        [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => 'Text to echo back',
                ],
            ],
            'required' => ['text'],
        ],
        function (array $arguments): array {
            $text = $arguments['text'] ?? '';

            return [
                'content' => [
                    new TextContent("Echo: {$text}"),
                ],
            ];
        }
    );

    $server->tool(
        'time',
        'Get the current server time',
        [],
        function (array $arguments): array {
            return [
                'content' => [
                    new TextContent('Current server time: ' . date('Y-m-d H:i:s T')),
                ],
            ];
        }
    );

    $server->tool(
        'connections',
        'Get information about active WebSocket connections',
        [],
        function (array $arguments) use ($transport): array {
            $status = $transport->getStatus();

            return [
                'content' => [
                    new TextContent(
                        "Active connections: {$status['connections']}/{$status['maxConnections']}\n" .
                            "Server address: {$status['address']}\n" .
                            'Status: ' . ($status['started'] ? 'Running' : 'Stopped')
                    ),
                ],
            ];
        }
    );

    $server->tool(
        'math',
        'Perform basic mathematical operations',
        [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['add', 'subtract', 'multiply', 'divide'],
                    'description' => 'Mathematical operation to perform',
                ],
                'a' => [
                    'type' => 'number',
                    'description' => 'First number',
                ],
                'b' => [
                    'type' => 'number',
                    'description' => 'Second number',
                ],
            ],
            'required' => ['operation', 'a', 'b'],
        ],
        function (array $arguments): array {
            $operation = $arguments['operation'];
            $a = $arguments['a'];
            $b = $arguments['b'];

            $result = match ($operation) {
                'add' => $a + $b,
                'subtract' => $a - $b,
                'multiply' => $a * $b,
                'divide' => $b !== 0 ? $a / $b : throw new \InvalidArgumentException('Division by zero'),
                default => throw new \InvalidArgumentException("Unknown operation: {$operation}")
            };

            return [
                'content' => [
                    new TextContent("{$a} {$operation} {$b} = {$result}"),
                ],
            ];
        }
    );

    // Register a resource
    $server->resource(
        'server-info',
        'Information about this WebSocket MCP server',
        'text/plain',
        function (): string {
            global $transportOptions;

            return "WebSocket MCP Server Information\n" .
                "================================\n" .
                "Address: {$transportOptions->getAddress()}\n" .
                "Max Connections: {$transportOptions->maxConnections}\n" .
                'Heartbeat Enabled: ' . ($transportOptions->enablePing ? 'Yes' : 'No') . "\n" .
                "Heartbeat Interval: {$transportOptions->heartbeatInterval}s\n" .
                'Started: ' . date('Y-m-d H:i:s T') . "\n";
        }
    );

    // Register a prompt
    $server->prompt(
        'websocket-status',
        'Generate a status report for the WebSocket server',
        [],
        function () use ($transport): array {
            $status = $transport->getStatus();

            return [
                'description' => 'WebSocket Server Status Report',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            'type' => 'text',
                            'text' => "Generate a comprehensive status report for a WebSocket MCP server with the following information:\n\n" .
                                "- Server Address: {$status['address']}\n" .
                                "- Active Connections: {$status['connections']}\n" .
                                "- Maximum Connections: {$status['maxConnections']}\n" .
                                '- Server Status: ' . ($status['started'] ? 'Running' : 'Stopped') . "\n" .
                                '- Current Time: ' . date('Y-m-d H:i:s T') . "\n\n" .
                                'Please provide insights on performance, capacity utilization, and any recommendations.',
                        ],
                    ],
                ],
            ];
        }
    );

    echo "✅ Server configured with tools, resources, and prompts\n";
    echo "🌐 WebSocket server will listen on: {$transportOptions->getAddress()}\n";
    echo "📊 Maximum concurrent connections: {$transportOptions->maxConnections}\n";
    echo '💓 Heartbeat enabled: ' . ($transportOptions->enablePing ? 'Yes' : 'No') . "\n";

    if ($transportOptions->allowedOrigins) {
        echo '🔒 Allowed origins: ' . implode(', ', $transportOptions->allowedOrigins) . "\n";
    }

    echo "\n📝 Available tools:\n";
    echo "  - echo: Echo back provided text\n";
    echo "  - time: Get current server time\n";
    echo "  - connections: Get connection information\n";
    echo "  - math: Perform basic calculations\n";

    echo "\n📄 Available resources:\n";
    echo "  - server-info: Server information and configuration\n";

    echo "\n💬 Available prompts:\n";
    echo "  - websocket-status: Generate server status report\n";

    echo "\n🔌 Starting server...\n";

    // Connect to the transport and start the server
    $server->connect($transport);

    echo "🎉 WebSocket MCP Server started successfully!\n";
    echo "🔗 Connect your WebSocket client to: {$transportOptions->getAddress()}\n";
    echo "📋 Send MCP JSON-RPC messages to interact with the server\n";
    echo "⏹️  Press Ctrl+C to stop the server\n\n";

    // Keep the server running
    while (true) {
        \Amp\delay(1);

        // Optional: Log periodic status updates
        static $lastStatusLog = 0;
        if (time() - $lastStatusLog >= 60) { // Log every minute
            $status = $transport->getStatus();
            $logger->info("Server status: {$status['connections']} active connections");
            $lastStatusLog = time();
        }
    }
} catch (\Throwable $error) {
    echo "❌ Error: {$error->getMessage()}\n";
    echo "📍 File: {$error->getFile()}:{$error->getLine()}\n";

    if ($logger) {
        $logger->error("Server error: {$error->getMessage()}", [
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ]);
    }

    exit(1);
}
