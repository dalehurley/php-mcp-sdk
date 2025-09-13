<?php

declare(strict_types=1);

namespace MCP\Laravel\Tools;

use Illuminate\Support\Facades\Cache;

class CacheTool extends BaseTool
{
    public function name(): string
    {
        return 'laravel_cache';
    }

    public function description(): string
    {
        return 'Manage Laravel cache - get, put, forget, and flush operations';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['get', 'put', 'forget', 'flush', 'many', 'putMany', 'forgetMultiple'],
                    'description' => 'Cache operation to perform',
                ],
                'key' => [
                    'type' => 'string',
                    'description' => 'Cache key (for get, put, forget operations)',
                ],
                'keys' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Array of cache keys (for many, forgetMultiple operations)',
                ],
                'value' => [
                    'type' => ['string', 'number', 'boolean', 'null', 'object', 'array'],
                    'description' => 'Value to cache (for put operation)',
                ],
                'values' => [
                    'type' => 'object',
                    'description' => 'Key-value pairs to cache (for putMany operation)',
                ],
                'ttl' => [
                    'type' => 'integer',
                    'description' => 'Time to live in seconds (for put, putMany operations)',
                    'minimum' => 1,
                ],
                'store' => [
                    'type' => 'string',
                    'description' => 'Cache store to use (optional)',
                ],
            ],
            'required' => ['operation'],
        ];
    }

    public function handle(array $params): array
    {
        $operation = $params['operation'];
        $store = $params['store'] ?? null;
        $cache = $store ? Cache::store($store) : Cache::store();

        return match ($operation) {
            'get' => $this->handleGet($cache, $params),
            'put' => $this->handlePut($cache, $params),
            'forget' => $this->handleForget($cache, $params),
            'flush' => $this->handleFlush($cache),
            'many' => $this->handleMany($cache, $params),
            'putMany' => $this->handlePutMany($cache, $params),
            'forgetMultiple' => $this->handleForgetMultiple($cache, $params),
            default => throw new \InvalidArgumentException("Unsupported operation: {$operation}"),
        };
    }

    private function handleGet($cache, array $params): array
    {
        $key = $params['key'] ?? throw new \InvalidArgumentException('Key is required for get operation');
        
        $value = $cache->get($key);
        
        return [
            'operation' => 'get',
            'key' => $key,
            'value' => $value,
            'found' => $value !== null,
        ];
    }

    private function handlePut($cache, array $params): array
    {
        $key = $params['key'] ?? throw new \InvalidArgumentException('Key is required for put operation');
        $value = $params['value'] ?? throw new \InvalidArgumentException('Value is required for put operation');
        $ttl = $params['ttl'] ?? null;

        if ($ttl !== null) {
            $result = $cache->put($key, $value, $ttl);
        } else {
            $result = $cache->forever($key, $value);
        }

        return [
            'operation' => 'put',
            'key' => $key,
            'success' => $result,
            'ttl' => $ttl,
        ];
    }

    private function handleForget($cache, array $params): array
    {
        $key = $params['key'] ?? throw new \InvalidArgumentException('Key is required for forget operation');
        
        $result = $cache->forget($key);
        
        return [
            'operation' => 'forget',
            'key' => $key,
            'success' => $result,
        ];
    }

    private function handleFlush($cache): array
    {
        $result = $cache->flush();
        
        return [
            'operation' => 'flush',
            'success' => $result,
        ];
    }

    private function handleMany($cache, array $params): array
    {
        $keys = $params['keys'] ?? throw new \InvalidArgumentException('Keys array is required for many operation');
        
        $values = $cache->many($keys);
        
        return [
            'operation' => 'many',
            'keys' => $keys,
            'values' => $values,
        ];
    }

    private function handlePutMany($cache, array $params): array
    {
        $values = $params['values'] ?? throw new \InvalidArgumentException('Values object is required for putMany operation');
        $ttl = $params['ttl'] ?? null;

        if ($ttl !== null) {
            $result = $cache->putMany($values, $ttl);
        } else {
            // Laravel doesn't have foreverMany, so we iterate
            $result = true;
            foreach ($values as $key => $value) {
                $result = $result && $cache->forever($key, $value);
            }
        }

        return [
            'operation' => 'putMany',
            'success' => $result,
            'count' => count($values),
            'ttl' => $ttl,
        ];
    }

    private function handleForgetMultiple($cache, array $params): array
    {
        $keys = $params['keys'] ?? throw new \InvalidArgumentException('Keys array is required for forgetMultiple operation');
        
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $cache->forget($key);
        }
        
        return [
            'operation' => 'forgetMultiple',
            'keys' => $keys,
            'results' => $results,
            'success_count' => count(array_filter($results)),
        ];
    }

    public function cacheable(): bool
    {
        return false; // Cache operations shouldn't be cached
    }

    public function requiresAuth(): bool
    {
        return true;
    }

    public function requiredScopes(): array
    {
        return ['mcp:tools', 'laravel:cache'];
    }
}