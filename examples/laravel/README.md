# Laravel Integration Examples

This directory contains examples showing how to integrate the core PHP MCP SDK with Laravel applications.

## Overview

These examples demonstrate how to use the PHP MCP SDK directly in Laravel without requiring the separate `laravel-mcp-sdk` package. This approach gives you full control over the integration while leveraging Laravel's features.

## Files

### `McpController.php`

Complete Laravel controller example showing:

- MCP server initialization
- Tool registration using Laravel models (User, Post)
- Cache operations using Laravel's cache system
- Resource registration
- HTTP request handling
- Error handling and JSON responses

### `McpServiceProvider.php`

Laravel service provider example showing:

- Registering MCP server as singleton in container
- Configuration publishing
- Service aliases for dependency injection

### `mcp-config.php`

Comprehensive configuration file showing:

- Server settings
- Transport configuration (STDIO, HTTP)
- Authentication options
- Tool and resource configuration
- Performance and security settings
- Environment variable integration

### `routes.php`

Laravel routes example showing:

- MCP endpoint setup
- RESTful tool and resource endpoints
- Health check endpoints
- Middleware integration options

## Quick Start

### 1. Install the Core SDK

```bash
composer require mcp/php-sdk
```

### 2. Add Service Provider

Add to `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\McpServiceProvider::class,
],
```

### 3. Copy Configuration

Copy `mcp-config.php` to `config/mcp.php` in your Laravel app.

### 4. Add Routes

Add the routes to your `routes/web.php` or `routes/api.php`:

```php
require __DIR__ . '/mcp.php';
```

### 5. Create Controller

Copy `McpController.php` to `app/Http/Controllers/` and adjust the namespace and model imports as needed.

## Environment Configuration

Add these variables to your `.env` file:

```env
# MCP Server Configuration
MCP_SERVER_NAME="My Laravel MCP Server"
MCP_SERVER_VERSION="1.0.0"
MCP_TRANSPORT=http
MCP_HTTP_PORT=3000

# Authentication (optional)
MCP_AUTH_ENABLED=false
MCP_AUTH_BEARER_TOKEN=your-secret-token

# Tools Configuration
MCP_TOOL_USER_SEARCH_ENABLED=true
MCP_TOOL_CACHE_ENABLED=true
MCP_TOOL_DATABASE_ENABLED=false

# Performance
MCP_CACHE_ENABLED=true
MCP_RATE_LIMITING_ENABLED=true
```

## Usage Examples

### Using in Laravel Controller

```php
<?php

namespace App\Http\Controllers;

use MCP\Server\McpServer;

class ApiController extends Controller
{
    public function __construct(
        private McpServer $mcpServer
    ) {}

    public function searchUsers(Request $request)
    {
        $result = $this->mcpServer->callTool('search-users', [
            'query' => $request->input('query'),
            'limit' => $request->input('limit', 10)
        ]);

        return response()->json($result);
    }
}
```

### Using in Laravel Command

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MCP\Server\McpServer;

class McpServerCommand extends Command
{
    protected $signature = 'mcp:serve';
    protected $description = 'Start the MCP server';

    public function handle(McpServer $server)
    {
        $this->info('Starting MCP server...');

        // Start STDIO transport for command-line usage
        $transport = new \MCP\Shared\StdioServerTransport();
        $server->connect($transport)->await();

        $this->info('MCP server started');
    }
}
```

### Using in Laravel Job

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use MCP\Server\McpServer;

class ProcessMcpRequest implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(McpServer $server)
    {
        // Process MCP requests asynchronously
        $result = $server->callTool('heavy-computation', $this->params);

        // Store or broadcast result
        cache()->put("mcp_result_{$this->requestId}", $result, 3600);
    }
}
```

## Testing

### Unit Testing

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use MCP\Server\McpServer;

class McpIntegrationTest extends TestCase
{
    public function test_mcp_server_can_be_resolved()
    {
        $server = $this->app->make(McpServer::class);

        $this->assertInstanceOf(McpServer::class, $server);
    }

    public function test_user_search_tool_works()
    {
        $server = $this->app->make(McpServer::class);

        $result = $server->callTool('search-users', [
            'query' => 'john',
            'limit' => 5
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('users', $result);
    }
}
```

### Feature Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class McpHttpTest extends TestCase
{
    public function test_mcp_endpoint_returns_server_info()
    {
        $response = $this->get('/mcp/info');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'name',
                    'version',
                    'tools_count',
                    'resources_count'
                ]);
    }

    public function test_tool_can_be_called_via_http()
    {
        $response = $this->postJson('/mcp/', [
            'method' => 'tools/call',
            'params' => [
                'name' => 'search-users',
                'arguments' => ['query' => 'test']
            ]
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure(['content']);
    }
}
```

## Advanced Features

### Custom Tool Registration

```php
// In your service provider or controller
$server->registerTool('custom-tool', $toolDefinition, function($params) {
    // Use any Laravel features
    $user = auth()->user();
    $data = DB::table('custom_table')->where('user_id', $user->id)->get();

    return [
        'data' => $data,
        'user' => $user->name
    ];
});
```

### Middleware Integration

```php
// In routes/mcp.php
Route::post('/mcp', [McpController::class, 'handle'])
    ->middleware(['auth:sanctum', 'throttle:100,1']);
```

### Event Broadcasting

```php
// In your MCP tool handler
broadcast(new McpToolExecuted($toolName, $params, $result));
```

## Differences from Full Laravel Package

This integration approach:

- ✅ Uses core PHP MCP SDK directly
- ✅ Full control over implementation
- ✅ Minimal dependencies
- ✅ Easy to customize

The separate `laravel-mcp-sdk` package provides:

- 🏗️ Automatic service discovery
- 🎨 Artisan commands for scaffolding
- 📦 Pre-built tools and resources
- 🖥️ Web dashboard interface
- 🔐 Advanced authentication features

Choose the approach that best fits your needs!
