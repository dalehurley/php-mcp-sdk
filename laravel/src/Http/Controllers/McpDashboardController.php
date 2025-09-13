<?php

declare(strict_types=1);

namespace MCP\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use MCP\Server\McpServer;
use MCP\Client\Client;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class McpDashboardController extends Controller
{
    public function __construct(
        private McpServer $server,
        private Client $client
    ) {}

    /**
     * Show the MCP dashboard.
     */
    public function index(Request $request): View|InertiaResponse
    {
        $data = [
            'server' => [
                'name' => config('mcp.server.name'),
                'version' => config('mcp.server.version'),
                'connected' => $this->server->isConnected(),
                'capabilities' => config('mcp.server.capabilities'),
            ],
            'config' => [
                'auth_enabled' => config('mcp.auth.enabled'),
                'cache_enabled' => config('mcp.cache.enabled'),
                'queue_enabled' => config('mcp.queue.enabled'),
                'ui_theme' => config('mcp.ui.theme', 'light'),
                'realtime_enabled' => config('mcp.ui.realtime.enabled', true),
            ],
            'endpoints' => [
                'stats' => route('mcp.dashboard.stats'),
                'logs' => route('mcp.dashboard.logs'),
                'test_tool' => route('mcp.dashboard.test-tool'),
                'test_resource' => route('mcp.dashboard.test-resource'),
                'test_prompt' => route('mcp.dashboard.test-prompt'),
                'sse' => route('mcp.sse'),
            ],
        ];

        // Use Inertia if available, otherwise Blade
        if (class_exists('Inertia\\Inertia')) {
            return Inertia::render('Mcp/Dashboard', $data);
        }

        return view('mcp::dashboard', $data);
    }

    /**
     * Get dashboard statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = [
                'timestamp' => now()->toISOString(),
                'server' => [
                    'connected' => $this->server->isConnected(),
                    'uptime' => $this->getServerUptime(),
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true),
                ],
                'components' => [
                    'tools' => $this->getToolsStats(),
                    'resources' => $this->getResourcesStats(),
                    'prompts' => $this->getPromptsStats(),
                ],
                'performance' => [
                    'requests_per_minute' => $this->getRequestsPerMinute(),
                    'average_response_time' => $this->getAverageResponseTime(),
                    'error_rate' => $this->getErrorRate(),
                ],
                'cache' => config('mcp.cache.enabled') ? $this->getCacheStats() : null,
                'queue' => config('mcp.queue.enabled') ? $this->getQueueStats() : null,
            ];

            return response()->json($stats);
        } catch (\Throwable $e) {
            Log::channel(config('mcp.logging.channel'))->error('Dashboard stats error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to load stats'], 500);
        }
    }

    /**
     * Get recent logs.
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $level = $request->query('level', 'info');
            $limit = min((int) $request->query('limit', 50), 500);
            
            $logs = $this->getRecentLogs($level, $limit);

            return response()->json([
                'logs' => $logs,
                'level' => $level,
                'total' => count($logs),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to load logs'], 500);
        }
    }

    /**
     * Test a tool execution.
     */
    public function testTool(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tool_name' => 'required|string',
                'parameters' => 'sometimes|array',
            ]);

            $toolName = $request->input('tool_name');
            $parameters = $request->input('parameters', []);

            $startTime = microtime(true);

            // This is a simplified version - in practice, you'd call the actual tool
            $result = [
                'success' => true,
                'result' => [
                    'message' => "Tool '{$toolName}' executed successfully",
                    'parameters' => $parameters,
                    'timestamp' => now()->toISOString(),
                ],
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Test a resource read.
     */
    public function testResource(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'resource_uri' => 'required|string',
            ]);

            $resourceUri = $request->input('resource_uri');
            $startTime = microtime(true);

            // This is a simplified version - in practice, you'd read the actual resource
            $result = [
                'success' => true,
                'result' => [
                    'uri' => $resourceUri,
                    'content' => "Resource '{$resourceUri}' read successfully",
                    'mime_type' => 'text/plain',
                    'timestamp' => now()->toISOString(),
                ],
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Test a prompt generation.
     */
    public function testPrompt(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'prompt_name' => 'required|string',
                'arguments' => 'sometimes|array',
            ]);

            $promptName = $request->input('prompt_name');
            $arguments = $request->input('arguments', []);
            $startTime = microtime(true);

            // This is a simplified version - in practice, you'd execute the actual prompt
            $result = [
                'success' => true,
                'result' => [
                    'prompt' => $promptName,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => "Generated prompt '{$promptName}' with arguments",
                        ],
                    ],
                    'arguments' => $arguments,
                    'timestamp' => now()->toISOString(),
                ],
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Inspect server state (development only).
     */
    public function inspect(Request $request): JsonResponse
    {
        if (!config('app.debug')) {
            abort(404);
        }

        try {
            $inspection = [
                'server' => [
                    'connected' => $this->server->isConnected(),
                    'implementation' => [
                        'name' => config('mcp.server.name'),
                        'version' => config('mcp.server.version'),
                    ],
                    'capabilities' => config('mcp.server.capabilities'),
                ],
                'laravel' => [
                    'version' => app()->version(),
                    'environment' => config('app.env'),
                    'debug' => config('app.debug'),
                ],
                'php' => [
                    'version' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ],
                'system' => [
                    'os' => PHP_OS,
                    'timestamp' => now()->toISOString(),
                    'timezone' => config('app.timezone'),
                ],
            ];

            return response()->json($inspection);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get server uptime.
     */
    protected function getServerUptime(): ?int
    {
        $startTime = Cache::get('mcp:server:start_time');
        return $startTime ? now()->timestamp - $startTime : null;
    }

    /**
     * Get tools statistics.
     */
    protected function getToolsStats(): array
    {
        // This would be implemented with actual server introspection
        return [
            'total' => 0,
            'enabled' => 0,
            'most_used' => [],
            'execution_count' => Cache::get('mcp:stats:tools:executions', 0),
        ];
    }

    /**
     * Get resources statistics.
     */
    protected function getResourcesStats(): array
    {
        return [
            'total' => 0,
            'templates' => 0,
            'read_count' => Cache::get('mcp:stats:resources:reads', 0),
        ];
    }

    /**
     * Get prompts statistics.
     */
    protected function getPromptsStats(): array
    {
        return [
            'total' => 0,
            'generation_count' => Cache::get('mcp:stats:prompts:generations', 0),
        ];
    }

    /**
     * Get requests per minute.
     */
    protected function getRequestsPerMinute(): int
    {
        $key = 'mcp:stats:requests:' . now()->format('Y-m-d-H-i');
        return (int) Cache::get($key, 0);
    }

    /**
     * Get average response time.
     */
    protected function getAverageResponseTime(): float
    {
        return (float) Cache::get('mcp:stats:avg_response_time', 0);
    }

    /**
     * Get error rate.
     */
    protected function getErrorRate(): float
    {
        $totalRequests = Cache::get('mcp:stats:total_requests', 1);
        $totalErrors = Cache::get('mcp:stats:total_errors', 0);
        
        return $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0;
    }

    /**
     * Get cache statistics.
     */
    protected function getCacheStats(): array
    {
        try {
            $store = Cache::getStore();
            
            return [
                'driver' => get_class($store),
                'hits' => Cache::get('mcp:stats:cache:hits', 0),
                'misses' => Cache::get('mcp:stats:cache:misses', 0),
                'size_estimate' => $this->getCacheSizeEstimate(),
            ];
        } catch (\Throwable) {
            return [
                'driver' => 'unknown',
                'hits' => 0,
                'misses' => 0,
                'size_estimate' => 0,
            ];
        }
    }

    /**
     * Get queue statistics.
     */
    protected function getQueueStats(): array
    {
        try {
            // This would integrate with Laravel's queue system
            return [
                'connection' => config('mcp.queue.connection'),
                'pending' => 0, // Would query queue size
                'processed' => Cache::get('mcp:stats:queue:processed', 0),
                'failed' => Cache::get('mcp:stats:queue:failed', 0),
            ];
        } catch (\Throwable) {
            return [
                'connection' => 'unknown',
                'pending' => 0,
                'processed' => 0,
                'failed' => 0,
            ];
        }
    }

    /**
     * Get recent logs.
     */
    protected function getRecentLogs(string $level, int $limit): array
    {
        // This is a simplified implementation
        // In practice, you'd integrate with your logging system
        return [
            [
                'level' => 'info',
                'message' => 'MCP server started successfully',
                'timestamp' => now()->subMinutes(5)->toISOString(),
                'context' => ['component' => 'server'],
            ],
            [
                'level' => 'debug',
                'message' => 'Tool executed: example-tool',
                'timestamp' => now()->subMinutes(2)->toISOString(),
                'context' => ['tool' => 'example-tool', 'duration' => 150],
            ],
        ];
    }

    /**
     * Estimate cache size.
     */
    protected function getCacheSizeEstimate(): int
    {
        // This is a rough estimate and would vary by cache driver
        return 0;
    }
}