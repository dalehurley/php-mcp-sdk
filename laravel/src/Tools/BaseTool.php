<?php

declare(strict_types=1);

namespace MCP\Laravel\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseTool
{
    /**
     * Get the tool name.
     */
    abstract public function name(): string;

    /**
     * Get the tool description.
     */
    abstract public function description(): string;

    /**
     * Get the input schema for the tool.
     */
    abstract public function inputSchema(): array;

    /**
     * Handle the tool execution.
     */
    abstract public function handle(array $params): array;

    /**
     * Get the tool title (optional, defaults to name).
     */
    public function title(): ?string
    {
        return null;
    }

    /**
     * Get tool annotations (optional).
     */
    public function annotations(): array
    {
        return [];
    }

    /**
     * Whether this tool should be cached.
     */
    public function cacheable(): bool
    {
        return false;
    }

    /**
     * Get cache TTL in seconds.
     */
    public function cacheTtl(): int
    {
        return config('mcp.cache.ttl.tools', 300);
    }

    /**
     * Whether this tool requires authentication.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Required scopes for this tool.
     */
    public function requiredScopes(): array
    {
        return ['mcp:tools'];
    }

    /**
     * Whether this tool can be executed asynchronously.
     */
    public function async(): bool
    {
        return false;
    }

    /**
     * Maximum execution time in seconds.
     */
    public function maxExecutionTime(): int
    {
        return config('mcp.performance.execution_time_limit', 60);
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name(),
            'description' => $this->description(),
            'inputSchema' => $this->inputSchema(),
        ];

        if ($title = $this->title()) {
            $result['title'] = $title;
        }

        if ($annotations = $this->annotations()) {
            $result['annotations'] = $annotations;
        }

        return $result;
    }

    /**
     * Handle tool execution with caching and error handling.
     */
    public function execute(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            // Check cache if cacheable
            if ($this->cacheable()) {
                $cacheKey = $this->getCacheKey($params);
                $cached = Cache::get($cacheKey);
                
                if ($cached !== null) {
                    $this->logExecution($params, $cached, microtime(true) - $startTime, true);
                    return $cached;
                }
            }

            // Validate parameters
            $this->validateParams($params);

            // Execute tool
            $result = $this->handle($params);

            // Cache result if cacheable
            if ($this->cacheable()) {
                Cache::put($cacheKey, $result, $this->cacheTtl());
            }

            $this->logExecution($params, $result, microtime(true) - $startTime, false);

            return $result;
        } catch (\Throwable $e) {
            $this->logError($params, $e, microtime(true) - $startTime);
            throw $e;
        }
    }

    /**
     * Generate cache key for parameters.
     */
    protected function getCacheKey(array $params): string
    {
        return config('mcp.cache.prefix', 'mcp:') . 'tool:' . $this->name() . ':' . md5(serialize($params));
    }

    /**
     * Validate tool parameters against schema.
     */
    protected function validateParams(array $params): void
    {
        // Basic validation - in a full implementation, this would use JSON Schema validation
        $schema = $this->inputSchema();
        
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (!array_key_exists($field, $params)) {
                    throw new \InvalidArgumentException("Required parameter '{$field}' is missing");
                }
            }
        }
    }

    /**
     * Log tool execution.
     */
    protected function logExecution(array $params, array $result, float $duration, bool $cached): void
    {
        if (config('mcp.logging.log_performance', false)) {
            Log::channel(config('mcp.logging.channel'))
                ->info("MCP Tool executed", [
                    'tool' => $this->name(),
                    'duration' => $duration,
                    'cached' => $cached,
                    'params_count' => count($params),
                    'result_size' => strlen(json_encode($result)),
                ]);
        }
    }

    /**
     * Log tool error.
     */
    protected function logError(array $params, \Throwable $error, float $duration): void
    {
        if (config('mcp.logging.log_errors', true)) {
            Log::channel(config('mcp.logging.channel'))
                ->error("MCP Tool error", [
                    'tool' => $this->name(),
                    'error' => $error->getMessage(),
                    'duration' => $duration,
                    'params' => $params,
                ]);
        }
    }
}