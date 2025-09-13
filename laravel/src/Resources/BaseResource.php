<?php

declare(strict_types=1);

namespace MCP\Laravel\Resources;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MCP\Shared\UriTemplate;

abstract class BaseResource
{
    /**
     * Get the resource URI or URI template.
     */
    abstract public function uri(): string;

    /**
     * Get the resource name.
     */
    abstract public function name(): string;

    /**
     * Get the resource description.
     */
    abstract public function description(): string;

    /**
     * Read the resource content.
     */
    abstract public function read(string $uri): array;

    /**
     * Get the MIME type of the resource.
     */
    public function mimeType(): ?string
    {
        return null;
    }

    /**
     * Get resource annotations.
     */
    public function annotations(): array
    {
        return [];
    }

    /**
     * Whether this resource supports URI templates.
     */
    public function supportsTemplates(): bool
    {
        return str_contains($this->uri(), '{');
    }

    /**
     * Get the URI template (if supported).
     */
    public function uriTemplate(): string
    {
        if (!$this->supportsTemplates()) {
            throw new \LogicException('This resource does not support URI templates');
        }
        
        return $this->uri();
    }

    /**
     * Whether this resource should be cached.
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
        return config('mcp.cache.ttl.resources', 60);
    }

    /**
     * Whether this resource requires authentication.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Required scopes for this resource.
     */
    public function requiredScopes(): array
    {
        return ['mcp:resources'];
    }

    /**
     * Whether this resource supports subscriptions.
     */
    public function subscribable(): bool
    {
        return false;
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name(),
            'description' => $this->description(),
        ];

        if ($this->supportsTemplates()) {
            $result['uriTemplate'] = $this->uriTemplate();
        } else {
            $result['uri'] = $this->uri();
        }

        if ($mimeType = $this->mimeType()) {
            $result['mimeType'] = $mimeType;
        }

        if ($annotations = $this->annotations()) {
            $result['annotations'] = $annotations;
        }

        return $result;
    }

    /**
     * Handle resource reading with caching and error handling.
     */
    public function readResource(string $uri): array
    {
        $startTime = microtime(true);
        
        try {
            // Validate URI if using templates
            if ($this->supportsTemplates()) {
                $this->validateUri($uri);
            }

            // Check cache if cacheable
            if ($this->cacheable()) {
                $cacheKey = $this->getCacheKey($uri);
                $cached = Cache::get($cacheKey);
                
                if ($cached !== null) {
                    $this->logRead($uri, microtime(true) - $startTime, true);
                    return $cached;
                }
            }

            // Read resource
            $result = $this->read($uri);

            // Cache result if cacheable
            if ($this->cacheable()) {
                Cache::put($cacheKey, $result, $this->cacheTtl());
            }

            $this->logRead($uri, microtime(true) - $startTime, false);

            return $result;
        } catch (\Throwable $e) {
            $this->logError($uri, $e, microtime(true) - $startTime);
            throw $e;
        }
    }

    /**
     * Validate URI against template.
     */
    protected function validateUri(string $uri): void
    {
        $template = new UriTemplate($this->uriTemplate());
        
        if (!$template->match($uri)) {
            throw new \InvalidArgumentException("URI '{$uri}' does not match template '{$this->uriTemplate()}'");
        }
    }

    /**
     * Generate cache key for URI.
     */
    protected function getCacheKey(string $uri): string
    {
        return config('mcp.cache.prefix', 'mcp:') . 'resource:' . $this->name() . ':' . md5($uri);
    }

    /**
     * Log resource read.
     */
    protected function logRead(string $uri, float $duration, bool $cached): void
    {
        if (config('mcp.logging.log_performance', false)) {
            Log::channel(config('mcp.logging.channel'))
                ->info("MCP Resource read", [
                    'resource' => $this->name(),
                    'uri' => $uri,
                    'duration' => $duration,
                    'cached' => $cached,
                ]);
        }
    }

    /**
     * Log resource error.
     */
    protected function logError(string $uri, \Throwable $error, float $duration): void
    {
        if (config('mcp.logging.log_errors', true)) {
            Log::channel(config('mcp.logging.channel'))
                ->error("MCP Resource error", [
                    'resource' => $this->name(),
                    'uri' => $uri,
                    'error' => $error->getMessage(),
                    'duration' => $duration,
                ]);
        }
    }

    /**
     * Trigger resource update notification.
     */
    protected function notifyUpdate(string $uri): void
    {
        if ($this->subscribable()) {
            // In a full implementation, this would emit a resource update notification
            // For now, just invalidate cache
            if ($this->cacheable()) {
                Cache::forget($this->getCacheKey($uri));
            }
        }
    }
}