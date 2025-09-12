#!/usr/bin/env php
<?php

/**
 * Parallel Tool Calls Client Example
 * 
 * This example demonstrates how to:
 * - Execute multiple tool calls concurrently
 * - Handle parallel responses and notifications
 * - Aggregate results from multiple tools
 * - Measure performance improvements from parallelization
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
use function Amp\await;
use function Amp\Future\all;

// Create the client
$client = new Client(
    new Implementation('parallel-tools-client', '1.0.0', 'Parallel Tool Calls Demo Client'),
    new ClientOptions(
        capabilities: new ClientCapabilities()
    )
);

// Server configuration - you can change this to connect to different servers
$servers = [
    'calculator' => [
        'command' => 'php',
        'args' => [__DIR__ . '/../server/simple-server.php'],
        'name' => 'Calculator Server'
    ],
    'weather' => [
        'command' => 'php',
        'args' => [__DIR__ . '/../server/weather-server.php'],
        'name' => 'Weather Server'
    ],
    'database' => [
        'command' => 'php',
        'args' => [__DIR__ . '/../server/sqlite-server.php'],
        'name' => 'Database Server'
    ]
];

// Choose which server to connect to
$serverChoice = $argv[1] ?? 'calculator';
if (!isset($servers[$serverChoice])) {
    echo "Available servers: " . implode(', ', array_keys($servers)) . "\n";
    echo "Usage: php parallel-tools-client.php [server_name]\n";
    exit(1);
}

$serverConfig = $servers[$serverChoice];

async(function () use ($client, $serverConfig, $serverChoice) {
    try {
        echo "🚀 Parallel Tool Calls Client\n";
        echo "============================\n";
        echo "Connecting to: {$serverConfig['name']}\n\n";

        // Create transport and connect
        $serverParams = new StdioServerParameters(
            command: $serverConfig['command'],
            args: $serverConfig['args'],
            cwd: dirname(__DIR__, 2)
        );

        $transport = new StdioClientTransport($serverParams);
        await($client->connect($transport));

        echo "✅ Connected! Server info: " . json_encode($client->getServerVersion()) . "\n\n";

        // List available tools
        echo "📋 Available Tools:\n";
        $tools = await($client->listTools());
        $toolNames = [];
        foreach ($tools->getTools() as $tool) {
            $toolNames[] = $tool->getName();
            echo "  - {$tool->getName()}: {$tool->getDescription()}\n";
        }
        echo "\n";

        if (empty($toolNames)) {
            echo "❌ No tools available on this server.\n";
            return;
        }

        // Demonstrate parallel tool calls based on server type
        switch ($serverChoice) {
            case 'calculator':
                await(demonstrateCalculatorParallel($client));
                break;
            case 'weather':
                await(demonstrateWeatherParallel($client));
                break;
            case 'database':
                await(demonstrateDatabaseParallel($client));
                break;
            default:
                await(demonstrateGenericParallel($client, $toolNames));
        }

        echo "\n🔌 Closing connection...\n";
        await($client->close());
        echo "✅ Done!\n";
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
})->await();

/**
 * Demonstrate parallel calculator operations
 */
function demonstrateCalculatorParallel(Client $client): \Amp\Future
{
    return async(function () use ($client) {
        echo "🧮 Calculator Parallel Operations\n";
        echo "================================\n";

        $calculations = [
            ['expression' => '2 + 2'],
            ['expression' => '10 * 5'],
            ['expression' => '100 / 4'],
            ['expression' => '15 - 3'],
            ['expression' => '(5 + 3) * 2']
        ];

        // Sequential execution for comparison
        echo "⏱️  Sequential execution:\n";
        $sequentialStart = microtime(true);

        $sequentialResults = [];
        foreach ($calculations as $i => $calc) {
            echo "  Calculating: {$calc['expression']}\n";
            $result = await($client->callToolByName('calculate', $calc));
            $sequentialResults[] = $result;
        }

        $sequentialTime = microtime(true) - $sequentialStart;
        echo "  Sequential time: " . round($sequentialTime * 1000, 2) . "ms\n\n";

        // Parallel execution
        echo "⚡ Parallel execution:\n";
        $parallelStart = microtime(true);

        $promises = [];
        foreach ($calculations as $i => $calc) {
            echo "  Starting: {$calc['expression']}\n";
            $promises[] = $client->callToolByName('calculate', $calc);
        }

        // Wait for all calculations to complete
        $parallelResults = await(all($promises));
        $parallelTime = microtime(true) - $parallelStart;

        echo "  Parallel time: " . round($parallelTime * 1000, 2) . "ms\n";
        echo "  Speed improvement: " . round(($sequentialTime / $parallelTime), 2) . "x faster\n\n";

        // Display results
        echo "📊 Results Comparison:\n";
        foreach ($calculations as $i => $calc) {
            $seqResult = getResultText($sequentialResults[$i]);
            $parResult = getResultText($parallelResults[$i]);
            echo "  {$calc['expression']} = $seqResult (results match: " . ($seqResult === $parResult ? '✅' : '❌') . ")\n";
        }
    });
}

/**
 * Demonstrate parallel weather queries
 */
function demonstrateWeatherParallel(Client $client): \Amp\Future
{
    return async(function () use ($client) {
        echo "🌤️  Weather Parallel Queries\n";
        echo "===========================\n";

        $locations = [
            ['location' => 'London,UK', 'units' => 'metric'],
            ['location' => 'New York,NY,US', 'units' => 'imperial'],
            ['location' => 'Tokyo,JP', 'units' => 'metric'],
            ['location' => 'Sydney,AU', 'units' => 'metric']
        ];

        echo "⚡ Fetching weather for multiple cities in parallel:\n";
        $start = microtime(true);

        $promises = [];
        foreach ($locations as $loc) {
            echo "  Starting query for: {$loc['location']}\n";
            $promises[] = $client->callToolByName('current-weather', $loc);
        }

        $results = await(all($promises));
        $totalTime = microtime(true) - $start;

        echo "  Total time: " . round($totalTime * 1000, 2) . "ms\n\n";

        // Display results
        echo "🌍 Weather Results:\n";
        foreach ($results as $i => $result) {
            $location = $locations[$i]['location'];
            echo "  📍 $location:\n";
            if ($result->isError()) {
                echo "    ❌ Error: " . getResultText($result) . "\n";
            } else {
                $weatherText = getResultText($result);
                // Extract just the first line for summary
                $firstLine = explode("\n", $weatherText)[0] ?? $weatherText;
                echo "    " . trim($firstLine) . "\n";
            }
        }

        // Demonstrate forecast queries in parallel
        echo "\n🔮 Parallel Forecast Queries:\n";
        $forecastStart = microtime(true);

        $forecastPromises = [];
        foreach (array_slice($locations, 0, 2) as $loc) { // Just first 2 cities for demo
            $forecastPromises[] = $client->callToolByName('weather-forecast', array_merge($loc, ['days' => 3]));
        }

        $forecastResults = await(all($forecastPromises));
        $forecastTime = microtime(true) - $forecastStart;

        echo "  Forecast time: " . round($forecastTime * 1000, 2) . "ms\n";
        echo "  Retrieved forecasts for " . count($forecastResults) . " cities\n";
    });
}

/**
 * Demonstrate parallel database queries
 */
function demonstrateDatabaseParallel(Client $client): \Amp\Future
{
    return async(function () use ($client) {
        echo "🗄️  Database Parallel Queries\n";
        echo "============================\n";

        $queries = [
            [
                'query' => 'SELECT COUNT(*) as user_count FROM users',
                'description' => 'Count users'
            ],
            [
                'query' => 'SELECT COUNT(*) as post_count FROM posts',
                'description' => 'Count posts'
            ],
            [
                'query' => 'SELECT COUNT(*) as comment_count FROM comments',
                'description' => 'Count comments'
            ],
            [
                'query' => 'SELECT username, email FROM users LIMIT 3',
                'description' => 'Sample users'
            ]
        ];

        echo "⚡ Executing multiple database queries in parallel:\n";
        $start = microtime(true);

        $promises = [];
        foreach ($queries as $q) {
            echo "  Starting: {$q['description']}\n";
            $promises[] = $client->callToolByName('query-select', ['query' => $q['query']]);
        }

        $results = await(all($promises));
        $totalTime = microtime(true) - $start;

        echo "  Total query time: " . round($totalTime * 1000, 2) . "ms\n\n";

        // Display results
        echo "📊 Query Results:\n";
        foreach ($results as $i => $result) {
            $description = $queries[$i]['description'];
            echo "  🔍 $description:\n";
            if ($result->isError()) {
                echo "    ❌ Error: " . getResultText($result) . "\n";
            } else {
                $resultText = getResultText($result);
                // Try to extract just the relevant data
                if (preg_match('/\"row_count\":\s*(\d+)/', $resultText, $matches)) {
                    echo "    ✅ Found {$matches[1]} rows\n";
                } elseif (preg_match('/\"data\":\s*(\[.*?\])/s', $resultText, $matches)) {
                    $data = json_decode($matches[1], true);
                    if ($data && is_array($data)) {
                        echo "    ✅ Retrieved " . count($data) . " records\n";
                        if (count($data) <= 3) {
                            foreach ($data as $row) {
                                if (isset($row['username'])) {
                                    echo "      - {$row['username']} ({$row['email']})\n";
                                }
                            }
                        }
                    }
                } else {
                    echo "    ✅ Query completed\n";
                }
            }
        }

        // Demonstrate parallel search
        echo "\n🔍 Parallel Search Queries:\n";
        $searchTerms = ['alice', 'post', 'comment'];
        $searchStart = microtime(true);

        $searchPromises = [];
        foreach ($searchTerms as $term) {
            echo "  Searching for: '$term'\n";
            $searchPromises[] = $client->callToolByName('search', ['term' => $term]);
        }

        $searchResults = await(all($searchPromises));
        $searchTime = microtime(true) - $searchStart;

        echo "  Search time: " . round($searchTime * 1000, 2) . "ms\n";
        echo "  Completed " . count($searchResults) . " parallel searches\n";
    });
}

/**
 * Generic parallel demonstration for unknown tools
 */
function demonstrateGenericParallel(Client $client, array $toolNames): \Amp\Future
{
    return async(function () use ($client, $toolNames) {
        echo "🔧 Generic Parallel Tool Calls\n";
        echo "==============================\n";

        // Try to call the first few tools in parallel
        $toolsToTest = array_slice($toolNames, 0, 3);

        echo "⚡ Calling multiple tools in parallel:\n";
        $start = microtime(true);

        $promises = [];
        foreach ($toolsToTest as $toolName) {
            echo "  Starting: $toolName\n";
            // Call with empty parameters - tools should handle gracefully
            $promises[] = $client->callToolByName($toolName, []);
        }

        $results = await(all($promises));
        $totalTime = microtime(true) - $start;

        echo "  Total time: " . round($totalTime * 1000, 2) . "ms\n\n";

        // Display results
        echo "📋 Tool Results:\n";
        foreach ($results as $i => $result) {
            $toolName = $toolsToTest[$i];
            echo "  🔧 $toolName:\n";
            if ($result->isError()) {
                echo "    ❌ Error: " . getResultText($result) . "\n";
            } else {
                echo "    ✅ Success\n";
            }
        }
    });
}

/**
 * Extract text content from a tool result
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
        } elseif (method_exists($item, 'getType') && $item->getType() === 'text') {
            $texts[] = $item->text ?? 'No text';
        }
    }

    return implode(' ', $texts);
}

/**
 * Demonstration of notification handling during parallel operations
 */
function demonstrateNotificationHandling(Client $client): \Amp\Future
{
    return async(function () use ($client) {
        echo "📢 Notification Handling During Parallel Operations\n";
        echo "=================================================\n";

        // Set up notification handler
        $notificationCount = 0;
        $client->setNotificationHandler(function ($notification) use (&$notificationCount) {
            $notificationCount++;
            echo "  📢 Notification #$notificationCount: " . json_encode($notification) . "\n";
        });

        echo "🔔 Starting operations that may generate notifications...\n";

        // This would work with servers that send notifications
        $promises = [
            $client->callToolByName('some-tool', ['param' => 'value1']),
            $client->callToolByName('another-tool', ['param' => 'value2'])
        ];

        await(all($promises));

        echo "✅ Operations completed. Received $notificationCount notifications.\n";
    });
}
