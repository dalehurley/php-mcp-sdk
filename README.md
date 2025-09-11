# PHP MCP SDK

PHP implementation of the Model Context Protocol (MCP), enabling seamless integration between LLM applications and external data sources/tools.

## Features

- 🚀 **Full MCP Protocol Support** - Implements the complete MCP specification
- 🔧 **Type-Safe** - Leverages PHP 8+ type system for robust code
- ⚡ **Async Operations** - Built on ReactPHP for non-blocking I/O
- 🔌 **Multiple Transports** - Stdio, HTTP Streaming, and WebSocket support
- 🔐 **OAuth 2.0 Authentication** - Complete auth flow with PKCE support
- 🏗️ **Laravel Integration** - First-class Laravel and InertiaJS support
- 📦 **PSR Compliant** - Follows PSR standards for interoperability

## Requirements

- PHP 8.0 or higher
- Composer
- ext-json
- ext-mbstring

## Installation

```bash
composer require mcp/php-sdk
```

## Quick Start

### Creating an MCP Server

```php
use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;

// Create server instance
$server = new McpServer(
    new Implementation('my-server', '1.0.0')
);

// Register a tool
$server->registerTool(
    'get-weather',
    [
        'title' => 'Get Weather',
        'description' => 'Get weather for a location',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string']
            ],
            'required' => ['location']
        ]
    ],
    function (array $params) {
        return [
            'content' => [[
                'type' => 'text',
                'text' => "The weather in {$params['location']} is sunny!"
            ]]
        ];
    }
);

// Start server
$transport = new StdioServerTransport();
$server->connect($transport)->then(function() {
    echo "MCP Server running...\n";
});
```

### Creating an MCP Client

```php
use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;

// Create client
$client = new Client(
    new Implementation('my-client', '1.0.0')
);

// Connect to server
$transport = new StdioClientTransport([
    'command' => 'node',
    'args' => ['server.js']
]);

$client->connect($transport)->then(function() use ($client) {
    // List available tools
    return $client->listTools();
})->then(function($tools) use ($client) {
    // Call a tool
    return $client->callTool('get-weather', [
        'location' => 'San Francisco'
    ]);
})->then(function($result) {
    echo "Result: " . json_encode($result) . "\n";
});
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

## Documentation

- [Server Implementation Guide](docs/server.md)
- [Client Implementation Guide](docs/client.md)
- [Transport Layers](docs/transports.md)
- [Authentication](docs/auth.md)
- [Laravel Integration](docs/laravel.md)
- [Type System](docs/types.md)

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
