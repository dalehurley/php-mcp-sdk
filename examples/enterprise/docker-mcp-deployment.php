#!/usr/bin/env php
<?php

/**
 * Docker MCP Deployment Example
 * 
 * This example demonstrates how to containerize MCP servers for production deployment.
 * It includes:
 * - Production-ready server configuration
 * - Health checks and monitoring
 * - Environment-based configuration
 * - Graceful shutdown handling
 * - Docker best practices for MCP
 * 
 * This server is designed to run in a Docker container with proper
 * configuration management and monitoring capabilities.
 * 
 * Usage:
 *   php docker-mcp-deployment.php
 *   
 * Docker Usage:
 *   docker build -t mcp-server .
 *   docker run -p 8080:8080 mcp-server
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;
use function Amp\async;

// Production-ready configuration
class ProductionConfig
{
    public static function get(string $key, $default = null)
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function getRequired(string $key)
    {
        $value = self::get($key);
        if ($value === null) {
            throw new Exception("Required environment variable {$key} is not set");
        }
        return $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) return $default;
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }
}

// Health check system
class HealthChecker
{
    private array $checks = [];
    private array $metrics = [];

    public function addCheck(string $name, callable $check): void
    {
        $this->checks[$name] = $check;
    }

    public function runChecks(): array
    {
        $results = [];
        $overallHealth = true;

        foreach ($this->checks as $name => $check) {
            try {
                $start = microtime(true);
                $result = $check();
                $duration = microtime(true) - $start;

                $results[$name] = [
                    'status' => $result ? 'healthy' : 'unhealthy',
                    'duration_ms' => round($duration * 1000, 2),
                    'timestamp' => date('c')
                ];

                if (!$result) {
                    $overallHealth = false;
                }
            } catch (Exception $e) {
                $results[$name] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'timestamp' => date('c')
                ];
                $overallHealth = false;
            }
        }

        return [
            'status' => $overallHealth ? 'healthy' : 'unhealthy',
            'checks' => $results,
            'timestamp' => date('c'),
            'uptime' => $this->getUptime()
        ];
    }

    public function recordMetric(string $name, $value): void
    {
        $this->metrics[$name] = [
            'value' => $value,
            'timestamp' => time()
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    private function getUptime(): string
    {
        $uptime = time() - ($_SERVER['REQUEST_TIME'] ?? time());
        return gmdate('H:i:s', $uptime);
    }
}

// Initialize configuration
$config = [
    'server_name' => ProductionConfig::get('MCP_SERVER_NAME', 'docker-mcp-server'),
    'server_version' => ProductionConfig::get('MCP_SERVER_VERSION', '1.0.0'),
    'environment' => ProductionConfig::get('ENVIRONMENT', 'production'),
    'log_level' => ProductionConfig::get('LOG_LEVEL', 'info'),
    'health_check_enabled' => ProductionConfig::getBool('HEALTH_CHECK_ENABLED', true),
    'metrics_enabled' => ProductionConfig::getBool('METRICS_ENABLED', true),
    'max_memory_mb' => ProductionConfig::getInt('MAX_MEMORY_MB', 128),
    'max_execution_time' => ProductionConfig::getInt('MAX_EXECUTION_TIME', 30)
];

// Initialize health checker
$healthChecker = new HealthChecker();

// Add health checks
$healthChecker->addCheck('memory', function () use ($config) {
    $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
    return $memoryUsage < $config['max_memory_mb'];
});

$healthChecker->addCheck('disk_space', function () {
    $freeSpace = disk_free_space('.');
    $totalSpace = disk_total_space('.');
    $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
    return $usagePercent < 90; // Less than 90% usage
});

$healthChecker->addCheck('php_version', function () {
    return version_compare(PHP_VERSION, '8.1.0', '>=');
});

// Create production MCP server
$server = new McpServer(
    new Implementation(
        $config['server_name'],
        $config['server_version'],
        'Production-ready MCP server for Docker deployment'
    )
);

// Tool: Health Check
$server->tool(
    'health_check',
    'Check server health and status',
    [
        'type' => 'object',
        'properties' => [
            'detailed' => [
                'type' => 'boolean',
                'description' => 'Include detailed health information',
                'default' => false
            ]
        ]
    ],
    function (array $args) use ($healthChecker): array {
        $detailed = $args['detailed'] ?? false;
        $health = $healthChecker->runChecks();

        if ($detailed) {
            $health['metrics'] = $healthChecker->getMetrics();
            $health['config'] = [
                'environment' => $_ENV['ENVIRONMENT'] ?? 'unknown',
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ];
        }

        $statusIcon = $health['status'] === 'healthy' ? '✅' : '❌';

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$statusIcon} Server Health: {$health['status']}\n\n" .
                        json_encode($health, JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
);

// Tool: System Information
$server->tool(
    'system_info',
    'Get system and container information',
    [
        'type' => 'object',
        'properties' => []
    ],
    function (array $args) use ($config): array {
        $info = [
            'server' => [
                'name' => $config['server_name'],
                'version' => $config['server_version'],
                'environment' => $config['environment'],
                'uptime' => gmdate('H:i:s', time() - ($_SERVER['REQUEST_TIME'] ?? time()))
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'os' => PHP_OS,
                'architecture' => php_uname('m'),
                'hostname' => gethostname(),
                'current_time' => date('c')
            ],
            'resources' => [
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                'memory_limit' => ini_get('memory_limit'),
                'disk_free' => round(disk_free_space('.') / 1024 / 1024, 2) . ' MB'
            ],
            'container' => [
                'is_docker' => file_exists('/.dockerenv'),
                'container_id' => substr(file_get_contents('/proc/self/cgroup') ?? '', 0, 12),
                'environment_vars' => array_filter($_ENV, fn($key) => str_starts_with($key, 'MCP_'), ARRAY_FILTER_USE_KEY)
            ]
        ];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "🐳 Docker MCP Server System Information\n\n" .
                        json_encode($info, JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
);

// Tool: Performance Metrics
$server->tool(
    'metrics',
    'Get server performance metrics',
    [
        'type' => 'object',
        'properties' => [
            'reset' => [
                'type' => 'boolean',
                'description' => 'Reset metrics after reading',
                'default' => false
            ]
        ]
    ],
    function (array $args) use ($healthChecker): array {
        $metrics = $healthChecker->getMetrics();
        $reset = $args['reset'] ?? false;

        // Add current metrics
        $healthChecker->recordMetric('requests_total', ($metrics['requests_total']['value'] ?? 0) + 1);
        $healthChecker->recordMetric('memory_current_mb', round(memory_get_usage(true) / 1024 / 1024, 2));
        $healthChecker->recordMetric('timestamp', time());

        $currentMetrics = $healthChecker->getMetrics();

        if ($reset) {
            // Reset metrics (in production, you'd persist this)
            $healthChecker = new HealthChecker();
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "📊 Performance Metrics\n\n" .
                        json_encode($currentMetrics, JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
);

// Resource: Docker Configuration
$server->resource(
    'Docker Configuration',
    'docker://config',
    [
        'title' => 'Docker Configuration',
        'description' => 'Docker deployment configuration and environment',
        'mimeType' => 'application/json'
    ],
    function () use ($config): string {
        $dockerConfig = [
            'image_info' => [
                'base_image' => 'php:8.1-cli',
                'mcp_sdk_version' => '1.0.0',
                'build_date' => date('c'),
                'maintainer' => 'MCP Team'
            ],
            'environment' => $config,
            'recommended_resources' => [
                'memory' => '256MB',
                'cpu' => '0.5',
                'disk' => '1GB'
            ],
            'ports' => [
                'health_check' => '8080',
                'metrics' => '9090'
            ],
            'volumes' => [
                '/app/data' => 'Application data',
                '/app/logs' => 'Application logs',
                '/app/config' => 'Configuration files'
            ]
        ];

        return json_encode($dockerConfig, JSON_PRETTY_PRINT);
    }
);

// Resource: Dockerfile
$server->resource(
    'Dockerfile',
    'docker://dockerfile',
    [
        'title' => 'Production Dockerfile',
        'description' => 'Complete Dockerfile for production deployment',
        'mimeType' => 'text/plain'
    ],
    function (): string {
        return <<<DOCKERFILE
# Production Dockerfile for MCP Server
FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \\
    git \\
    curl \\
    libzip-dev \\
    zip \\
    unzip \\
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Create non-root user
RUN groupadd -r mcp && useradd -r -g mcp mcp
RUN chown -R mcp:mcp /app
USER mcp

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD php -r "echo 'healthy';" || exit 1

# Set environment variables
ENV ENVIRONMENT=production
ENV MCP_SERVER_NAME=docker-mcp-server
ENV LOG_LEVEL=info
ENV HEALTH_CHECK_ENABLED=true

# Expose ports
EXPOSE 8080

# Run the server
CMD ["php", "docker-mcp-deployment.php"]
DOCKERFILE;
    }
);

// Prompt: Docker Deployment Help
$server->prompt(
    'docker_help',
    'Get help with Docker deployment',
    function (): array {
        return [
            'description' => 'Docker Deployment Assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I deploy this MCP server with Docker?'
                        ]
                    ]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Deploying MCP servers with Docker:\n\n" .
                                "**1. Build the Image:**\n" .
                                "```bash\n" .
                                "docker build -t mcp-server .\n" .
                                "```\n\n" .
                                "**2. Run the Container:**\n" .
                                "```bash\n" .
                                "docker run -d \\\n" .
                                "  --name mcp-server \\\n" .
                                "  -p 8080:8080 \\\n" .
                                "  -e ENVIRONMENT=production \\\n" .
                                "  -e LOG_LEVEL=info \\\n" .
                                "  mcp-server\n" .
                                "```\n\n" .
                                "**3. Health Checks:**\n" .
                                "Use the health_check tool to monitor server status\n\n" .
                                "**4. Production Features:**\n" .
                                "• Environment-based configuration\n" .
                                "• Health monitoring and metrics\n" .
                                "• Graceful shutdown handling\n" .
                                "• Resource usage monitoring\n\n" .
                                "Try: 'Use the system_info tool to see container details'"
                        ]
                    ]
                ]
            ]
        ];
    }
);

// Graceful shutdown handler
$shutdownHandler = function () use ($healthChecker) {
    echo "\n🛑 Graceful shutdown initiated...\n";

    // Record shutdown metric
    $healthChecker->recordMetric('shutdown_time', time());

    // Cleanup operations would go here
    echo "✅ Cleanup completed\n";
    exit(0);
};

// Register shutdown handlers
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, $shutdownHandler);
    pcntl_signal(SIGINT, $shutdownHandler);
}

register_shutdown_function(function () {
    echo "📊 Final metrics: Memory peak = " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . "MB\n";
});

// Start the server
async(function () use ($server, $config, $healthChecker) {
    echo "🐳 Docker MCP Server starting...\n";
    echo "📋 Environment: {$config['environment']}\n";
    echo "🔧 Configuration: {$config['server_name']} v{$config['server_version']}\n";
    echo "💾 Memory limit: {$config['max_memory_mb']}MB\n";
    echo "🏥 Health checks: " . ($config['health_check_enabled'] ? 'enabled' : 'disabled') . "\n";
    echo "📊 Metrics: " . ($config['metrics_enabled'] ? 'enabled' : 'disabled') . "\n";
    echo "🛠️  Available tools: health_check, system_info, metrics\n" . PHP_EOL;

    // Record startup metric
    $healthChecker->recordMetric('startup_time', time());

    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
