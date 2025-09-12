#!/usr/bin/env php
<?php

/**
 * MCP Server Monitor Utility
 * 
 * This utility provides comprehensive monitoring capabilities for MCP servers:
 * - Monitor server health and performance in real-time
 * - Track request/response times and success rates
 * - Memory usage and resource consumption monitoring
 * - Alert system for performance degradation
 * - Historical data collection and analysis
 * - Dashboard-style output with live updates
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Transport\StdioServerParameters;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ClientCapabilities;
use function Amp\async;
use function Amp\delay;

// Command line argument parsing
$options = getopt('', [
    'server:',
    'command:',
    'args:',
    'interval:',
    'duration:',
    'alerts',
    'log:',
    'dashboard',
    'json',
    'help'
]);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// Configuration
$config = [
    'server' => $options['server'] ?? null,
    'command' => $options['command'] ?? 'php',
    'args' => isset($options['args']) ? explode(',', $options['args']) : [],
    'interval' => (int)($options['interval'] ?? 5), // seconds
    'duration' => isset($options['duration']) ? (int)$options['duration'] : null, // seconds
    'alerts' => isset($options['alerts']),
    'log_file' => $options['log'] ?? null,
    'dashboard' => isset($options['dashboard']),
    'json_output' => isset($options['json'])
];

// Monitoring state
$monitoringData = [
    'start_time' => time(),
    'checks' => 0,
    'successful_checks' => 0,
    'failed_checks' => 0,
    'response_times' => [],
    'errors' => [],
    'memory_usage' => [],
    'alerts' => []
];

// Alert thresholds
$alertThresholds = [
    'response_time_ms' => 5000,
    'error_rate_percent' => 10,
    'memory_usage_mb' => 100,
    'consecutive_failures' => 3
];

async(function () use ($config, &$monitoringData, $alertThresholds) {
    try {
        echo "📊 MCP Server Monitor\n";
        echo "====================\n\n";

        if (!$config['server']) {
            echo "❌ Error: Server path is required\n";
            echo "Use --server=/path/to/server.php or --help for usage information\n";
            exit(1);
        }

        if ($config['dashboard']) {
            // Clear screen for dashboard mode
            echo "\033[2J\033[H";
        }

        echo "🎯 Target: {$config['server']}\n";
        echo "⏱️  Interval: {$config['interval']} seconds\n";
        if ($config['duration']) {
            echo "⏰ Duration: {$config['duration']} seconds\n";
        }
        echo "🚨 Alerts: " . ($config['alerts'] ? 'enabled' : 'disabled') . "\n";
        if ($config['log_file']) {
            echo "📝 Logging to: {$config['log_file']}\n";
        }
        echo "\n";

        $startTime = time();
        $consecutiveFailures = 0;

        while (true) {
            $checkStart = microtime(true);

            try {
                // Perform health check
                $healthData = performHealthCheck($config)->await();
                $responseTime = (microtime(true) - $checkStart) * 1000; // Convert to milliseconds

                $monitoringData['checks']++;
                $monitoringData['successful_checks']++;
                $monitoringData['response_times'][] = $responseTime;
                $consecutiveFailures = 0;

                // Keep only last 100 response times
                if (count($monitoringData['response_times']) > 100) {
                    array_shift($monitoringData['response_times']);
                }

                // Record memory usage if available
                if (isset($healthData['memory_usage'])) {
                    $monitoringData['memory_usage'][] = $healthData['memory_usage'];
                    if (count($monitoringData['memory_usage']) > 100) {
                        array_shift($monitoringData['memory_usage']);
                    }
                }

                // Check for alerts
                if ($config['alerts']) {
                    $alerts = checkAlerts($healthData, $responseTime, $monitoringData, $alertThresholds);
                    foreach ($alerts as $alert) {
                        $monitoringData['alerts'][] = array_merge($alert, ['timestamp' => time()]);
                        echo "🚨 ALERT: {$alert['message']}\n";
                    }
                }

                // Display current status
                if ($config['dashboard']) {
                    displayDashboard($monitoringData, $healthData, $responseTime);
                } elseif ($config['json_output']) {
                    echo json_encode([
                        'timestamp' => time(),
                        'status' => 'healthy',
                        'response_time_ms' => round($responseTime, 2),
                        'health_data' => $healthData
                    ]) . "\n";
                } else {
                    $timestamp = date('Y-m-d H:i:s');
                    echo "[$timestamp] ✅ Healthy - Response: " . round($responseTime, 2) . "ms";
                    if (isset($healthData['tools_count'])) {
                        echo " - Tools: {$healthData['tools_count']}";
                    }
                    if (isset($healthData['resources_count'])) {
                        echo " - Resources: {$healthData['resources_count']}";
                    }
                    echo "\n";
                }

                // Log to file if specified
                if ($config['log_file']) {
                    logToFile($config['log_file'], [
                        'timestamp' => time(),
                        'status' => 'healthy',
                        'response_time_ms' => round($responseTime, 2),
                        'health_data' => $healthData
                    ]);
                }
            } catch (\Exception $e) {
                $responseTime = (microtime(true) - $checkStart) * 1000;
                $consecutiveFailures++;

                $monitoringData['checks']++;
                $monitoringData['failed_checks']++;
                $monitoringData['errors'][] = [
                    'timestamp' => time(),
                    'message' => $e->getMessage(),
                    'response_time_ms' => round($responseTime, 2)
                ];

                // Keep only last 50 errors
                if (count($monitoringData['errors']) > 50) {
                    array_shift($monitoringData['errors']);
                }

                // Check for consecutive failure alert
                if ($config['alerts'] && $consecutiveFailures >= $alertThresholds['consecutive_failures']) {
                    $alert = [
                        'type' => 'consecutive_failures',
                        'message' => "Server has failed $consecutiveFailures consecutive health checks",
                        'severity' => 'critical',
                        'timestamp' => time()
                    ];
                    $monitoringData['alerts'][] = $alert;
                    echo "🚨 CRITICAL ALERT: {$alert['message']}\n";
                }

                if ($config['dashboard']) {
                    displayDashboard($monitoringData, null, $responseTime, $e->getMessage());
                } elseif ($config['json_output']) {
                    echo json_encode([
                        'timestamp' => time(),
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'response_time_ms' => round($responseTime, 2)
                    ]) . "\n";
                } else {
                    $timestamp = date('Y-m-d H:i:s');
                    echo "[$timestamp] ❌ Error - {$e->getMessage()} - Response: " . round($responseTime, 2) . "ms\n";
                }

                // Log error to file
                if ($config['log_file']) {
                    logToFile($config['log_file'], [
                        'timestamp' => time(),
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'response_time_ms' => round($responseTime, 2)
                    ]);
                }
            }

            // Check if duration limit reached
            if ($config['duration'] && (time() - $startTime) >= $config['duration']) {
                break;
            }

            // Wait for next check
            \Amp\delay($config['interval'] * 1000);
        }

        // Display final summary
        if (!$config['dashboard'] && !$config['json_output']) {
            displaySummary($monitoringData);
        }
    } catch (\Throwable $e) {
        echo "❌ Monitor Error: " . $e->getMessage() . "\n";
        exit(1);
    }
})->await();

/**
 * Perform health check on the server
 */
function performHealthCheck(array $config): \Amp\Future
{
    return \Amp\async(function () use ($config) {
        $client = new Client(
            new Implementation('mcp-monitor', '1.0.0', 'MCP Server Monitor'),
            new ClientOptions(capabilities: new ClientCapabilities())
        );

        $serverParams = new StdioServerParameters(
            command: $config['command'],
            args: array_merge($config['args'], [$config['server']]),
            cwd: dirname(__DIR__, 2)
        );

        $transport = new StdioClientTransport($serverParams);

        try {
            $client->connect($transport)->await();

            $healthData = [
                'server_info' => $client->getServerVersion(),
                'tools_count' => 0,
                'resources_count' => 0,
                'prompts_count' => 0
            ];

            // Try to list tools
            try {
                $tools = $client->listTools()->await();
                $healthData['tools_count'] = count($tools->getTools());
            } catch (\Exception $e) {
                $healthData['tools_error'] = $e->getMessage();
            }

            // Try to list resources
            try {
                $resources = $client->listResources()->await();
                $healthData['resources_count'] = count($resources->getResources());
            } catch (\Exception $e) {
                $healthData['resources_error'] = $e->getMessage();
            }

            // Try to list prompts
            try {
                $prompts = $client->listPrompts()->await();
                $healthData['prompts_count'] = count($prompts->getPrompts());
            } catch (\Exception $e) {
                $healthData['prompts_error'] = $e->getMessage();
            }

            // Get memory usage (if available)
            $healthData['memory_usage'] = memory_get_usage(true) / 1024 / 1024; // MB

            $client->close()->await();

            return $healthData;
        } catch (\Exception $e) {
            try {
                $client->close()->await();
            } catch (\Exception $closeError) {
                // Ignore close errors
            }
            throw $e;
        }
    });
}

/**
 * Check for alert conditions
 */
function checkAlerts(array $healthData, float $responseTime, array $monitoringData, array $thresholds): array
{
    $alerts = [];

    // Response time alert
    if ($responseTime > $thresholds['response_time_ms']) {
        $alerts[] = [
            'type' => 'high_response_time',
            'message' => "High response time: " . round($responseTime, 2) . "ms (threshold: {$thresholds['response_time_ms']}ms)",
            'severity' => 'warning'
        ];
    }

    // Error rate alert
    if ($monitoringData['checks'] > 0) {
        $errorRate = ($monitoringData['failed_checks'] / $monitoringData['checks']) * 100;
        if ($errorRate > $thresholds['error_rate_percent']) {
            $alerts[] = [
                'type' => 'high_error_rate',
                'message' => "High error rate: " . round($errorRate, 1) . "% (threshold: {$thresholds['error_rate_percent']}%)",
                'severity' => 'warning'
            ];
        }
    }

    // Memory usage alert
    if (isset($healthData['memory_usage']) && $healthData['memory_usage'] > $thresholds['memory_usage_mb']) {
        $alerts[] = [
            'type' => 'high_memory_usage',
            'message' => "High memory usage: " . round($healthData['memory_usage'], 1) . "MB (threshold: {$thresholds['memory_usage_mb']}MB)",
            'severity' => 'warning'
        ];
    }

    return $alerts;
}

/**
 * Display dashboard-style output
 */
function displayDashboard(array $monitoringData, ?array $healthData, float $responseTime, ?string $error = null): void
{
    // Clear screen and move cursor to top
    echo "\033[2J\033[H";

    $uptime = time() - $monitoringData['start_time'];
    $successRate = $monitoringData['checks'] > 0 ?
        ($monitoringData['successful_checks'] / $monitoringData['checks']) * 100 : 0;

    $avgResponseTime = !empty($monitoringData['response_times']) ?
        array_sum($monitoringData['response_times']) / count($monitoringData['response_times']) : 0;

    echo "📊 MCP Server Monitor Dashboard\n";
    echo "===============================\n\n";

    echo "🕐 Uptime: " . formatDuration($uptime) . "\n";
    echo "📈 Checks: {$monitoringData['checks']} (✅ {$monitoringData['successful_checks']} | ❌ {$monitoringData['failed_checks']})\n";
    echo "📊 Success Rate: " . round($successRate, 1) . "%\n";
    echo "⏱️  Avg Response: " . round($avgResponseTime, 2) . "ms\n";
    echo "🔄 Current Response: " . round($responseTime, 2) . "ms\n";

    if ($error) {
        echo "❌ Current Status: ERROR - $error\n";
    } else {
        echo "✅ Current Status: HEALTHY\n";
    }

    if ($healthData) {
        echo "\n🔧 Server Info:\n";
        echo "   Name: {$healthData['server_info']['name']}\n";
        echo "   Version: {$healthData['server_info']['version']}\n";
        echo "   Tools: {$healthData['tools_count']}\n";
        echo "   Resources: {$healthData['resources_count']}\n";
        echo "   Prompts: {$healthData['prompts_count']}\n";

        if (isset($healthData['memory_usage'])) {
            echo "   Memory: " . round($healthData['memory_usage'], 1) . " MB\n";
        }
    }

    // Response time graph (simple ASCII)
    if (!empty($monitoringData['response_times'])) {
        echo "\n📈 Response Time Trend (last " . count($monitoringData['response_times']) . " checks):\n";
        displayAsciiGraph($monitoringData['response_times'], 'ms');
    }

    // Recent errors
    if (!empty($monitoringData['errors'])) {
        echo "\n❌ Recent Errors:\n";
        $recentErrors = array_slice($monitoringData['errors'], -5);
        foreach ($recentErrors as $error) {
            $timestamp = date('H:i:s', $error['timestamp']);
            echo "   [$timestamp] {$error['message']}\n";
        }
    }

    // Recent alerts
    if (!empty($monitoringData['alerts'])) {
        echo "\n🚨 Recent Alerts:\n";
        $recentAlerts = array_slice($monitoringData['alerts'], -3);
        foreach ($recentAlerts as $alert) {
            $timestamp = date('H:i:s', $alert['timestamp']);
            echo "   [$timestamp] {$alert['message']}\n";
        }
    }

    echo "\n" . str_repeat("─", 50) . "\n";
    echo "Press Ctrl+C to stop monitoring\n";
}

/**
 * Display simple ASCII graph
 */
function displayAsciiGraph(array $data, string $unit): void
{
    $width = 50;
    $height = 10;

    if (empty($data)) return;

    $min = min($data);
    $max = max($data);
    $range = $max - $min;

    if ($range == 0) $range = 1; // Avoid division by zero

    // Take last $width data points
    $graphData = array_slice($data, -$width);

    echo "   Max: " . round($max, 1) . " $unit\n";

    for ($y = $height - 1; $y >= 0; $y--) {
        echo "   ";
        foreach ($graphData as $value) {
            $normalizedValue = ($value - $min) / $range;
            $barHeight = $normalizedValue * ($height - 1);

            if ($barHeight >= $y) {
                echo "█";
            } else {
                echo " ";
            }
        }
        echo "\n";
    }

    echo "   Min: " . round($min, 1) . " $unit\n";
}

/**
 * Display final summary
 */
function displaySummary(array $monitoringData): void
{
    echo "\n📊 Monitoring Summary\n";
    echo "====================\n";

    $uptime = time() - $monitoringData['start_time'];
    $successRate = $monitoringData['checks'] > 0 ?
        ($monitoringData['successful_checks'] / $monitoringData['checks']) * 100 : 0;

    echo "⏱️  Total Duration: " . formatDuration($uptime) . "\n";
    echo "📈 Total Checks: {$monitoringData['checks']}\n";
    echo "✅ Successful: {$monitoringData['successful_checks']}\n";
    echo "❌ Failed: {$monitoringData['failed_checks']}\n";
    echo "📊 Success Rate: " . round($successRate, 1) . "%\n";

    if (!empty($monitoringData['response_times'])) {
        $avgResponseTime = array_sum($monitoringData['response_times']) / count($monitoringData['response_times']);
        $minResponseTime = min($monitoringData['response_times']);
        $maxResponseTime = max($monitoringData['response_times']);

        echo "⏱️  Response Times:\n";
        echo "   Average: " . round($avgResponseTime, 2) . "ms\n";
        echo "   Minimum: " . round($minResponseTime, 2) . "ms\n";
        echo "   Maximum: " . round($maxResponseTime, 2) . "ms\n";
    }

    if (!empty($monitoringData['alerts'])) {
        echo "🚨 Total Alerts: " . count($monitoringData['alerts']) . "\n";

        $alertTypes = [];
        foreach ($monitoringData['alerts'] as $alert) {
            $alertTypes[$alert['type']] = ($alertTypes[$alert['type']] ?? 0) + 1;
        }

        foreach ($alertTypes as $type => $count) {
            echo "   $type: $count\n";
        }
    }
}

/**
 * Log monitoring data to file
 */
function logToFile(string $filename, array $data): void
{
    $logEntry = date('c') . ' ' . json_encode($data) . "\n";
    file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Format duration in human-readable format
 */
function formatDuration(int $seconds): string
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf("%dh %dm %ds", $hours, $minutes, $seconds);
    } elseif ($minutes > 0) {
        return sprintf("%dm %ds", $minutes, $seconds);
    } else {
        return sprintf("%ds", $seconds);
    }
}

/**
 * Show help information
 */
function showHelp(): void
{
    echo "MCP Server Monitor\n";
    echo "==================\n\n";
    echo "Usage: php monitor.php --server=/path/to/server.php [options]\n\n";
    echo "Options:\n";
    echo "  --server=PATH        Path to the MCP server script (required)\n";
    echo "  --command=CMD        Command to run server (default: php)\n";
    echo "  --args=ARG1,ARG2     Additional arguments for server command\n";
    echo "  --interval=SECONDS   Check interval in seconds (default: 5)\n";
    echo "  --duration=SECONDS   Total monitoring duration in seconds (unlimited if not set)\n";
    echo "  --alerts             Enable alert system for performance issues\n";
    echo "  --log=FILE           Log monitoring data to file\n";
    echo "  --dashboard          Display live dashboard (updates in place)\n";
    echo "  --json               Output in JSON format\n";
    echo "  --help               Show this help message\n\n";
    echo "Examples:\n";
    echo "  php monitor.php --server=../server/simple-server.php\n";
    echo "  php monitor.php --server=../server/weather-server.php --dashboard --alerts\n";
    echo "  php monitor.php --server=../server/sqlite-server.php --interval=10 --duration=300\n";
    echo "  php monitor.php --server=../server/oauth-server.php --json --log=monitor.log\n\n";
    echo "Dashboard Mode:\n";
    echo "  The dashboard provides real-time monitoring with visual graphs and statistics.\n";
    echo "  Press Ctrl+C to stop monitoring.\n\n";
    echo "Alert System:\n";
    echo "  Monitors response times, error rates, memory usage, and consecutive failures.\n";
    echo "  Alerts are displayed immediately and logged for analysis.\n\n";
}
