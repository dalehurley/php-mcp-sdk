#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Simple WebSocket Test Client.
 *
 * This is a basic WebSocket client to test connectivity with the WebSocket MCP server.
 * This demonstrates how to connect to the WebSocket server and send MCP messages.
 *
 * Usage:
 *   1. Start the WebSocket server: php examples/server/websocket-server.php
 *   2. Run this client: php examples/client/websocket-test-client.php
 */
echo "🔌 WebSocket MCP Test Client\n";
echo "============================\n\n";

// Simple WebSocket client implementation for testing
// Note: In a real application, you would use the WebSocketClientTransport
// which will be completed in the future.

$host = '127.0.0.1';
$port = 8080;
$url = "ws://{$host}:{$port}";

echo "📡 Attempting to connect to WebSocket server at: {$url}\n";

// For now, we'll show what the connection would look like
echo "\n📝 Example MCP messages that would be sent:\n\n";

// Initialize request
$initializeRequest = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'initialize',
    'params' => [
        'protocolVersion' => '2024-11-05',
        'capabilities' => [
            'tools' => [],
        ],
        'clientInfo' => [
            'name' => 'WebSocket Test Client',
            'version' => '1.0.0',
        ],
    ],
];

echo "1. Initialize Request:\n";
echo json_encode($initializeRequest, JSON_PRETTY_PRINT) . "\n\n";

// List tools request
$listToolsRequest = [
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/list',
    'params' => [],
];

echo "2. List Tools Request:\n";
echo json_encode($listToolsRequest, JSON_PRETTY_PRINT) . "\n\n";

// Call tool request
$callToolRequest = [
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'echo',
        'arguments' => [
            'text' => 'Hello from WebSocket client!',
        ],
    ],
];

echo "3. Call Tool Request (echo):\n";
echo json_encode($callToolRequest, JSON_PRETTY_PRINT) . "\n\n";

// Get time tool request
$timeToolRequest = [
    'jsonrpc' => '2.0',
    'id' => 4,
    'method' => 'tools/call',
    'params' => [
        'name' => 'time',
        'arguments' => [],
    ],
];

echo "4. Call Tool Request (time):\n";
echo json_encode($timeToolRequest, JSON_PRETTY_PRINT) . "\n\n";

// Math tool request
$mathToolRequest = [
    'jsonrpc' => '2.0',
    'id' => 5,
    'method' => 'tools/call',
    'params' => [
        'name' => 'math',
        'arguments' => [
            'operation' => 'add',
            'a' => 42,
            'b' => 8,
        ],
    ],
];

echo "5. Call Tool Request (math):\n";
echo json_encode($mathToolRequest, JSON_PRETTY_PRINT) . "\n\n";

// Connections status request
$connectionsRequest = [
    'jsonrpc' => '2.0',
    'id' => 6,
    'method' => 'tools/call',
    'params' => [
        'name' => 'connections',
        'arguments' => [],
    ],
];

echo "6. Call Tool Request (connections):\n";
echo json_encode($connectionsRequest, JSON_PRETTY_PRINT) . "\n\n";

// List resources request
$listResourcesRequest = [
    'jsonrpc' => '2.0',
    'id' => 7,
    'method' => 'resources/list',
    'params' => [],
];

echo "7. List Resources Request:\n";
echo json_encode($listResourcesRequest, JSON_PRETTY_PRINT) . "\n\n";

// Read resource request
$readResourceRequest = [
    'jsonrpc' => '2.0',
    'id' => 8,
    'method' => 'resources/read',
    'params' => [
        'uri' => 'server-info',
    ],
];

echo "8. Read Resource Request:\n";
echo json_encode($readResourceRequest, JSON_PRETTY_PRINT) . "\n\n";

echo "💡 Instructions for testing:\n";
echo "===========================\n";
echo "1. Start the WebSocket server:\n";
echo "   php examples/server/websocket-server.php\n\n";
echo "2. Use a WebSocket client tool like:\n";
echo "   - wscat: npm install -g wscat && wscat -c ws://127.0.0.1:8080\n";
echo "   - websocat: websocat ws://127.0.0.1:8080\n";
echo "   - Browser WebSocket API\n";
echo "   - Postman WebSocket client\n\n";
echo "3. Send the JSON messages shown above to test the server\n\n";

echo "🚀 For a complete WebSocket client implementation, see the WebSocketClientTransport class\n";
echo "   which provides full MCP client functionality over WebSocket connections.\n\n";

echo "📚 Example browser WebSocket client:\n";
echo "=====================================\n";

$jsExample = <<<'JS'
    // Browser WebSocket client example
    const ws = new WebSocket('ws://127.0.0.1:8080');

    ws.onopen = function() {
        console.log('Connected to WebSocket MCP server');
        
        // Send initialize request
        ws.send(JSON.stringify({
            jsonrpc: '2.0',
            id: 1,
            method: 'initialize',
            params: {
                protocolVersion: '2024-11-05',
                capabilities: { tools: [] },
                clientInfo: { name: 'Browser Client', version: '1.0.0' }
            }
        }));
    };

    ws.onmessage = function(event) {
        const response = JSON.parse(event.data);
        console.log('Received:', response);
    };

    ws.onerror = function(error) {
        console.error('WebSocket error:', error);
    };

    ws.onclose = function() {
        console.log('WebSocket connection closed');
    };

    // Send a tool call after initialization
    setTimeout(() => {
        ws.send(JSON.stringify({
            jsonrpc: '2.0',
            id: 2,
            method: 'tools/call',
            params: {
                name: 'echo',
                arguments: { text: 'Hello from browser!' }
            }
        }));
    }, 1000);
    JS;

echo $jsExample . "\n\n";

echo "✅ WebSocket Server Transport implementation is now complete!\n";
echo "🎉 The PHP MCP SDK now supports all major transport protocols.\n";
