# Laravel Integration Guide

This guide covers how to integrate the PHP MCP SDK with Laravel applications, providing powerful AI agent capabilities within your Laravel ecosystem.

> **Note**: This guide focuses on integrating the core PHP MCP SDK directly with Laravel. A dedicated `laravel-mcp-sdk` package with additional Laravel-specific features and conveniences is planned for future release.

## Overview

The PHP MCP SDK can be integrated with Laravel in multiple ways:

1. **Direct Integration** - Use the core SDK directly (recommended approach)
2. **Service Provider Pattern** - Register MCP as a Laravel service
3. **Artisan Commands** - Create CLI tools for MCP servers
4. **HTTP Controllers** - Handle MCP requests via web endpoints

## Quick Start

### Installation

Install the core PHP MCP SDK:

```bash
composer require dalehurley/php-mcp-sdk
```

## Core SDK Integration

### 1. Service Provider Setup

Create a service provider to register MCP services:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MCP\Server\McpServer;
use MCP\Types\Implementation;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(McpServer::class, function ($app) {
            $server = new McpServer(
                new Implementation(
                    config('app.name') . '-mcp',
                    '1.0.0',
                    'Laravel MCP Server'
                )
            );

            $this->registerTools($server);
            $this->registerResources($server);

            return $server;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\McpServeCommand::class,
            ]);
        }
    }

    private function registerTools(McpServer $server): void
    {
        // Register your Laravel-specific tools here
        $server->tool(
            'laravel_users',
            'Get Laravel users',
            ['type' => 'object', 'properties' => []],
            function () {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Users: ' . \App\Models\User::count(),
                        ],
                    ],
                ];
            }
        );
    }

    private function registerResources(McpServer $server): void
    {
        $server->resource(
            'Laravel App Info',
            'laravel://app-info',
            [
                'title' => 'Laravel Application Information',
                'mimeType' => 'application/json',
            ],
            function () {
                return json_encode([
                    'name' => config('app.name'),
                    'version' => config('app.version', '1.0.0'),
                    'environment' => app()->environment(),
                ]);
            }
        );
    }
}
```

Register the provider in `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\McpServiceProvider::class,
],
```

### 2. Artisan Command

Create a command to run the MCP server:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;

class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve';
    protected $description = 'Start the MCP server';

    public function handle(McpServer $server): void
    {
        $this->info('Starting Laravel MCP server...');

        $transport = new StdioServerTransport();
        $server->connect($transport)->await();
    }
}
```

### 3. HTTP Controller Integration

For HTTP-based MCP servers:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MCP\Server\McpServer;

class McpController extends Controller
{
    public function __construct(
        private McpServer $server
    ) {}

    public function handle(Request $request)
    {
        // Handle MCP HTTP requests
        return response()->json(
            $this->server->handleHttpRequest($request->all())
        );
    }

    public function info()
    {
        return response()->json([
            'name' => $this->server->getName(),
            'version' => $this->server->getVersion(),
            'tools' => $this->server->getToolNames(),
            'resources' => $this->server->getResourceNames(),
        ]);
    }
}
```

Add routes in `routes/web.php` or `routes/api.php`:

```php
Route::post('/mcp', [App\Http\Controllers\McpController::class, 'handle']);
Route::get('/mcp/info', [App\Http\Controllers\McpController::class, 'info']);
```

## Laravel-Specific Features

### Database Integration

Leverage Laravel's Eloquent ORM:

```php
$server->tool(
    'get_users',
    'Get users from database',
    [
        'type' => 'object',
        'properties' => [
            'role' => ['type' => 'string', 'description' => 'Filter by role'],
            'limit' => ['type' => 'integer', 'default' => 10],
        ],
    ],
    function (array $args) {
        $query = \App\Models\User::query();

        if (isset($args['role'])) {
            $query->where('role', $args['role']);
        }

        $users = $query->limit($args['limit'] ?? 10)->get();

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $users->map(fn($u) => "• {$u->name} ({$u->email})")->join("\n"),
                ],
            ],
        ];
    }
);
```

### Cache Integration

Use Laravel's cache system:

```php
$server->tool(
    'cached_stats',
    'Get cached application statistics',
    ['type' => 'object', 'properties' => []],
    function () {
        return cache()->remember('app_stats', 3600, function () {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'users' => \App\Models\User::count(),
                            'posts' => \App\Models\Post::count(),
                            'updated' => now()->toDateTimeString(),
                        ], JSON_PRETTY_PRINT),
                    ],
                ],
            ];
        });
    }
);
```

### Queue Integration

Process MCP requests asynchronously:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMcpRequest implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $toolName,
        private array $args
    ) {}

    public function handle(McpServer $server): void
    {
        $result = $server->callTool($this->toolName, $this->args);

        // Store result or broadcast to users
        cache()->put("mcp_result_{$this->job->getJobId()}", $result, 3600);
    }
}

// In your tool handler
$server->tool('async_task', 'Run async task', $schema, function ($args) {
    $job = ProcessMcpRequest::dispatch('heavy_computation', $args);

    return [
        'content' => [
            [
                'type' => 'text',
                'text' => "Task queued with ID: {$job->getJobId()}",
            ],
        ],
    ];
});
```

### Validation Integration

Use Laravel's validation:

```php
$server->tool(
    'create_user',
    'Create a new user',
    $schema,
    function (array $args) {
        $validator = validator($args, [
            'name' => 'required|string|min:2',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Validation errors:\n" .
                            $validator->errors()->all()->join("\n"),
                    ],
                ],
            ];
        }

        $user = \App\Models\User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'password' => bcrypt($args['password']),
        ]);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "User created: {$user->name}",
                ],
            ],
        ];
    }
);
```

## Configuration

Create `config/mcp.php`:

```php
<?php

return [
    'server' => [
        'name' => env('MCP_SERVER_NAME', config('app.name') . '-mcp'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'description' => env('MCP_SERVER_DESCRIPTION', 'Laravel MCP Server'),
    ],

    'transport' => [
        'default' => env('MCP_TRANSPORT', 'stdio'),
        'stdio' => [],
        'http' => [
            'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
            'port' => env('MCP_HTTP_PORT', 3000),
        ],
    ],

    'auth' => [
        'enabled' => env('MCP_AUTH_ENABLED', false),
        'bearer_token' => env('MCP_AUTH_BEARER_TOKEN'),
    ],

    'features' => [
        'database_tools' => env('MCP_DB_TOOLS_ENABLED', true),
        'cache_tools' => env('MCP_CACHE_TOOLS_ENABLED', true),
        'queue_tools' => env('MCP_QUEUE_TOOLS_ENABLED', false),
    ],
];
```

Add to your `.env`:

```env
MCP_SERVER_NAME="My Laravel MCP Server"
MCP_TRANSPORT=stdio
MCP_AUTH_ENABLED=false
MCP_DB_TOOLS_ENABLED=true
```

## Testing

### Unit Tests

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use MCP\Server\McpServer;

class McpIntegrationTest extends TestCase
{
    public function test_mcp_server_is_registered(): void
    {
        $server = app(McpServer::class);
        $this->assertInstanceOf(McpServer::class, $server);
    }

    public function test_laravel_tools_are_registered(): void
    {
        $server = app(McpServer::class);
        $tools = $server->getToolNames();

        $this->assertContains('laravel_users', $tools);
    }
}
```

### Feature Tests

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class McpHttpTest extends TestCase
{
    public function test_mcp_info_endpoint(): void
    {
        $response = $this->get('/mcp/info');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'name',
                    'version',
                    'tools',
                    'resources',
                ]);
    }

    public function test_mcp_tool_execution(): void
    {
        $response = $this->postJson('/mcp', [
            'method' => 'tools/call',
            'params' => [
                'name' => 'laravel_users',
                'arguments' => [],
            ],
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure(['content']);
    }
}
```

## Production Deployment

### Environment Setup

```env
# Production .env
MCP_SERVER_NAME="Production Laravel MCP"
MCP_TRANSPORT=http
MCP_HTTP_HOST=0.0.0.0
MCP_HTTP_PORT=3000
MCP_AUTH_ENABLED=true
MCP_AUTH_BEARER_TOKEN=your-secure-production-token
```

### Process Management

Use Supervisor to manage the MCP server:

```ini
[program:laravel-mcp]
command=php /path/to/your/app/artisan mcp:serve
directory=/path/to/your/app
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/laravel-mcp.log
```

### Docker Integration

```dockerfile
FROM php:8.2-cli

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 3000

CMD ["php", "artisan", "mcp:serve"]
```

## Security Considerations

### Authentication

Implement proper authentication for HTTP endpoints:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/mcp', [McpController::class, 'handle']);
});
```

### Rate Limiting

Add rate limiting to prevent abuse:

```php
Route::middleware(['throttle:100,1'])->group(function () {
    Route::post('/mcp', [McpController::class, 'handle']);
});
```

### Input Validation

Always validate MCP tool inputs:

```php
$server->tool('secure_tool', 'Secure tool', $schema, function ($args) {
    // Validate inputs
    $validator = validator($args, [
        'user_id' => 'required|integer|exists:users,id',
        'action' => 'required|string|in:read,write',
    ]);

    if ($validator->fails()) {
        throw new \InvalidArgumentException('Invalid arguments');
    }

    // Check permissions
    if (!auth()->user()->can('perform-action', $args['action'])) {
        throw new \UnauthorizedException('Insufficient permissions');
    }

    // Execute tool logic
    return $result;
});
```

## Examples and Resources

- **[Complete Laravel Integration Example](../examples/laravel/laravel-server.md)** - Full working example
- **[Core PHP MCP SDK](https://github.com/dalehurley/php-mcp-sdk)** - Main SDK repository
- **[Framework Integration Examples](../../examples/framework-integration/)** - More integration patterns

## Next Steps

1. **Try the examples** - Start with the [basic Laravel example](../examples/laravel/laravel-server.md)
2. **Build custom tools** - Create tools specific to your Laravel application
3. **Add authentication** - Implement proper security for production use
4. **Scale up** - Use queues and caching for high-performance scenarios
5. **Contribute** - Help improve the PHP MCP SDK with Laravel-specific features

## Troubleshooting

### Common Issues

1. **Service not found**

   ```bash
   # Clear Laravel caches
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Transport connection failed**

   - Check firewall settings
   - Verify port availability
   - Ensure proper permissions

3. **Tool not found**
   - Verify tool registration in service provider
   - Check for typos in tool names
   - Ensure service provider is registered

### Debug Tips

Enable debug logging:

```php
use Illuminate\Support\Facades\Log;

$server->tool('debug_tool', 'Debug tool', $schema, function ($args) {
    Log::info('MCP tool called', ['args' => $args]);

    // Your tool logic here

    Log::info('MCP tool completed', ['result' => $result]);
    return $result;
});
```

For more help, check the [troubleshooting guide](../getting-started/troubleshooting.md) or [open an issue](https://github.com/dalehurley/php-mcp-sdk/issues).

Happy coding with Laravel and MCP! 🚀
