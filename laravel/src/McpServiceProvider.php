<?php

declare(strict_types=1);

namespace MCP\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Application as Artisan;
use MCP\Server\McpServer;
use MCP\Client\Client;
use MCP\Types\Implementation;
use MCP\Laravel\Console\Commands\McpServerCommand;
use MCP\Laravel\Console\Commands\McpInstallCommand;
use MCP\Laravel\Console\Commands\McpToolMakeCommand;
use MCP\Laravel\Console\Commands\McpResourceMakeCommand;
use MCP\Laravel\Console\Commands\McpPromptMakeCommand;
use MCP\Laravel\Http\Controllers\McpController;
use MCP\Laravel\Http\Middleware\McpAuth;
use MCP\Laravel\Tools\BaseTool;
use MCP\Laravel\Resources\BaseResource;
use MCP\Laravel\Prompts\BasePrompt;
use MCP\Laravel\Facades\McpFacadeService;
use MCP\Laravel\Tools;
use ReflectionClass;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mcp.php',
            'mcp'
        );

        // Register MCP Server
        $this->app->singleton(McpServer::class, function ($app) {
            $server = new McpServer(
                new Implementation(
                    config('mcp.server.name', 'laravel-mcp'),
                    config('mcp.server.version', '1.0.0')
                )
            );

            // Register built-in tools first
            $this->registerBuiltinTools($server);

            // Auto-discover and register components if enabled
            if (config('mcp.server.auto_discover.enabled', true)) {
                $this->registerTools($server);
                $this->registerResources($server);
                $this->registerPrompts($server);
            }

            return $server;
        });

        // Register MCP Client
        $this->app->singleton(Client::class, function ($app) {
            // Convert array options to ClientOptions object
            $optionsArray = config('mcp.client.options', []);
            $options = null;
            
            // Only create ClientOptions if we have configuration
            if (!empty($optionsArray)) {
                $options = new \MCP\Client\ClientOptions($optionsArray);
            }
            
            return new Client(
                new Implementation(
                    config('mcp.client.name', 'laravel-client'),
                    config('mcp.client.version', '1.0.0')
                ),
                $options
            );
        });

        // Register HTTP Controller
        $this->app->bind(McpController::class);

        // Register Middleware
        $this->app->bind(McpAuth::class);

        // Register Facade Service
        $this->app->singleton(McpFacadeService::class, function ($app) {
            return new McpFacadeService(
                $app->make(McpServer::class),
                $app->make(Client::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');

        // Publish stubs for make commands
        $this->publishes([
            __DIR__ . '/../stubs' => resource_path('stubs/mcp'),
        ], 'mcp-stubs');

        // Publish React/Inertia components
        $this->publishes([
            __DIR__ . '/../resources/js' => resource_path('js/vendor/mcp'),
        ], 'mcp-components');

        // Publish Blade components
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/mcp'),
        ], 'mcp-views');

        // Load routes if enabled
        if (config('mcp.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/mcp.php');
        }

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                McpServerCommand::class,
                McpInstallCommand::class,
                McpToolMakeCommand::class,
                McpResourceMakeCommand::class,
                McpPromptMakeCommand::class,
            ]);
        }

        // Register middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('mcp.auth', McpAuth::class);

        // Load migrations if they exist
        $migrationPath = __DIR__ . '/../database/migrations';
        if (file_exists($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mcp');
    }

    /**
     * Register built-in Laravel tools.
     */
    protected function registerBuiltinTools(McpServer $server): void
    {
        $builtinTools = config('mcp.server.builtin_tools', []);

        foreach ($builtinTools as $toolName => $enabled) {
            if (!$enabled) {
                continue;
            }

            $toolClass = match ($toolName) {
                'cache' => Tools\CacheTool::class,
                'database' => Tools\DatabaseTool::class,
                'artisan' => Tools\ArtisanTool::class,
                // 'storage' => Tools\StorageTool::class,
                // 'queue' => Tools\QueueTool::class,
                // 'log' => Tools\LogTool::class,
                default => null,
            };

            if ($toolClass && class_exists($toolClass)) {
                try {
                    $tool = new $toolClass();
                    $server->registerTool(
                        $tool->name(),
                        $tool->toArray(),
                        [$tool, 'execute']
                    );
                } catch (\Throwable $e) {
                    if ($this->app->hasDebugModeEnabled()) {
                        logger()->warning("Failed to register built-in MCP tool: {$toolName}", [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Auto-discover and register tools.
     */
    protected function registerTools(McpServer $server): void
    {
        $namespaces = config('mcp.server.auto_discover.namespaces', []);
        $toolNamespace = $namespaces['App\\Mcp\\Tools'] ?? 'tools';

        if ($toolNamespace === 'tools') {
            $path = app_path('Mcp/Tools');
            $namespace = 'App\\Mcp\\Tools';

            $this->discoverAndRegisterClasses(
                $server,
                $path,
                $namespace,
                BaseTool::class,
                [$this, 'registerTool']
            );
        }
    }

    /**
     * Auto-discover and register resources.
     */
    protected function registerResources(McpServer $server): void
    {
        $namespaces = config('mcp.server.auto_discover.namespaces', []);
        $resourceNamespace = $namespaces['App\\Mcp\\Resources'] ?? 'resources';

        if ($resourceNamespace === 'resources') {
            $path = app_path('Mcp/Resources');
            $namespace = 'App\\Mcp\\Resources';

            $this->discoverAndRegisterClasses(
                $server,
                $path,
                $namespace,
                BaseResource::class,
                [$this, 'registerResource']
            );
        }
    }

    /**
     * Auto-discover and register prompts.
     */
    protected function registerPrompts(McpServer $server): void
    {
        $namespaces = config('mcp.server.auto_discover.namespaces', []);
        $promptNamespace = $namespaces['App\\Mcp\\Prompts'] ?? 'prompts';

        if ($promptNamespace === 'prompts') {
            $path = app_path('Mcp/Prompts');
            $namespace = 'App\\Mcp\\Prompts';

            $this->discoverAndRegisterClasses(
                $server,
                $path,
                $namespace,
                BasePrompt::class,
                [$this, 'registerPrompt']
            );
        }
    }

    /**
     * Discover and register classes of a specific type.
     */
    protected function discoverAndRegisterClasses(
        McpServer $server,
        string $path,
        string $namespace,
        string $baseClass,
        callable $registerCallback
    ): void {
        if (!File::isDirectory($path)) {
            return;
        }

        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace([$path, '/'], ['', '\\'], $file->getPathname());
            $className = $namespace . str_replace('.php', '', $relativePath);

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            // Skip abstract classes and interfaces
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            // Check if class extends the base class
            if (!$reflection->isSubclassOf($baseClass)) {
                continue;
            }

            try {
                $instance = $this->app->make($className);
                call_user_func($registerCallback, $server, $instance);
            } catch (\Throwable $e) {
                // Log error but don't break the registration process
                if ($this->app->hasDebugModeEnabled()) {
                    logger()->warning("Failed to register MCP class: {$className}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Register a tool with the server.
     */
    protected function registerTool(McpServer $server, BaseTool $tool): void
    {
        $server->registerTool(
            $tool->name(),
            $tool->toArray(),
            [$tool, 'handle']
        );
    }

    /**
     * Register a resource with the server.
     */
    protected function registerResource(McpServer $server, BaseResource $resource): void
    {
        if ($resource->supportsTemplates()) {
            $server->registerResourceTemplate(
                $resource->uriTemplate(),
                $resource->toArray(),
                [$resource, 'read']
            );
        } else {
            $server->registerResource(
                $resource->uri(),
                $resource->toArray(),
                [$resource, 'read']
            );
        }
    }

    /**
     * Register a prompt with the server.
     */
    protected function registerPrompt(McpServer $server, BasePrompt $prompt): void
    {
        $server->registerPrompt(
            $prompt->name(),
            $prompt->toArray(),
            [$prompt, 'handle']
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            McpServer::class,
            Client::class,
            McpController::class,
            McpAuth::class,
        ];
    }
}