# PHP MCP SDK

[![Latest Version](https://img.shields.io/packagist/v/dalehurley/php-mcp-sdk.svg?style=flat-square)](https://packagist.org/packages/dalehurley/php-mcp-sdk)
[![PHP Version](https://img.shields.io/packagist/php-v/dalehurley/php-mcp-sdk.svg?style=flat-square)](https://packagist.org/packages/dalehurley/php-mcp-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/dalehurley/php-mcp-sdk.svg?style=flat-square)](https://packagist.org/packages/dalehurley/php-mcp-sdk)
[![License](https://img.shields.io/packagist/l/dalehurley/php-mcp-sdk.svg?style=flat-square)](https://packagist.org/packages/dalehurley/php-mcp-sdk)
[![Tests](https://github.com/dalehurley/php-mcp-sdk/workflows/Tests/badge.svg)](https://github.com/dalehurley/php-mcp-sdk/actions)

PHP implementation of the Model Context Protocol (MCP), enabling seamless integration between LLM applications and external data sources and tools.

## ✨ Features

- 🚀 **Complete MCP Protocol Support** - Full implementation of the MCP specification
- 🔧 **Type-Safe** - Leverages PHP 8.1+ type system with enums, union types, and strict typing  
- ⚡ **Async First** - Built on Amphp for non-blocking I/O operations
- 🔌 **Multiple Transports** - STDIO, HTTP Streaming, and WebSocket (coming soon)
- 🔐 **OAuth 2.0 Ready** - Built-in authentication with PKCE support
- 🏗️ **Framework Integration** - First-class Laravel package with Artisan commands
- 📦 **PSR Compliant** - Follows PSR-4, PSR-7, PSR-12, and PSR-15 standards
- 🛡️ **Production Ready** - Comprehensive error handling, logging, and monitoring

## 📋 Requirements

- **PHP 8.1+** - Leverages modern PHP features
- **Composer** - For dependency management  
- **ext-json** - JSON processing
- **ext-mbstring** - String handling

## 🚀 Installation

### Via Composer

```bash
composer require dalehurley/php-mcp-sdk
```

### Development Version

```bash
composer require dalehurley/php-mcp-sdk:dev-main
```

## ⚡ Quick Start

### Creating an MCP Server

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use Amp\Loop;

// Create server instance
$server = new McpServer(
    new Implementation('weather-server', '1.0.0', 'Simple Weather Server')
);

// Register a tool
$server->registerTool(
    'get-weather',
    [
        'title' => 'Get Weather',
        'description' => 'Get current weather for a location',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name or coordinates'
                ]
            ],
            'required' => ['location']
        ]
    ],
    function (array $params): array {
        // In a real implementation, you'd call a weather API
        $weather = [
            'temperature' => rand(15, 30) . '°C',
            'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
            'humidity' => rand(40, 80) . '%'
        ];

        return [
            'content' => [[
                'type' => 'text',
                'text' => "Weather in {$params['location']}: " . 
                         "{$weather['temperature']}, {$weather['condition']}, " .
                         "Humidity: {$weather['humidity']}"
            ]]
        ];
    }
);

// Start server with STDIO transport
$transport = new StdioServerTransport();
Amp\async(function() use ($server, $transport) {
    yield $server->connect($transport);
    error_log("Weather server started and listening...");
});

Loop::run();
```

### Creating an MCP Client

```php
#!/usr/bin/env php  
<?php
require_once __DIR__ . '/vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use Amp\Loop;

// Create client
$client = new Client(
    new Implementation('weather-client', '1.0.0')
);

// Connect to weather server
$transport = new StdioClientTransport([
    'command' => 'php',
    'args' => [__DIR__ . '/weather-server.php']
]);

Amp\async(function() use ($client, $transport) {
    try {
        // Connect to server
        yield $client->connect($transport);
        echo "✅ Connected to weather server\n";

        // List available tools
        $result = yield $client->listTools();
        echo "📋 Available tools:\n";
        foreach ($result['tools'] as $tool) {
            echo "  - {$tool['name']}: {$tool['description']}\n";
        }

        // Call the weather tool
        $result = yield $client->callTool('get-weather', [
            'location' => 'London, UK'
        ]);

        echo "\n🌤️  Weather result:\n";
        echo $result['content'][0]['text'] . "\n";

        yield $client->close();
        
    } catch (\Exception $error) {
        echo "❌ Error: " . $error->getMessage() . "\n";
    } finally {
        Loop::stop();
    }
});

Loop::run();
```

### Test Your Implementation

```bash
# Make the server executable
chmod +x weather-server.php

# Test with the MCP Inspector (Node.js required)
npx @modelcontextprotocol/inspector ./weather-server.php

# Or run the client directly
php weather-client.php
```

## Laravel Integration

### Installation

```bash
composer require mcp/php-sdk
php artisan vendor:publish --tag=mcp-config
```

### Configuration

Configure MCP in `config/mcp.php`:

```php
return [
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'laravel-mcp'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
    ],
    'tools' => [
        'search-users' => [
            'definition' => [...],
            'handler' => \App\Mcp\Tools\UserSearchTool::class,
        ],
    ],
];
```

### Using with InertiaJS

```tsx
import { McpClient } from "@/Components/McpClient";

export default function Dashboard() {
  const handleSearch = async (query: string) => {
    const client = new McpClient();
    const result = await client.callTool("search-users", { query });
    console.log(result);
  };

  return <div>{/* Your UI */}</div>;
}
```

## 📚 Documentation

Comprehensive documentation is available in the [docs/](docs/) directory:

### Getting Started
- [📖 Complete Documentation](docs/README.md) - Start here for full overview
- [⚡ Quick Start Guide](docs/getting-started/quick-start.md) - Get up and running fast
- [💡 Core Concepts](docs/getting-started/concepts.md) - Understand MCP fundamentals

### Implementation Guides  
- [🖥️ Creating Servers](docs/guides/creating-servers.md) - Build MCP servers
- [📱 Creating Clients](docs/guides/creating-clients.md) - Build MCP clients
- [🔐 Authentication](docs/guides/authentication.md) - OAuth 2.0 and security
- [🔌 Transports](docs/guides/transports.md) - STDIO, HTTP, WebSocket
- [🏗️ Laravel Integration](docs/guides/laravel-integration.md) - Framework integration

### API Reference
- [🔧 Server API](docs/api/server.md) - Complete server API
- [📡 Client API](docs/api/client.md) - Complete client API  
- [📋 Types & Schemas](docs/api/types.md) - Type system reference
- [🚀 Transport APIs](docs/api/transports.md) - Transport layer APIs

### Examples & Migration
- [💻 Code Examples](docs/examples/README.md) - Working examples
- [🔄 TypeScript Migration](docs/migration/from-typescript.md) - Migration guide

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer phpstan
composer psalm

# Fix code style
composer cs-fix

# Run all checks
composer check
```

## Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) for details.

### Changelog

All notable changes to this project are documented in the [CHANGELOG.md](CHANGELOG.md). Please update it when making changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Based on the [MCP TypeScript SDK](https://github.com/modelcontextprotocol/typescript-sdk)
- Built with [ReactPHP](https://reactphp.org/) for async operations
- Uses [Respect/Validation](https://respect-validation.readthedocs.io/) for schema validation
