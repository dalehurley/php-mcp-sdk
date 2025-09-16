# Laravel MCP Server Integration

This guide demonstrates how to integrate the PHP MCP SDK with Laravel applications to create powerful MCP servers.

> **Note**: This guide uses the core PHP MCP SDK directly. A dedicated `laravel-mcp-sdk` package with additional Laravel-specific features is planned for future release.

## Overview

The PHP MCP SDK can be integrated with Laravel in several ways:

1. **Direct Integration** - Use the core SDK directly in your Laravel app (recommended for full control)
2. **Service Provider Pattern** - Register MCP as a Laravel service
3. **Controller-based** - Handle MCP requests through Laravel controllers

## Quick Start

### 1. Installation

```bash
# Install the core PHP MCP SDK
composer require dalehurley/php-mcp-sdk
```

### 2. Basic Laravel MCP Server

Create a simple MCP server that leverages Laravel's features:

```php
<?php

namespace App\Http\Controllers;

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use App\Models\User;
use Illuminate\Http\Request;

class McpController extends Controller
{
    private McpServer $server;

    public function __construct()
    {
        $this->server = new McpServer(
            new Implementation(
                'laravel-mcp-server',
                '1.0.0',
                'Laravel MCP Integration'
            )
        );

        $this->registerTools();
        $this->registerResources();
    }

    private function registerTools(): void
    {
        // Tool: Get Users
        $this->server->tool(
            'get_users',
            'Retrieve users from the database',
            [
                'type' => 'object',
                'properties' => [
                    'role' => [
                        'type' => 'string',
                        'description' => 'Filter users by role',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of users to return',
                        'default' => 10,
                    ],
                ],
            ],
            function (array $args): array {
                $query = User::query();

                if (isset($args['role'])) {
                    $query->where('role', $args['role']);
                }

                $users = $query->limit($args['limit'] ?? 10)->get();

                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Found {$users->count()} users:\n\n" .
                                $users->map(fn($user) => "• {$user->name} ({$user->email})")
                                      ->join("\n"),
                        ],
                    ],
                ];
            }
        );

        // Tool: Create User
        $this->server->tool(
            'create_user',
            'Create a new user',
            [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'User name'],
                    'email' => ['type' => 'string', 'description' => 'User email'],
                    'password' => ['type' => 'string', 'description' => 'User password'],
                ],
                'required' => ['name', 'email', 'password'],
            ],
            function (array $args): array {
                // Use Laravel validation
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
                                'text' => "❌ Validation failed:\n" .
                                    $validator->errors()->all()->join("\n"),
                            ],
                        ],
                    ];
                }

                $user = User::create([
                    'name' => $args['name'],
                    'email' => $args['email'],
                    'password' => bcrypt($args['password']),
                ]);

                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "✅ User created successfully!\n\n" .
                                "ID: {$user->id}\n" .
                                "Name: {$user->name}\n" .
                                "Email: {$user->email}",
                        ],
                    ],
                ];
            }
        );
    }

    private function registerResources(): void
    {
        // Resource: Application Info
        $this->server->resource(
            'Laravel Application Info',
            'laravel://app-info',
            [
                'title' => 'Laravel Application Information',
                'description' => 'Information about the Laravel application',
                'mimeType' => 'application/json',
            ],
            function (): string {
                return json_encode([
                    'app_name' => config('app.name'),
                    'app_version' => config('app.version', '1.0.0'),
                    'environment' => app()->environment(),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'database' => config('database.default'),
                    'cache' => config('cache.default'),
                    'users_count' => User::count(),
                ], JSON_PRETTY_PRINT);
            }
        );
    }

    public function handle(Request $request)
    {
        // Handle HTTP MCP requests
        $transport = new HttpServerTransport();
        return $this->server->handleRequest($request->all());
    }
}
```

### 3. Service Provider Integration

Create a service provider for better Laravel integration:

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
            return new McpServer(
                new Implementation(
                    config('mcp.server.name', 'laravel-mcp'),
                    config('mcp.server.version', '1.0.0'),
                    config('mcp.server.description', 'Laravel MCP Server')
                )
            );
        });
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');
    }
}
```

### 4. Configuration

Create `config/mcp.php`:

```php
<?php

return [
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'laravel-mcp-server'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'description' => env('MCP_SERVER_DESCRIPTION', 'Laravel MCP Server'),
    ],

    'transport' => [
        'default' => env('MCP_TRANSPORT', 'stdio'),
        'http' => [
            'port' => env('MCP_HTTP_PORT', 3000),
            'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
        ],
    ],

    'auth' => [
        'enabled' => env('MCP_AUTH_ENABLED', false),
        'bearer_token' => env('MCP_AUTH_BEARER_TOKEN'),
    ],

    'tools' => [
        'user_management' => env('MCP_TOOL_USER_MANAGEMENT', true),
        'cache_operations' => env('MCP_TOOL_CACHE_OPERATIONS', true),
        'database_queries' => env('MCP_TOOL_DATABASE_QUERIES', false),
    ],

    'resources' => [
        'app_info' => env('MCP_RESOURCE_APP_INFO', true),
        'database_schema' => env('MCP_RESOURCE_DB_SCHEMA', true),
    ],
];
```

### 5. Artisan Command

Create an Artisan command to run the MCP server:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;

class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve {--transport=stdio}';
    protected $description = 'Start the MCP server';

    public function handle(McpServer $server): void
    {
        $this->info('Starting Laravel MCP server...');

        $transport = match ($this->option('transport')) {
            'stdio' => new StdioServerTransport(),
            'http' => new HttpServerTransport(
                config('mcp.transport.http.host'),
                config('mcp.transport.http.port')
            ),
            default => new StdioServerTransport(),
        };

        $server->connect($transport)->await();
    }
}
```

## Advanced Features

### Database Integration

Leverage Laravel's Eloquent ORM and Query Builder:

```php
// Tool using Eloquent relationships
$server->tool('get_user_posts', 'Get posts for a user', $schema, function($args) {
    $user = User::with('posts')->find($args['user_id']);

    if (!$user) {
        throw new \Exception('User not found');
    }

    return [
        'content' => [
            [
                'type' => 'text',
                'text' => "{$user->name} has {$user->posts->count()} posts:\n\n" .
                    $user->posts->map(fn($post) => "• {$post->title}")->join("\n"),
            ],
        ],
    ];
});
```

### Cache Integration

Use Laravel's cache system:

```php
$server->tool('cached_data', 'Get cached data', $schema, function($args) {
    $key = "mcp_data_{$args['type']}";

    return cache()->remember($key, 3600, function() use ($args) {
        // Expensive operation
        return expensive_computation($args);
    });
});
```

### Queue Integration

Process MCP requests asynchronously:

```php
$server->tool('async_task', 'Run async task', $schema, function($args) {
    ProcessMcpTask::dispatch($args);

    return [
        'content' => [
            [
                'type' => 'text',
                'text' => 'Task queued for processing',
            ],
        ],
    ];
});
```

### Middleware Integration

Add authentication and rate limiting:

```php
// In routes/web.php or routes/api.php
Route::post('/mcp', [McpController::class, 'handle'])
    ->middleware(['auth:sanctum', 'throttle:100,1']);
```

## Testing

### Unit Tests

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use MCP\Server\McpServer;

class McpServerTest extends TestCase
{
    public function test_server_can_be_instantiated(): void
    {
        $server = app(McpServer::class);
        $this->assertInstanceOf(McpServer::class, $server);
    }

    public function test_get_users_tool_works(): void
    {
        $server = app(McpServer::class);

        $result = $server->callTool('get_users', ['limit' => 5]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
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
    public function test_mcp_endpoint_works(): void
    {
        $response = $this->postJson('/mcp', [
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_users',
                'arguments' => ['limit' => 1]
            ]
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure(['content']);
    }
}
```

## Production Deployment

### Environment Variables

```env
# .env
MCP_SERVER_NAME="Production Laravel MCP"
MCP_SERVER_VERSION="1.0.0"
MCP_TRANSPORT=http
MCP_HTTP_PORT=3000
MCP_AUTH_ENABLED=true
MCP_AUTH_BEARER_TOKEN=your-secure-token
```

### Supervisor Configuration

```ini
[program:laravel-mcp]
command=php artisan mcp:serve --transport=http
directory=/path/to/your/laravel/app
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/laravel-mcp.log
```

### Docker Setup

```dockerfile
FROM php:8.2-cli

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 3000

CMD ["php", "artisan", "mcp:serve", "--transport=http"]
```

## Examples and Resources

- **[Complete Laravel Integration Example](../../../examples/laravel/)** - Full working example
- **[Framework Integration Example](../../../examples/framework-integration/laravel-mcp-integration.php)** - Standalone integration
- **[Core PHP MCP SDK](https://github.com/dalehurley/php-mcp-sdk)** - Main SDK repository

## Troubleshooting

### Common Issues

1. **Class not found errors**

   - Ensure `composer install` has been run
   - Check autoloader with `composer dump-autoload`

2. **Transport connection issues**

   - Verify port availability for HTTP transport
   - Check firewall settings

3. **Database connection errors**
   - Verify Laravel database configuration
   - Ensure database migrations are run

### Debug Mode

Enable debug logging:

```php
// In your MCP tools
Log::info('MCP tool called', ['tool' => $toolName, 'args' => $args]);
```

## Next Steps

- Contribute to the [PHP MCP SDK](https://github.com/dalehurley/php-mcp-sdk) with Laravel-specific improvements
- Check out [real-world examples](../../../examples/real-world/) for inspiration
- Read the [security best practices guide](../../guides/security/security-best-practices.md)

Happy coding with Laravel and MCP! 🚀
