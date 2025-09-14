#!/usr/bin/env php
<?php

/**
 * Monitoring and Observability MCP Server
 * 
 * This example demonstrates comprehensive monitoring and observability for MCP systems:
 * - Real-time metrics collection and reporting
 * - Distributed tracing and logging
 * - Performance monitoring and alerting
 * - System health dashboards
 * - Custom metrics and SLA monitoring
 * - Log aggregation and analysis
 * 
 * Perfect for production MCP deployments requiring full observability.
 * 
 * Usage:
 *   php monitoring-observability-mcp.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;
use function Amp\async;

// Metrics Collector
class MetricsCollector
{
    private array $metrics = [];
    private array $counters = [];
    private array $gauges = [];
    private array $histograms = [];
    private int $startTime;

    public function __construct()
    {
        $this->startTime = time();
    }

    public function incrementCounter(string $name, array $labels = [], float $value = 1): void
    {
        $key = $this->getMetricKey($name, $labels);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
        $this->recordMetric('counter', $name, $value, $labels);
    }

    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $key = $this->getMetricKey($name, $labels);
        $this->gauges[$key] = $value;
        $this->recordMetric('gauge', $name, $value, $labels);
    }

    public function recordHistogram(string $name, float $value, array $labels = []): void
    {
        $key = $this->getMetricKey($name, $labels);
        if (!isset($this->histograms[$key])) {
            $this->histograms[$key] = [];
        }
        $this->histograms[$key][] = $value;
        $this->recordMetric('histogram', $name, $value, $labels);
    }

    public function getMetrics(): array
    {
        return [
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'histograms' => $this->getHistogramStats(),
            'uptime' => time() - $this->startTime,
            'timestamp' => time()
        ];
    }

    private function getMetricKey(string $name, array $labels): string
    {
        ksort($labels);
        $labelStr = empty($labels) ? '' : '{' . http_build_query($labels, '', ',') . '}';
        return $name . $labelStr;
    }

    private function recordMetric(string $type, string $name, float $value, array $labels): void
    {
        $this->metrics[] = [
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
            'timestamp' => microtime(true)
        ];

        // Keep only last 1000 metrics to prevent memory issues
        if (count($this->metrics) > 1000) {
            $this->metrics = array_slice($this->metrics, -1000);
        }
    }

    private function getHistogramStats(): array
    {
        $stats = [];
        foreach ($this->histograms as $key => $values) {
            if (empty($values)) continue;

            sort($values);
            $count = count($values);

            $stats[$key] = [
                'count' => $count,
                'sum' => array_sum($values),
                'min' => min($values),
                'max' => max($values),
                'avg' => array_sum($values) / $count,
                'p50' => $values[intval($count * 0.5)] ?? 0,
                'p90' => $values[intval($count * 0.9)] ?? 0,
                'p95' => $values[intval($count * 0.95)] ?? 0,
                'p99' => $values[intval($count * 0.99)] ?? 0,
            ];
        }
        return $stats;
    }
}

// Distributed Tracer
class DistributedTracer
{
    private array $traces = [];
    private array $activeSpans = [];

    public function startTrace(string $operationName, array $context = []): string
    {
        $traceId = uniqid('trace_', true);
        $spanId = uniqid('span_', true);

        $this->traces[$traceId] = [
            'trace_id' => $traceId,
            'operation' => $operationName,
            'start_time' => microtime(true),
            'context' => $context,
            'spans' => [],
            'status' => 'active'
        ];

        $this->activeSpans[$spanId] = [
            'span_id' => $spanId,
            'trace_id' => $traceId,
            'operation' => $operationName,
            'start_time' => microtime(true),
            'parent_span' => null,
            'tags' => [],
            'logs' => []
        ];

        return $traceId;
    }

    public function startSpan(string $traceId, string $operationName, ?string $parentSpanId = null): string
    {
        $spanId = uniqid('span_', true);

        $this->activeSpans[$spanId] = [
            'span_id' => $spanId,
            'trace_id' => $traceId,
            'operation' => $operationName,
            'start_time' => microtime(true),
            'parent_span' => $parentSpanId,
            'tags' => [],
            'logs' => []
        ];

        return $spanId;
    }

    public function finishSpan(string $spanId, array $tags = []): void
    {
        if (!isset($this->activeSpans[$spanId])) return;

        $span = $this->activeSpans[$spanId];
        $span['end_time'] = microtime(true);
        $span['duration'] = $span['end_time'] - $span['start_time'];
        $span['tags'] = array_merge($span['tags'], $tags);

        // Add span to trace
        $traceId = $span['trace_id'];
        if (isset($this->traces[$traceId])) {
            $this->traces[$traceId]['spans'][] = $span;
        }

        unset($this->activeSpans[$spanId]);
    }

    public function finishTrace(string $traceId, string $status = 'completed'): void
    {
        if (!isset($this->traces[$traceId])) return;

        $this->traces[$traceId]['end_time'] = microtime(true);
        $this->traces[$traceId]['duration'] = $this->traces[$traceId]['end_time'] - $this->traces[$traceId]['start_time'];
        $this->traces[$traceId]['status'] = $status;
    }

    public function getTrace(string $traceId): ?array
    {
        return $this->traces[$traceId] ?? null;
    }

    public function getAllTraces(): array
    {
        return $this->traces;
    }

    public function getActiveSpans(): array
    {
        return $this->activeSpans;
    }
}

// Log Aggregator
class LogAggregator
{
    private array $logs = [];
    private array $logLevels = ['debug', 'info', 'warn', 'error', 'fatal'];

    public function log(string $level, string $message, array $context = []): void
    {
        if (!in_array($level, $this->logLevels)) {
            $level = 'info';
        }

        $this->logs[] = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'service' => 'monitoring-mcp',
            'trace_id' => $context['trace_id'] ?? null
        ];

        // Keep only last 500 logs
        if (count($this->logs) > 500) {
            $this->logs = array_slice($this->logs, -500);
        }
    }

    public function getLogs(string $level = null, int $limit = 100): array
    {
        $logs = $this->logs;

        if ($level) {
            $logs = array_filter($logs, fn($log) => $log['level'] === $level);
        }

        return array_slice(array_reverse($logs), 0, $limit);
    }

    public function getLogStats(): array
    {
        $stats = [];
        foreach ($this->logLevels as $level) {
            $stats[$level] = count(array_filter($this->logs, fn($log) => $log['level'] === $level));
        }
        return $stats;
    }
}

// Initialize monitoring components
$metricsCollector = new MetricsCollector();
$tracer = new DistributedTracer();
$logger = new LogAggregator();

// Simulate some initial metrics
$metricsCollector->incrementCounter('mcp_server_requests_total', ['method' => 'tool_call', 'status' => 'success'], 150);
$metricsCollector->incrementCounter('mcp_server_requests_total', ['method' => 'tool_call', 'status' => 'error'], 5);
$metricsCollector->setGauge('mcp_server_memory_usage_bytes', memory_get_usage(true));
$metricsCollector->setGauge('mcp_server_active_connections', 3);

// Record some performance metrics
for ($i = 0; $i < 20; $i++) {
    $metricsCollector->recordHistogram('mcp_request_duration_seconds', rand(10, 500) / 1000);
}

// Add some sample logs
$logger->log('info', 'MCP server started successfully', ['version' => '1.0.0']);
$logger->log('debug', 'Processing tool call request', ['tool' => 'get_metrics']);
$logger->log('warn', 'High memory usage detected', ['memory_mb' => 245]);
$logger->log('error', 'Failed to connect to external service', ['service' => 'database', 'error' => 'connection timeout']);

// Create monitoring server
$server = new McpServer(
    new Implementation(
        'monitoring-observability-server',
        '1.0.0',
        'Comprehensive monitoring and observability for MCP systems'
    )
);

// Tool: Get Metrics
$server->tool(
    'get_metrics',
    'Retrieve system metrics in Prometheus format',
    [
        'type' => 'object',
        'properties' => [
            'format' => [
                'type' => 'string',
                'enum' => ['json', 'prometheus'],
                'description' => 'Output format for metrics',
                'default' => 'json'
            ],
            'filter' => [
                'type' => 'string',
                'description' => 'Filter metrics by name pattern'
            ]
        ]
    ],
    function (array $args) use ($metricsCollector): array {
        $format = $args['format'] ?? 'json';
        $filter = $args['filter'] ?? null;

        // Record this request
        $metricsCollector->incrementCounter('mcp_monitoring_requests_total', ['endpoint' => 'get_metrics']);

        $metrics = $metricsCollector->getMetrics();

        if ($filter) {
            // Simple filter implementation
            foreach (['counters', 'gauges', 'histograms'] as $type) {
                $metrics[$type] = array_filter(
                    $metrics[$type],
                    fn($key) => stripos($key, $filter) !== false,
                    ARRAY_FILTER_USE_KEY
                );
            }
        }

        if ($format === 'prometheus') {
            $output = "# Prometheus Metrics Export\n";
            $output .= "# Generated at " . date('c') . "\n\n";

            foreach ($metrics['counters'] as $name => $value) {
                $output .= "# TYPE {$name} counter\n";
                $output .= "{$name} {$value}\n\n";
            }

            foreach ($metrics['gauges'] as $name => $value) {
                $output .= "# TYPE {$name} gauge\n";
                $output .= "{$name} {$value}\n\n";
            }

            $text = $output;
        } else {
            $text = "📊 System Metrics\n\n" . json_encode($metrics, JSON_PRETTY_PRINT);
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text
                ]
            ]
        ];
    }
);

// Tool: Get Traces
$server->tool(
    'get_traces',
    'Retrieve distributed traces',
    [
        'type' => 'object',
        'properties' => [
            'trace_id' => [
                'type' => 'string',
                'description' => 'Specific trace ID to retrieve'
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of traces to return',
                'default' => 10
            ]
        ]
    ],
    function (array $args) use ($tracer, $metricsCollector): array {
        $traceId = $args['trace_id'] ?? null;
        $limit = $args['limit'] ?? 10;

        $metricsCollector->incrementCounter('mcp_monitoring_requests_total', ['endpoint' => 'get_traces']);

        if ($traceId) {
            $trace = $tracer->getTrace($traceId);
            if (!$trace) {
                throw new McpError(-32602, "Trace '{$traceId}' not found");
            }
            $traces = [$trace];
        } else {
            $allTraces = $tracer->getAllTraces();
            $traces = array_slice(array_reverse($allTraces), 0, $limit);
        }

        $output = "🔍 Distributed Traces\n\n";

        foreach ($traces as $trace) {
            $duration = isset($trace['duration']) ? round($trace['duration'] * 1000, 2) . 'ms' : 'active';
            $output .= "Trace: {$trace['trace_id']}\n";
            $output .= "Operation: {$trace['operation']}\n";
            $output .= "Status: {$trace['status']}\n";
            $output .= "Duration: {$duration}\n";
            $output .= "Spans: " . count($trace['spans']) . "\n";

            if (!empty($trace['spans'])) {
                $output .= "Span Details:\n";
                foreach ($trace['spans'] as $span) {
                    $spanDuration = round($span['duration'] * 1000, 2);
                    $output .= "  - {$span['operation']} ({$spanDuration}ms)\n";
                }
            }
            $output .= "\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $output
                ]
            ]
        ];
    }
);

// Tool: Get Logs
$server->tool(
    'get_logs',
    'Retrieve application logs',
    [
        'type' => 'object',
        'properties' => [
            'level' => [
                'type' => 'string',
                'enum' => ['debug', 'info', 'warn', 'error', 'fatal'],
                'description' => 'Filter logs by level'
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of logs to return',
                'default' => 50
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Search term to filter log messages'
            ]
        ]
    ],
    function (array $args) use ($logger, $metricsCollector): array {
        $level = $args['level'] ?? null;
        $limit = $args['limit'] ?? 50;
        $search = $args['search'] ?? null;

        $metricsCollector->incrementCounter('mcp_monitoring_requests_total', ['endpoint' => 'get_logs']);

        $logs = $logger->getLogs($level, $limit);

        if ($search) {
            $logs = array_filter(
                $logs,
                fn($log) =>
                stripos($log['message'], $search) !== false ||
                    stripos(json_encode($log['context']), $search) !== false
            );
        }

        $output = "📝 Application Logs\n\n";
        $output .= "Filter: " . ($level ? "level={$level}" : 'all levels') . "\n";
        $output .= "Count: " . count($logs) . "\n\n";

        foreach ($logs as $log) {
            $timestamp = date('Y-m-d H:i:s', intval($log['timestamp']));
            $levelIcon = match ($log['level']) {
                'debug' => '🐛',
                'info' => 'ℹ️',
                'warn' => '⚠️',
                'error' => '❌',
                'fatal' => '💀',
                default => '📄'
            };

            $output .= "{$levelIcon} [{$timestamp}] {$log['level']}: {$log['message']}\n";

            if (!empty($log['context'])) {
                $output .= "   Context: " . json_encode($log['context']) . "\n";
            }

            if ($log['trace_id']) {
                $output .= "   Trace: {$log['trace_id']}\n";
            }

            $output .= "\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $output
                ]
            ]
        ];
    }
);

// Tool: Health Dashboard
$server->tool(
    'health_dashboard',
    'Generate comprehensive health dashboard',
    [
        'type' => 'object',
        'properties' => []
    ],
    function (array $args) use ($metricsCollector, $tracer, $logger): array {
        $metrics = $metricsCollector->getMetrics();
        $logStats = $logger->getLogStats();
        $activeSpans = count($tracer->getActiveSpans());
        $totalTraces = count($tracer->getAllTraces());

        $dashboard = "🏥 MCP System Health Dashboard\n";
        $dashboard .= "=" . str_repeat("=", 40) . "\n\n";

        // System Overview
        $dashboard .= "📊 System Overview\n";
        $dashboard .= "-" . str_repeat("-", 20) . "\n";
        $dashboard .= "Uptime: " . gmdate('H:i:s', $metrics['uptime']) . "\n";
        $dashboard .= "Memory Usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
        $dashboard .= "Peak Memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
        $dashboard .= "Active Traces: {$activeSpans}\n";
        $dashboard .= "Total Traces: {$totalTraces}\n\n";

        // Request Metrics
        $dashboard .= "🚀 Request Metrics\n";
        $dashboard .= "-" . str_repeat("-", 20) . "\n";
        foreach ($metrics['counters'] as $name => $value) {
            if (str_contains($name, 'requests_total')) {
                $dashboard .= "{$name}: {$value}\n";
            }
        }
        $dashboard .= "\n";

        // Performance Stats
        if (!empty($metrics['histograms'])) {
            $dashboard .= "⚡ Performance Stats\n";
            $dashboard .= "-" . str_repeat("-", 20) . "\n";
            foreach ($metrics['histograms'] as $name => $stats) {
                if (str_contains($name, 'duration')) {
                    $dashboard .= "{$name}:\n";
                    $dashboard .= "  Average: " . round($stats['avg'] * 1000, 2) . "ms\n";
                    $dashboard .= "  P95: " . round($stats['p95'] * 1000, 2) . "ms\n";
                    $dashboard .= "  P99: " . round($stats['p99'] * 1000, 2) . "ms\n";
                }
            }
            $dashboard .= "\n";
        }

        // Log Summary
        $dashboard .= "📝 Log Summary\n";
        $dashboard .= "-" . str_repeat("-", 20) . "\n";
        foreach ($logStats as $level => $count) {
            $icon = match ($level) {
                'debug' => '🐛',
                'info' => 'ℹ️',
                'warn' => '⚠️',
                'error' => '❌',
                'fatal' => '💀',
                default => '📄'
            };
            $dashboard .= "{$icon} {$level}: {$count}\n";
        }

        // Health Status
        $dashboard .= "\n🟢 Overall Status: HEALTHY\n";
        $errorRate = ($logStats['error'] + $logStats['fatal']) / max(array_sum($logStats), 1);
        if ($errorRate > 0.1) {
            $dashboard .= "⚠️  Warning: High error rate detected (" . round($errorRate * 100, 2) . "%)\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $dashboard
                ]
            ]
        ];
    }
);

// Resource: Monitoring Configuration
$server->resource(
    'Monitoring Configuration',
    'monitoring://config',
    [
        'title' => 'Monitoring and Observability Configuration',
        'description' => 'Complete monitoring stack configuration',
        'mimeType' => 'application/json'
    ],
    function (): string {
        return json_encode([
            'monitoring_stack' => [
                'metrics' => [
                    'collector' => 'custom',
                    'storage' => 'in_memory',
                    'export_formats' => ['json', 'prometheus'],
                    'retention' => '1000_points'
                ],
                'tracing' => [
                    'system' => 'custom_distributed_tracer',
                    'sampling_rate' => 1.0,
                    'storage' => 'in_memory'
                ],
                'logging' => [
                    'levels' => ['debug', 'info', 'warn', 'error', 'fatal'],
                    'aggregation' => 'in_memory',
                    'retention' => '500_entries'
                ]
            ],
            'integrations' => [
                'prometheus' => 'metrics export compatible',
                'jaeger' => 'tracing format compatible',
                'elk_stack' => 'log format compatible',
                'grafana' => 'dashboard ready'
            ],
            'alerting' => [
                'thresholds' => [
                    'error_rate' => '> 10%',
                    'response_time_p95' => '> 1000ms',
                    'memory_usage' => '> 80%'
                ]
            ],
            'production_recommendations' => [
                'use_external_storage' => 'Redis/PostgreSQL for metrics',
                'implement_sampling' => 'For high-volume tracing',
                'setup_log_rotation' => 'Prevent disk space issues',
                'configure_alerting' => 'PagerDuty/Slack integration'
            ]
        ], JSON_PRETTY_PRINT);
    }
);

// Prompt: Monitoring Help
$server->prompt(
    'monitoring_help',
    'Get help with monitoring and observability',
    function (): array {
        return [
            'description' => 'Monitoring and Observability Assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I monitor my MCP applications?'
                        ]
                    ]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Comprehensive MCP monitoring includes:\n\n" .
                                "**📊 Metrics Collection:**\n" .
                                "• **Counters**: Request counts, error counts\n" .
                                "• **Gauges**: Memory usage, active connections\n" .
                                "• **Histograms**: Response times, request sizes\n\n" .
                                "**🔍 Distributed Tracing:**\n" .
                                "• Track requests across services\n" .
                                "• Identify performance bottlenecks\n" .
                                "• Debug complex interactions\n\n" .
                                "**📝 Log Aggregation:**\n" .
                                "• Centralized logging with levels\n" .
                                "• Structured logging with context\n" .
                                "• Search and filtering capabilities\n\n" .
                                "**🛠️ Available Tools:**\n" .
                                "• **get_metrics** - Retrieve system metrics\n" .
                                "• **get_traces** - View distributed traces\n" .
                                "• **get_logs** - Access application logs\n" .
                                "• **health_dashboard** - Comprehensive overview\n\n" .
                                "**🏥 Health Monitoring:**\n" .
                                "• Real-time health dashboard\n" .
                                "• Performance statistics\n" .
                                "• Error rate monitoring\n" .
                                "• Resource usage tracking\n\n" .
                                "Try: 'Use health_dashboard to see system overview'"
                        ]
                    ]
                ]
            ]
        ];
    }
);

// Start the monitoring server
async(function () use ($server, $metricsCollector, $tracer, $logger) {
    echo "📊 Monitoring & Observability MCP Server starting...\n";
    echo "🔧 Metrics Collector: Active\n";
    echo "🔍 Distributed Tracer: Active\n";
    echo "📝 Log Aggregator: Active\n";
    echo "🛠️  Available tools: get_metrics, get_traces, get_logs, health_dashboard\n";
    echo "📈 Ready for production monitoring!\n" . PHP_EOL;

    // Start a sample trace
    $traceId = $tracer->startTrace('mcp_server_startup');
    $spanId = $tracer->startSpan($traceId, 'transport_initialization');

    $transport = new StdioServerTransport();

    $tracer->finishSpan($spanId, ['transport' => 'stdio', 'status' => 'success']);
    $tracer->finishTrace($traceId, 'completed');

    $logger->log('info', 'Monitoring server started successfully', ['trace_id' => $traceId]);

    $server->connect($transport)->await();
})->await();
