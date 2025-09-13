<?php

declare(strict_types=1);

namespace MCP\Laravel\Prompts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BasePrompt
{
    /**
     * Get the prompt name.
     */
    abstract public function name(): string;

    /**
     * Get the prompt description.
     */
    abstract public function description(): string;

    /**
     * Get the prompt arguments schema.
     */
    abstract public function arguments(): array;

    /**
     * Handle the prompt generation.
     */
    abstract public function handle(array $params): array;

    /**
     * Get prompt annotations.
     */
    public function annotations(): array
    {
        return [];
    }

    /**
     * Whether this prompt should be cached.
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
        return config('mcp.cache.ttl.prompts', 300);
    }

    /**
     * Whether this prompt requires authentication.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Required scopes for this prompt.
     */
    public function requiredScopes(): array
    {
        return ['mcp:prompts'];
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name(),
            'description' => $this->description(),
            'arguments' => $this->arguments(),
        ];

        if ($annotations = $this->annotations()) {
            $result['annotations'] = $annotations;
        }

        return $result;
    }

    /**
     * Handle prompt execution with caching and error handling.
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
                    $this->logExecution($params, microtime(true) - $startTime, true);
                    return $cached;
                }
            }

            // Validate parameters
            $this->validateParams($params);

            // Execute prompt
            $result = $this->handle($params);

            // Validate result
            $this->validateResult($result);

            // Cache result if cacheable
            if ($this->cacheable()) {
                Cache::put($cacheKey, $result, $this->cacheTtl());
            }

            $this->logExecution($params, microtime(true) - $startTime, false);

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
        return config('mcp.cache.prefix', 'mcp:') . 'prompt:' . $this->name() . ':' . md5(serialize($params));
    }

    /**
     * Validate prompt parameters.
     */
    protected function validateParams(array $params): void
    {
        $arguments = $this->arguments();
        
        foreach ($arguments as $argument) {
            $name = $argument['name'];
            $required = $argument['required'] ?? false;
            
            if ($required && !array_key_exists($name, $params)) {
                throw new \InvalidArgumentException("Required argument '{$name}' is missing");
            }
        }
    }

    /**
     * Validate prompt result structure.
     */
    protected function validateResult(array $result): void
    {
        if (!isset($result['messages'])) {
            throw new \InvalidArgumentException("Prompt result must contain 'messages' array");
        }

        if (!is_array($result['messages'])) {
            throw new \InvalidArgumentException("Prompt 'messages' must be an array");
        }

        foreach ($result['messages'] as $message) {
            if (!is_array($message)) {
                throw new \InvalidArgumentException("Each message must be an array");
            }

            if (!isset($message['role'])) {
                throw new \InvalidArgumentException("Each message must have a 'role'");
            }

            if (!isset($message['content'])) {
                throw new \InvalidArgumentException("Each message must have 'content'");
            }
        }
    }

    /**
     * Log prompt execution.
     */
    protected function logExecution(array $params, float $duration, bool $cached): void
    {
        if (config('mcp.logging.log_performance', false)) {
            Log::channel(config('mcp.logging.channel'))
                ->info("MCP Prompt executed", [
                    'prompt' => $this->name(),
                    'duration' => $duration,
                    'cached' => $cached,
                    'params_count' => count($params),
                ]);
        }
    }

    /**
     * Log prompt error.
     */
    protected function logError(array $params, \Throwable $error, float $duration): void
    {
        if (config('mcp.logging.log_errors', true)) {
            Log::channel(config('mcp.logging.channel'))
                ->error("MCP Prompt error", [
                    'prompt' => $this->name(),
                    'error' => $error->getMessage(),
                    'duration' => $duration,
                    'params' => $params,
                ]);
        }
    }

    /**
     * Create a text message.
     */
    protected function createTextMessage(string $role, string $text): array
    {
        return [
            'role' => $role,
            'content' => [
                'type' => 'text',
                'text' => $text,
            ],
        ];
    }

    /**
     * Create a user message.
     */
    protected function createUserMessage(string $text): array
    {
        return $this->createTextMessage('user', $text);
    }

    /**
     * Create an assistant message.
     */
    protected function createAssistantMessage(string $text): array
    {
        return $this->createTextMessage('assistant', $text);
    }

    /**
     * Create a system message.
     */
    protected function createSystemMessage(string $text): array
    {
        return $this->createTextMessage('system', $text);
    }
}