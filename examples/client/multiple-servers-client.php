#!/usr/bin/env php
<?php

/**
 * Multiple Servers Client Example
 * 
 * This example demonstrates how to:
 * - Connect to multiple MCP servers simultaneously
 * - Coordinate operations across different servers
 * - Handle different transport types
 * - Aggregate results from multiple sources
 * - Manage connection lifecycle for multiple servers
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load required files to ensure all classes are available
require_once __DIR__ . '/../../src/Shared/Protocol.php';
require_once __DIR__ . '/../../src/Client/ClientOptions.php';
require_once __DIR__ . '/../../src/Client/Client.php';
require_once __DIR__ . '/../../src/Client/Transport/StdioClientTransport.php';

use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Transport\StdioServerParameters;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ClientCapabilities;
use function Amp\async;
use function Amp\Future\await;

// Server configurations
$serverConfigs = [
    'calculator' => [
        'name' => 'Calculator Server',
        'transport' => 'stdio',
        'command' => 'php',
        'args' => [__DIR__ . '/../server/simple-server.php'],
        'description' => 'Mathematical calculations and basic operations'
    ],
    'weather' => [
        'name' => 'Weather Server',
        'transport' => 'stdio',
        'command' => 'php',
        'args' => [__DIR__ . '/../server/weather-server.php'],
        'description' => 'Weather information and forecasts'
    ],
    'database' => [
        'name' => 'Database Server',
        'transport' => 'stdio',
        'command' => 'php',
        'args' => [__DIR__ . '/../server/sqlite-server.php'],
        'description' => 'Database queries and management'
    ],
    'resources' => [
        'name' => 'Resource Server',
        'transport' => 'stdio',
        'command' => 'php',
        'args' => [__DIR__ . '/../server/resource-server.php'],
        'description' => 'File and resource management'
    ]
];

async(function () use ($serverConfigs) {
    try {
        echo "🌐 Multiple Servers MCP Client\n";
        echo "=============================\n";
        echo "Connecting to " . count($serverConfigs) . " servers simultaneously...\n\n";

        // Demonstrate sequential connections
        demonstrateSequentialConnections($serverConfigs)->await();

        // Demonstrate parallel connections
        demonstrateParallelConnections($serverConfigs)->await();

        // Demonstrate cross-server operations
        demonstrateCrossServerOperations($serverConfigs)->await();

        echo "✅ Multiple servers demo completed!\n";
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
})->await();

/**
 * Demonstrate connecting to servers sequentially
 */
function demonstrateSequentialConnections(array $serverConfigs): \Amp\Future
{
    return async(function () use ($serverConfigs) {
        echo "📋 Sequential Server Connections\n";
        echo "===============================\n";

        $clients = [];
        $connectionResults = [];

        foreach ($serverConfigs as $serverId => $config) {
            echo "🔌 Connecting to {$config['name']}...\n";

            try {
                $startTime = microtime(true);

                // Create client
                $client = new Client(
                    new Implementation("multi-client-$serverId", '1.0.0', "Client for {$config['name']}"),
                    new ClientOptions(capabilities: new ClientCapabilities())
                );

                // Create transport
                $serverParams = new StdioServerParameters(
                    command: $config['command'],
                    args: $config['args'],
                    cwd: dirname(__DIR__, 2)
                );

                $transport = new StdioClientTransport($serverParams);

                // Connect
                $client->connect($transport)->await();

                $connectionTime = microtime(true) - $startTime;

                // Get server info
                $serverInfo = $client->getServerVersion();

                $clients[$serverId] = $client;
                $connectionResults[$serverId] = [
                    'success' => true,
                    'time' => $connectionTime,
                    'server_info' => $serverInfo
                ];

                echo "  ✅ Connected in " . round($connectionTime * 1000, 2) . "ms\n";
                echo "  📋 Server: {$serverInfo['name']} v{$serverInfo['version']}\n";
            } catch (\Exception $e) {
                echo "  ❌ Failed: " . $e->getMessage() . "\n";
                $connectionResults[$serverId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            echo "\n";
        }

        // Test basic operations on each server
        testBasicOperationsOnServers($clients)->await();

        // Close all connections
        echo "🔌 Closing sequential connections...\n";
        foreach ($clients as $serverId => $client) {
            try {
                $client->close()->await();
                echo "  ✅ Closed connection to " . $serverConfigs[$serverId]['name'] . "\n";
            } catch (\Exception $e) {
                echo "  ⚠️  Error closing {$serverConfigs[$serverId]['name']}: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";
    });
}

/**
 * Demonstrate connecting to servers in parallel
 */
function demonstrateParallelConnections(array $serverConfigs): \Amp\Future
{
    return async(function () use ($serverConfigs) {
        echo "⚡ Parallel Server Connections\n";
        echo "=============================\n";

        $connectionPromises = [];
        $clients = [];

        echo "🚀 Starting parallel connections...\n";
        $overallStart = microtime(true);

        // Create connection promises for all servers
        foreach ($serverConfigs as $serverId => $config) {
            $connectionPromises[$serverId] = async(function () use ($serverId, $config) {
                $startTime = microtime(true);

                try {
                    // Create client
                    $client = new Client(
                        new Implementation("parallel-client-$serverId", '1.0.0', "Parallel Client for {$config['name']}"),
                        new ClientOptions(capabilities: new ClientCapabilities())
                    );

                    // Create transport
                    $serverParams = new StdioServerParameters(
                        command: $config['command'],
                        args: $config['args'],
                        cwd: dirname(__DIR__, 2)
                    );

                    $transport = new StdioClientTransport($serverParams);

                    // Connect
                    $client->connect($transport)->await();

                    $connectionTime = microtime(true) - $startTime;

                    return [
                        'client' => $client,
                        'success' => true,
                        'time' => $connectionTime,
                        'server_info' => $client->getServerVersion()
                    ];
                } catch (\Exception $e) {
                    return [
                        'client' => null,
                        'success' => false,
                        'time' => microtime(true) - $startTime,
                        'error' => $e->getMessage()
                    ];
                }
            });
        }

        // Wait for all connections to complete
        $results = await($connectionPromises);
        $totalTime = microtime(true) - $overallStart;

        echo "⏱️  Total parallel connection time: " . round($totalTime * 1000, 2) . "ms\n\n";

        // Process results
        $successfulClients = [];
        foreach ($results as $serverId => $result) {
            $config = $serverConfigs[$serverId];

            if ($result['success']) {
                echo "✅ {$config['name']}: Connected in " . round($result['time'] * 1000, 2) . "ms\n";
                echo "   Server: {$result['server_info']['name']} v{$result['server_info']['version']}\n";
                $successfulClients[$serverId] = $result['client'];
            } else {
                echo "❌ {$config['name']}: Failed in " . round($result['time'] * 1000, 2) . "ms - {$result['error']}\n";
            }
        }
        echo "\n";

        // Demonstrate parallel operations
        demonstrateParallelOperations($successfulClients, $serverConfigs)->await();

        // Close all connections
        echo "🔌 Closing parallel connections...\n";
        $closePromises = [];
        foreach ($successfulClients as $serverId => $client) {
            $closePromises[] = async(function () use ($client, $serverId, $serverConfigs) {
                try {
                    $client->close()->await();
                    return "✅ Closed {$serverConfigs[$serverId]['name']}";
                } catch (\Exception $e) {
                    return "⚠️  Error closing {$serverConfigs[$serverId]['name']}: " . $e->getMessage();
                }
            });
        }

        $closeResults = await($closePromises);
        foreach ($closeResults as $result) {
            echo "  $result\n";
        }
        echo "\n";
    });
}

/**
 * Test basic operations on each server
 */
function testBasicOperationsOnServers(array $clients): \Amp\Future
{
    return async(function () use ($clients) {
        echo "🧪 Testing Basic Operations\n";
        echo "===========================\n";

        foreach ($clients as $serverId => $client) {
            echo "📋 Testing $serverId server:\n";

            try {
                // List tools
                $tools = $client->listTools()->await();
                echo "  🔧 Tools: " . count($tools->getTools()) . " available\n";

                // List resources
                $resources = $client->listResources()->await();
                echo "  📁 Resources: " . count($resources->getResources()) . " available\n";

                // List prompts
                $prompts = $client->listPrompts()->await();
                echo "  💬 Prompts: " . count($prompts->getPrompts()) . " available\n";
            } catch (\Exception $e) {
                echo "  ❌ Error testing $serverId: " . $e->getMessage() . "\n";
            }

            echo "\n";
        }
    });
}

/**
 * Demonstrate parallel operations across servers
 */
function demonstrateParallelOperations(array $clients, array $serverConfigs): \Amp\Future
{
    return async(function () use ($clients, $serverConfigs) {
        echo "⚡ Parallel Operations Across Servers\n";
        echo "===================================\n";

        // Define operations for each server
        $operations = [
            'calculator' => [
                'tool' => 'calculate',
                'params' => ['expression' => '(10 + 5) * 2'],
                'description' => 'Mathematical calculation'
            ],
            'weather' => [
                'tool' => 'cache-status',
                'params' => [],
                'description' => 'Weather cache status'
            ],
            'database' => [
                'tool' => 'table-stats',
                'params' => [],
                'description' => 'Database statistics'
            ],
            'resources' => [
                'tool' => 'create-resource',
                'params' => [
                    'id' => 'parallel-test-' . time(),
                    'title' => 'Parallel Test Resource',
                    'data' => ['created_by' => 'parallel-client', 'timestamp' => time()]
                ],
                'description' => 'Create test resource'
            ]
        ];

        echo "🚀 Starting parallel operations...\n";
        $startTime = microtime(true);

        $operationPromises = [];
        foreach ($operations as $serverId => $operation) {
            if (isset($clients[$serverId])) {
                echo "  Starting: {$operation['description']} on {$serverConfigs[$serverId]['name']}\n";

                $operationPromises[$serverId] = async(function () use ($clients, $serverId, $operation) {
                    try {
                        $result = $clients[$serverId]->callToolByName($operation['tool'], $operation['params'])->await();
                        $success = !$result->isError();
                        return [
                            'success' => $success,
                            'result' => $result,
                            'error' => $success ? null : getResultText($result)
                        ];
                    } catch (\Exception $e) {
                        return [
                            'success' => false,
                            'result' => null,
                            'error' => $e->getMessage()
                        ];
                    }
                });
            }
        }

        // Wait for all operations to complete
        $operationResults = await($operationPromises);
        $totalTime = microtime(true) - $startTime;

        echo "⏱️  Total operation time: " . round($totalTime * 1000, 2) . "ms\n\n";

        // Display results
        echo "📊 Operation Results:\n";
        foreach ($operationResults as $serverId => $result) {
            $serverName = $serverConfigs[$serverId]['name'];
            $operation = $operations[$serverId];

            echo "  🔧 {$operation['description']} ({$serverName}):\n";

            if ($result['success']) {
                echo "    ✅ Success\n";
                $resultText = getResultText($result['result']);
                // Show first line of result
                $firstLine = explode("\n", $resultText)[0] ?? $resultText;
                if (strlen($firstLine) > 80) {
                    $firstLine = substr($firstLine, 0, 77) . '...';
                }
                echo "    📋 Result: $firstLine\n";
            } else {
                echo "    ❌ Failed: {$result['error']}\n";
            }
            echo "\n";
        }
    });
}

/**
 * Demonstrate cross-server operations
 */
function demonstrateCrossServerOperations(array $serverConfigs): \Amp\Future
{
    return async(function () use ($serverConfigs) {
        echo "🔀 Cross-Server Operations Demo\n";
        echo "==============================\n";

        try {
            // Connect to specific servers for cross-server demo
            $clients = [];

            // Connect to calculator and database servers
            $serversToConnect = ['calculator', 'database'];

            foreach ($serversToConnect as $serverId) {
                if (!isset($serverConfigs[$serverId])) continue;

                $config = $serverConfigs[$serverId];
                echo "🔌 Connecting to {$config['name']} for cross-server demo...\n";

                $client = new Client(
                    new Implementation("cross-server-$serverId", '1.0.0', "Cross-server client for {$config['name']}"),
                    new ClientOptions(capabilities: new ClientCapabilities())
                );

                $serverParams = new StdioServerParameters(
                    command: $config['command'],
                    args: $config['args'],
                    cwd: dirname(__DIR__, 2)
                );

                $transport = new StdioClientTransport($serverParams);
                $client->connect($transport)->await();

                $clients[$serverId] = $client;
                echo "  ✅ Connected\n";
            }
            echo "\n";

            if (count($clients) >= 2) {
                echo "🧮 Cross-server calculation and storage example:\n";

                // Step 1: Perform calculation
                if (isset($clients['calculator'])) {
                    echo "  1. Calculating result...\n";
                    $calcResult = $clients['calculator']->callToolByName('calculate', [
                        'expression' => '(25 + 15) * 3'
                    ])->await();

                    if (!$calcResult->isError()) {
                        $resultText = getResultText($calcResult);
                        echo "     ✅ Calculation: $resultText\n";

                        // Step 2: Store result in database (if we had a storage tool)
                        if (isset($clients['database'])) {
                            echo "  2. Querying database for comparison...\n";
                            $dbResult = $clients['database']->callToolByName('query-select', [
                                'query' => 'SELECT COUNT(*) as total_records FROM users'
                            ])->await();

                            if (!$dbResult->isError()) {
                                echo "     ✅ Database query completed\n";
                                echo "     📊 Cross-server operation: Combined calculation and database query\n";
                            } else {
                                echo "     ❌ Database query failed\n";
                            }
                        }
                    } else {
                        echo "     ❌ Calculation failed\n";
                    }
                }
            } else {
                echo "⚠️  Need at least 2 servers for cross-server demo\n";
            }

            // Close connections
            foreach ($clients as $serverId => $client) {
                $client->close()->await();
            }

            echo "\n✅ Cross-server operations demo completed\n\n";
        } catch (\Exception $e) {
            echo "❌ Cross-server operations error: " . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Extract text content from a result
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
 * Monitor connection health across multiple servers
 */
function monitorConnectionHealth(array $clients, array $serverConfigs): \Amp\Future
{
    return async(function () use ($clients, $serverConfigs) {
        echo "💓 Connection Health Monitoring\n";
        echo "==============================\n";

        $healthChecks = [];

        foreach ($clients as $serverId => $client) {
            $healthChecks[] = async(function () use ($client, $serverId, $serverConfigs) {
                try {
                    $startTime = microtime(true);

                    // Simple health check - list tools
                    $tools = $client->listTools()->await();
                    $responseTime = microtime(true) - $startTime;

                    return [
                        'server' => $serverId,
                        'healthy' => true,
                        'response_time' => $responseTime,
                        'tools_count' => count($tools->getTools())
                    ];
                } catch (\Exception $e) {
                    return [
                        'server' => $serverId,
                        'healthy' => false,
                        'error' => $e->getMessage()
                    ];
                }
            });
        }

        $healthResults = await($healthChecks);

        foreach ($healthResults as $health) {
            $serverName = $serverConfigs[$health['server']]['name'];

            if ($health['healthy']) {
                $responseMs = round($health['response_time'] * 1000, 2);
                echo "  ✅ $serverName: Healthy ({$responseMs}ms, {$health['tools_count']} tools)\n";
            } else {
                echo "  ❌ $serverName: Unhealthy - {$health['error']}\n";
            }
        }

        echo "\n";
    });
}
