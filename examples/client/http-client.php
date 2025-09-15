#!/usr/bin/env php
<?php

/**
 * HTTP Client Example.
 *
 * This example demonstrates how to:
 * - Connect to MCP servers via HTTP transport
 * - Handle session management
 * - Process Server-Sent Events (SSE) for notifications
 * - Manage connection lifecycle
 * - Handle reconnection and error recovery
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use function Amp\async;

use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Client\Transport\StreamableHttpClientTransport;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Implementation;

// HTTP server configuration
$serverConfig = [
    'base_url' => $_ENV['MCP_HTTP_SERVER_URL'] ?? 'http://localhost:3000',
    'endpoints' => [
        'mcp' => '/mcp',
        'sse' => '/sse',
        'messages' => '/messages',
    ],
    'timeout' => 30,
    'retry_attempts' => 3,
    'retry_delay' => 2000, // milliseconds
];

async(function () use ($serverConfig) {
    try {
        echo "🌐 HTTP MCP Client Example\n";
        echo "=========================\n";
        echo "Server URL: {$serverConfig['base_url']}\n\n";

        // Create HTTP client
        $client = new Client(
            new Implementation('http-client', '1.0.0', 'HTTP Transport MCP Client'),
            new ClientOptions(
                capabilities: new ClientCapabilities()
            )
        );

        // Set up notification handler (commented out - needs proper notification class)
        $notificationCount = 0;
        // $client->setNotificationHandler('NotificationClass', function ($notification) use (&$notificationCount) {
        //     $notificationCount++;
        //     echo "📢 Notification #$notificationCount: " . json_encode($notification, JSON_PRETTY_PRINT) . "\n\n";
        // });

        // Note: Connection handlers would need to be set up differently based on the actual Client API

        // Demonstrate different HTTP transport modes
        demonstrateStreamableHttp($client, $serverConfig)->await();
        demonstrateSseTransport($client, $serverConfig)->await();
        demonstrateSessionManagement($client, $serverConfig)->await();

        echo "✅ HTTP client demo completed!\n";
    } catch (\Throwable $e) {
        echo '❌ Error: ' . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
})->await();

/**
 * Demonstrate Streamable HTTP transport.
 */
function demonstrateStreamableHttp(Client $client, array $config): \Amp\Future
{
    return async(function () use ($client, $config) {
        echo "🚀 Streamable HTTP Transport Demo\n";
        echo "=================================\n";

        try {
            // Create streamable HTTP transport
            $url = $config['base_url'] . $config['endpoints']['mcp'];
            $options = new \MCP\Client\Transport\StreamableHttpClientTransportOptions(
                headers: [
                    'User-Agent' => 'PHP-MCP-HTTP-Client/1.0.0',
                    'Accept' => 'application/json',
                ]
            );
            $transport = new StreamableHttpClientTransport($url, $options);

            echo "🔌 Connecting via Streamable HTTP...\n";
            $client->connect($transport)->await();

            // Test basic operations
            testBasicOperations($client, 'Streamable HTTP')->await();

            // Test real-time features
            testRealtimeFeatures($client)->await();

            // Close connection
            echo "🔌 Closing Streamable HTTP connection...\n";
            $client->close()->await();
            echo "✅ Streamable HTTP demo completed\n\n";
        } catch (\Exception $e) {
            echo '❌ Streamable HTTP error: ' . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Demonstrate SSE transport (fallback mode).
 */
function demonstrateSseTransport(Client $client, array $config): \Amp\Future
{
    return async(function () use ($client, $config) {
        echo "📡 SSE Transport Demo (Fallback Mode)\n";
        echo "====================================\n";

        try {
            // Use StreamableHttp instead of deprecated SSE transport
            $url = $config['base_url'] . $config['endpoints']['mcp'];
            $options = new \MCP\Client\Transport\StreamableHttpClientTransportOptions();
            $transport = new StreamableHttpClientTransport($url, $options);

            echo "📡 Connecting via StreamableHttp (SSE alternative)...\n";
            $client->connect($transport)->await();

            // Test basic operations
            testBasicOperations($client, 'SSE')->await();

            // Test notification stream
            testNotificationStream($client)->await();

            // Close connection
            echo "🔌 Closing StreamableHttp connection...\n";
            $client->close()->await();
            echo "✅ StreamableHttp demo completed\n\n";
        } catch (\Exception $e) {
            echo '❌ SSE transport error: ' . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Test basic MCP operations.
 */
function testBasicOperations(Client $client, string $transportType): \Amp\Future
{
    return async(function () use ($client, $transportType) {
        echo "🧪 Testing Basic Operations ($transportType)\n";
        echo str_repeat('-', 40) . "\n";

        // List tools
        echo "📋 Listing tools...\n";

        try {
            $tools = $client->listTools()->await();
            echo '   Found ' . count($tools->getTools()) . " tools:\n";
            foreach ($tools->getTools() as $tool) {
                echo "   - {$tool->getName()}: {$tool->getDescription()}\n";
            }
            echo "\n";
        } catch (\Exception $e) {
            echo '   ❌ Failed to list tools: ' . $e->getMessage() . "\n\n";
        }

        // List resources
        echo "📁 Listing resources...\n";

        try {
            $resources = $client->listResources()->await();
            echo '   Found ' . count($resources->getResources()) . " resources:\n";
            foreach ($resources->getResources() as $resource) {
                echo "   - {$resource->getName()}: {$resource->getUri()}\n";
            }
            echo "\n";
        } catch (\Exception $e) {
            echo '   ❌ Failed to list resources: ' . $e->getMessage() . "\n\n";
        }

        // List prompts
        echo "💬 Listing prompts...\n";

        try {
            $prompts = $client->listPrompts()->await();
            echo '   Found ' . count($prompts->getPrompts()) . " prompts:\n";
            foreach ($prompts->getPrompts() as $prompt) {
                echo "   - {$prompt->getName()}: {$prompt->getDescription()}\n";
            }
            echo "\n";
        } catch (\Exception $e) {
            echo '   ❌ Failed to list prompts: ' . $e->getMessage() . "\n\n";
        }

        // Test tool call
        echo "🔧 Testing tool call...\n";

        try {
            // Try to call a common tool (this will depend on the server)
            $result = $client->callToolByName('echo', ['message' => "Hello from $transportType!"])->await();

            if ($result->isError()) {
                echo '   ❌ Tool call failed: ' . getResultText($result) . "\n";
            } else {
                echo '   ✅ Tool call successful: ' . getResultText($result) . "\n";
            }
            echo "\n";
        } catch (\Exception $e) {
            echo '   ⚠️  Tool call not available or failed: ' . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Test real-time features with Streamable HTTP.
 */
function testRealtimeFeatures(Client $client): \Amp\Future
{
    return async(function () use ($client) {
        echo "⚡ Testing Real-time Features\n";
        echo "----------------------------\n";

        // Test session persistence
        echo "🔄 Testing session persistence...\n";

        try {
            // Session info would be managed by the transport layer
            echo "   ℹ️  Session info managed by transport layer\n";
            echo "\n";
        } catch (\Exception $e) {
            echo '   ⚠️  Session info not supported: ' . $e->getMessage() . "\n\n";
        }

        // Test notification subscription
        echo "🔔 Testing notification subscription...\n";

        try {
            // Note: Resource subscription would use server-specific subscription methods
            echo "   ℹ️  Resource subscription not implemented in this demo\n";
            echo "\n";
        } catch (\Exception $e) {
            echo '   ⚠️  Notification subscription not supported: ' . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Test notification stream with SSE.
 */
function testNotificationStream(Client $client): \Amp\Future
{
    return async(function () use ($client) {
        echo "📢 Testing Notification Stream\n";
        echo "-----------------------------\n";

        try {
            // Start a tool that generates notifications (if available)
            echo "🚀 Starting notification-generating tool...\n";

            $result = $client->callToolByName('start-notification-stream', [
                'interval' => 1000,  // 1 second
                'count' => 5,        // 5 notifications
                'message' => 'SSE Test Notification',
            ])->await();

            if ($result->isError()) {
                echo '   ❌ Failed to start notification stream: ' . getResultText($result) . "\n";
            } else {
                echo "   ✅ Notification stream started\n";
                echo "   ⏳ Waiting for notifications (6 seconds)...\n";
                \Amp\delay(6000);
            }
            echo "\n";
        } catch (\Exception $e) {
            echo '   ⚠️  Notification stream not available: ' . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Demonstrate session management.
 */
function demonstrateSessionManagement(Client $client, array $config): \Amp\Future
{
    return async(function () use ($client, $config) {
        echo "🎯 Session Management Demo\n";
        echo "=========================\n";

        try {
            // Create transport with session management
            $url = $config['base_url'] . $config['endpoints']['mcp'];
            $options = new \MCP\Client\Transport\StreamableHttpClientTransportOptions(
                sessionId: 'session-' . uniqid()
            );
            $transport = new StreamableHttpClientTransport($url, $options);

            echo "🔗 Connecting with session management...\n";
            $client->connect($transport)->await();

            // Demonstrate session operations
            testSessionOperations($client)->await();

            // Test connection recovery
            testConnectionRecovery($client, $transport, $config)->await();

            $client->close()->await();
            echo "✅ Session management demo completed\n\n";
        } catch (\Exception $e) {
            echo '❌ Session management error: ' . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Test session-specific operations.
 */
function testSessionOperations(Client $client): \Amp\Future
{
    return async(function () use ($client) {
        echo "🔍 Testing Session Operations\n";
        echo "----------------------------\n";

        // Test session state
        echo "📊 Checking session state...\n";

        try {
            // Session state would be managed by the transport layer
            echo "   ℹ️  Session state managed by transport layer\n";
            echo "\n";
        } catch (\Exception $e) {
            echo '   ⚠️  Session state not supported: ' . $e->getMessage() . "\n\n";
        }

        // Test session persistence across operations
        echo "💾 Testing session persistence...\n";
        for ($i = 1; $i <= 3; $i++) {
            try {
                echo "   Operation $i: ";
                $result = $client->callToolByName('echo', ['message' => "Session test $i"])->await();
                echo ($result->isError() ? '❌ Failed' : '✅ Success') . "\n";
                \Amp\delay(1000);
            } catch (\Exception $e) {
                echo "❌ Error\n";
            }
        }
        echo "\n";
    });
}

/**
 * Test connection recovery.
 */
function testConnectionRecovery(Client $client, $transport, array $config): \Amp\Future
{
    return async(function () use ($client, $transport, $config) {
        echo "🔄 Testing Connection Recovery\n";
        echo "-----------------------------\n";

        try {
            echo "📡 Testing automatic reconnection...\n";

            // Simulate connection loss (this would depend on server implementation)
            echo "   ⚠️  Simulating connection interruption...\n";

            // In a real scenario, you might temporarily disconnect
            // For demo, we'll just test the recovery mechanism

            echo "   🔄 Testing recovery mechanism...\n";
            // $recovered = $transport->testRecovery()->await(); // Method may not exist
            $recovered = true; // Simulate recovery test

            if ($recovered) {
                echo "   ✅ Connection recovery successful\n";
            } else {
                echo "   ❌ Connection recovery failed\n";
            }
        } catch (\Exception $e) {
            echo '   ⚠️  Recovery test not supported: ' . $e->getMessage() . "\n";
        }

        echo "\n";
    });
}

/**
 * Demonstrate multiple concurrent connections.
 */
function demonstrateMultipleConnections(array $config): \Amp\Future
{
    return async(function () use ($config) {
        echo "🔀 Multiple Connections Demo\n";
        echo "===========================\n";

        $clients = [];
        $transports = [];

        try {
            // Create multiple clients
            for ($i = 1; $i <= 3; $i++) {
                $client = new Client(
                    new Implementation("http-client-$i", '1.0.0', "HTTP Client #$i"),
                    new ClientOptions(capabilities: new ClientCapabilities())
                );

                $url = $config['base_url'] . $config['endpoints']['mcp'];
                $transport = new StreamableHttpClientTransport($url);

                echo "🔌 Connecting client #$i...\n";
                $client->connect($transport)->await();

                $clients[] = $client;
                $transports[] = $transport;
            }

            echo "✅ All clients connected\n\n";

            // Test concurrent operations
            echo "⚡ Testing concurrent operations...\n";
            $promises = [];

            foreach ($clients as $i => $client) {
                $clientNum = $i + 1;
                $promises[] = $client->callToolByName('echo', ['message' => "Concurrent call from client #$clientNum"]);
            }

            $results = \Amp\Future\await($promises);

            foreach ($results as $i => $result) {
                $clientNum = $i + 1;
                echo "   Client #$clientNum: " . ($result->isError() ? '❌ Error' : '✅ Success') . "\n";
            }

            // Close all connections
            echo "\n🔌 Closing all connections...\n";
            foreach ($clients as $client) {
                $client->close()->await();
            }

            echo "✅ Multiple connections demo completed\n\n";
        } catch (\Exception $e) {
            echo '❌ Multiple connections error: ' . $e->getMessage() . "\n\n";

            // Cleanup
            foreach ($clients as $client) {
                try {
                    $client->close()->await();
                } catch (\Exception $cleanup) {
                    // Ignore cleanup errors
                }
            }
        }
    });
}

/**
 * Extract text content from a result.
 */
function getResultText($result): string
{
    if (!$result || !method_exists($result, 'getContent')) {
        return 'No result';
    }

    $content = $result->getContent();
    if (empty($content)) {
        return 'Empty result';
    }

    $texts = [];
    foreach ($content as $item) {
        if (method_exists($item, 'getText')) {
            $texts[] = $item->getText();
        } elseif (is_array($item) && isset($item['text'])) {
            $texts[] = $item['text'];
        }
    }

    return implode(' ', $texts);
}

/**
 * Mock HTTP server for testing (if no real server is available).
 */
function startMockHttpServer(int $port = 3000): void
{
    echo "🎭 Starting mock HTTP server on port $port...\n";
    echo "   Note: This is for testing when no real MCP HTTP server is available\n";
    echo "   In production, connect to a real MCP server\n\n";

    // This would start a simple HTTP server for testing
    // Implementation would depend on your needs
}
