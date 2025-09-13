# Code Examples

This directory contains working examples of MCP servers and clients using the PHP MCP SDK.

## 📁 Example Categories

### Basic Examples
- [Simple Weather Server](basic/weather-server.md) - Basic tool and resource implementation
- [Echo Client](basic/echo-client.md) - Simple client that calls tools
- [File System Server](basic/filesystem-server.md) - Resource templates and file access

### Advanced Examples  
- [Database Server](advanced/database-server.md) - Database integration with tools and resources
- [Authentication Server](advanced/auth-server.md) - OAuth 2.0 authentication
- [Multi-Transport Server](advanced/multi-transport.md) - Supporting multiple transport types
- [Batch Processing Client](advanced/batch-client.md) - Concurrent operations and error handling

### Framework Integration
- [Laravel MCP Server](laravel/laravel-server.md) - Laravel service provider integration
- [Symfony MCP Bundle](symfony/symfony-bundle.md) - Symfony framework integration

### Real-World Applications
- [Code Analysis Server](real-world/code-analysis.md) - Static analysis tools
- [API Gateway Client](real-world/api-gateway.md) - REST API integration
- [Documentation Generator](real-world/doc-generator.md) - LLM-powered documentation

## 🚀 Running Examples

### Prerequisites
```bash
# Install the SDK
composer require dalehurley/php-mcp-sdk

# Clone examples (if not already available)
git clone https://github.com/dalehurley/php-mcp-sdk.git
cd php-mcp-sdk/examples
```

### Basic Usage

1. **Start a server:**
   ```bash
   php examples/basic/weather-server.php
   ```

2. **Test with MCP Inspector:**
   ```bash
   npx @modelcontextprotocol/inspector ./examples/basic/weather-server.php
   ```

3. **Use with a client:**
   ```bash
   php examples/basic/echo-client.php
   ```

## 📋 Example Structure

Each example includes:

- **README.md** - Description and usage instructions
- **Server implementation** - Complete server code
- **Client implementation** - Example client usage
- **Docker setup** (where applicable) - Containerized deployment
- **Tests** - Unit and integration tests

## 🧪 Testing Examples

Run the test suite for all examples:

```bash
# Unit tests
composer test examples/

# Integration tests
composer test-integration examples/

# Specific example
phpunit examples/basic/weather-server/tests/
```

## 📚 Learning Path

Recommended order for studying examples:

1. **[Simple Weather Server](basic/weather-server.md)** - Learn basic concepts
2. **[Echo Client](basic/echo-client.md)** - Understand client-server interaction  
3. **[File System Server](basic/filesystem-server.md)** - Resource templates
4. **[Database Server](advanced/database-server.md)** - Real-world data integration
5. **[Authentication Server](advanced/auth-server.md)** - Security implementation
6. **[Laravel Integration](laravel/laravel-server.md)** - Framework usage

## 🤝 Contributing Examples

We welcome example contributions! Please:

1. Follow the established structure
2. Include comprehensive documentation  
3. Add unit and integration tests
4. Use realistic, practical scenarios
5. Follow PSR-12 coding standards

### Example Template

```php
#!/usr/bin/env php
<?php
/**
 * Example: [Name]
 * 
 * Description: [Brief description of what this example demonstrates]
 * 
 * Usage: php example-name.php
 * Test: npx @modelcontextprotocol/inspector ./example-name.php
 * 
 * @author Your Name
 * @license MIT
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Types\Implementation;
// ... other imports

// Server setup
$server = new McpServer(
    new Implementation('example-name', '1.0.0', 'Example Description')
);

// Implementation...

// Start server
// ...
```

## 🐛 Reporting Issues

If you find issues with examples:

1. Check the [troubleshooting section](../getting-started/quick-start.md#troubleshooting)
2. Verify your PHP version and extensions
3. [Open an issue](https://github.com/dalehurley/php-mcp-sdk/issues) with:
   - Example name and version
   - Error message and stack trace
   - PHP version and environment details
   - Steps to reproduce

## 📖 Additional Resources

- [Getting Started Guide](../getting-started/quick-start.md)
- [Server API Reference](../api/server.md)
- [Client API Reference](../api/client.md)
- [Best Practices Guide](../guides/creating-servers.md)

Happy coding with MCP! 🎯