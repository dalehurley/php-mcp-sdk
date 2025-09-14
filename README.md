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
- 🔌 **Multiple Transports** - STDIO, HTTP Streaming, and WebSocket
- 🔐 **OAuth 2.0 Ready** - Built-in authentication with PKCE support
- 🏗️ **Framework Integration** - Laravel, Symfony, and PSR-compatible design
- 📦 **PSR Compliant** - Follows PSR-4, PSR-7, PSR-12, and PSR-15 standards
- 🛡️ **Production Ready** - Comprehensive error handling, logging, and monitoring
- 🤖 **Agentic AI Support** - Build intelligent AI agents with MCP tool orchestration
- 🏭 **Real-World Examples** - Complete applications (Blog CMS, Task Manager, API Gateway)
- 📚 **Comprehensive Documentation** - Best-in-class documentation with tested examples
- 🧪 **Automated Testing** - All documentation examples are automatically tested

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
use function Amp\async;

// Create the simplest possible MCP server
$server = new McpServer(
    new Implementation(
        'hello-world-server',
        '1.0.0'
    )
);

// Add a simple "say_hello" tool
$server->tool(
    'say_hello',
    'Says hello to someone',
    [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Name of the person to greet'
            ]
        ],
        'required' => ['name']
    ],
    function (array $args): array {
        $name = $args['name'] ?? 'World';

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Hello, {$name}! 👋 Welcome to MCP!"
                ]
            ]
        ];
    }
);

// Start the server
async(function () use ($server) {
    echo "🚀 Hello World MCP Server starting...\n";

    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
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
# Run the hello-world server
php examples/getting-started/hello-world-server.php

# Test with Claude Desktop by adding to your configuration:
{
  "mcpServers": {
    "hello-world": {
      "command": "php",
      "args": ["/path/to/examples/getting-started/hello-world-server.php"]
    }
  }
}

# Or test with the MCP Inspector (Node.js required)
npx @modelcontextprotocol/inspector examples/getting-started/hello-world-server.php
```

## 🎯 Examples Overview

The PHP MCP SDK includes **20+ comprehensive examples** across all skill levels:

### 🎓 Getting Started (4 examples)

- **Hello World** - Simplest possible server and client
- **Calculator** - Multi-tool server with math operations
- **File Reader** - Secure file system integration
- **Weather Client** - External API integration patterns

### 🏗️ Framework Integration (2 examples)

- **Laravel Integration** - Complete Laravel patterns with service container
- **Symfony Integration** - Full Symfony integration with DI container

### 🤖 Agentic AI (4 examples)

- **Working Agentic Demo** - Rule-based agent reasoning
- **Personal Assistant** - Multi-MCP server coordination
- **Multi-Agent Orchestrator** - Specialized agent coordination
- **OpenAI Integration** - LLM-powered intelligent agents

### 🏭 Real-World Applications (5 examples)

- **Blog CMS** - Complete content management system
- **Task Manager** - Project management with analytics
- **API Gateway** - Enterprise API orchestration
- **Code Analyzer** - Development quality tools
- **Data Pipeline** - ETL and data processing

### 🏢 Enterprise & Production (3 examples)

- **Docker Deployment** - Production containerization
- **Microservices Architecture** - Distributed systems patterns
- **Monitoring & Observability** - Production monitoring

**All examples are tested and working!** 🎉

## Framework Integration

The PHP MCP SDK is designed to work with any PHP framework through its PSR-compliant architecture.

### Laravel Integration

You can use the core PHP MCP SDK directly in Laravel applications:

```bash
composer require mcp/php-sdk
```

```php
// In a Laravel controller or service
use MCP\Server\McpServer;
use MCP\Types\Implementation;

class McpController extends Controller
{
    public function createServer()
    {
        $server = new McpServer(
            new Implementation('my-laravel-app', '1.0.0')
        );

        // Register your tools, resources, and prompts
        $server->registerTool('search-users', function($params) {
            return User::where('name', 'like', "%{$params['query']}%")->get();
        });

        return $server;
    }
}
```

For a complete Laravel package with service providers, Artisan commands, and Laravel-specific features, see the separate `laravel-mcp-sdk` package.

## 📚 Documentation

The **most comprehensive MCP SDK documentation in the ecosystem** is available in the [docs/](docs/) directory:

### 🎓 Getting Started (Beginner-Friendly)

- [📖 Complete Documentation](docs/README.md) - Start here for full overview
- [⚡ Quick Start Guide](docs/getting-started/quick-start.md) - Get up and running in 5 minutes
- [🏗️ First Server](docs/getting-started/first-server.md) - Build your first server in 10 minutes
- [📱 First Client](docs/getting-started/first-client.md) - Build your first client in 10 minutes
- [🧠 Understanding MCP](docs/getting-started/understanding-mcp.md) - Deep dive into MCP protocol
- [💡 Core Concepts](docs/getting-started/concepts.md) - Understand MCP fundamentals
- [🔧 Troubleshooting](docs/getting-started/troubleshooting.md) - Common issues and solutions

### 🏗️ Implementation Guides

- [🖥️ Creating Servers](docs/guides/creating-servers.md) - Build MCP servers
- [📱 Creating Clients](docs/guides/creating-clients.md) - Build MCP clients
- [🔐 Authentication](docs/guides/authentication.md) - OAuth 2.0 and security
- [🔌 Transports](docs/guides/transports.md) - STDIO, HTTP, WebSocket
- [🏗️ Laravel Integration](docs/guides/integrations/laravel-integration.md) - Complete Laravel guide
- [⚡ Symfony Integration](docs/guides/integrations/symfony-integration.md) - Complete Symfony guide
- [🤖 OpenAI Integration](docs/guides/integrations/openai-tool-calling.md) - AI tool calling
- [📊 FullCX Integration](docs/guides/integrations/fullcx-integration.md) - Product management

### 🤖 Agentic AI Development

- [🧠 Build Agentic AI Agents](docs/tutorials/specialized/agentic-ai-agents.md) - Complete agentic AI tutorial
- [🎯 Agent Examples](examples/agentic-ai/) - Working agent implementations
- [🔗 Multi-Agent Systems](examples/agentic-ai/multi-agent-orchestrator.php) - Agent coordination

### 🏭 Real-World Applications

- [📝 Blog CMS](examples/real-world/blog-cms/) - Complete content management system
- [📋 Task Manager](examples/real-world/task-manager/) - Project management system
- [🚀 API Gateway](examples/real-world/api-gateway/) - Enterprise API management
- [🔍 Code Analyzer](examples/real-world/code-analyzer/) - Development quality tools
- [🔄 Data Pipeline](examples/real-world/data-pipeline/) - ETL and data processing

### 🏢 Enterprise & Production

- [🐳 Docker Deployment](examples/enterprise/docker-mcp-deployment.php) - Containerization
- [🏗️ Microservices](examples/enterprise/microservices-mcp-architecture.php) - Distributed systems
- [📊 Monitoring](examples/enterprise/monitoring-observability-mcp.php) - Observability

### 📖 API Reference

- [🔧 Server API](docs/api/server.md) - Complete server API
- [📡 Client API](docs/api/client.md) - Complete client API
- [📋 Types & Schemas](docs/api/types.md) - Type system reference
- [🚀 Transport APIs](docs/api/transports.md) - Transport layer APIs

### 🔄 Migration & Examples

- [💻 Working Examples](examples/README.md) - 20+ tested examples
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
