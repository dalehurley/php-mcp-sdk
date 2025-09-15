<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MCP\Server\McpServer;
use MCP\Types\Implementation;

/**
 * Example Laravel service provider showing how to register the MCP server
 * as a singleton service in the Laravel container.
 *
 * This demonstrates:
 * - Registering MCP server as a singleton
 * - Configuration through Laravel config
 * - Dependency injection setup
 */
class McpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register MCP server as singleton
        $this->app->singleton(McpServer::class, function ($app) {
            return new McpServer(
                new Implementation(
                    name: config('app.name', 'Laravel MCP Server'),
                    version: config('app.version', '1.0.0')
                )
            );
        });

        // Register alias for easier access
        $this->app->alias(McpServer::class, 'mcp.server');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/mcp.php' => config_path('mcp.php'),
            ], 'mcp-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            McpServer::class,
            'mcp.server',
        ];
    }
}
