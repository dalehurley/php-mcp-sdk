# PHP MCP SDK Documentation

Welcome to the PHP implementation of the Model Context Protocol (MCP) SDK. This documentation will help you build robust integrations between LLM applications and external data sources and tools.

## 🚀 Quick Navigation

### Getting Started

- [Installation & Setup](getting-started/installation.md)
- [Quick Start Guide](getting-started/quick-start.md)
- [Core Concepts](getting-started/concepts.md)

### Implementation Guides

- [Creating MCP Servers](guides/creating-servers.md)
- [Creating MCP Clients](guides/creating-clients.md)
- [Authentication & Security](guides/authentication.md)
- [Transport Layers](guides/transports.md)
- [Laravel Framework Integration](guides/laravel-integration.md)

### API Reference

- [Server API](api/server.md)
- [Client API](api/client.md)
- [Type System](api/types.md)
- [Transport APIs](api/transports.md)

### Examples

- [Code Examples](examples/README.md)

### Migration & Contributing

- [Migrating from TypeScript SDK](migration/from-typescript.md)
- [Contributing Guidelines](contributing.md)

## 📋 Requirements

- **PHP 8.1+** - Leverages modern PHP features like enums, union types, and attributes
- **Composer** - For dependency management
- **ext-json** - JSON handling
- **ext-mbstring** - String processing

## 🏗️ Architecture Overview

The PHP MCP SDK follows the established patterns from the TypeScript SDK while embracing PHP idioms:

```
┌─────────────────┐    ┌─────────────────┐
│   MCP Client    │◄──►│   MCP Server    │
│                 │    │                 │
│ ┌─────────────┐ │    │ ┌─────────────┐ │
│ │ Transport   │ │    │ │ Transport   │ │
│ │ (STDIO/HTTP)│ │    │ │ (STDIO/HTTP)│ │
│ └─────────────┘ │    │ └─────────────┘ │
│                 │    │                 │
│ ┌─────────────┐ │    │ ┌─────────────┐ │
│ │ Protocol    │ │    │ │ Protocol    │ │
│ │ Handler     │ │    │ │ Handler     │ │
│ └─────────────┘ │    │ └─────────────┘ │
└─────────────────┘    └─────────────────┘
           │                      │
           └──── JSON-RPC 2.0 ────┘
```

## 🔧 Key Features

### Type Safety

- PHP 8.1+ strict typing with enums, union types, and attributes
- Comprehensive input validation using Respect/Validation
- IDE-friendly with full PHPDoc coverage

### Async Operations

- Built on Amphp for non-blocking I/O
- Promise-based API similar to JavaScript/TypeScript
- Efficient handling of concurrent operations

### Multiple Transports

- **STDIO** - Process-based communication
- **HTTP Streaming** - Web-based integration
- **WebSocket** - Real-time bidirectional communication (coming soon)

### Authentication

- OAuth 2.0 with PKCE support
- Custom authentication providers
- Secure token handling

### Framework Integration

- First-class Laravel support with service provider
- Artisan commands for server management
- InertiaJS integration for frontend communication

## 🎯 Common Use Cases

### Development Tools

```php
// Code analysis, formatting, linting
$server->registerTool('format-code', $config, $handler);
```

### Data Access

```php
// Database queries, API integrations, file system access
$server->registerResource('database', $config, $handler);
```

### AI Assistants

```php
// Knowledge base integration, search, context provision
$server->registerPrompt('knowledge-base', $config, $handler);
```

### System Integration

```php
// External service communication, webhook handling
$server->registerTool('send-webhook', $config, $handler);
```

## 🔄 Protocol Compatibility

This SDK implements the complete MCP specification:

- ✅ **Tools** - Callable functions with structured input/output
- ✅ **Resources** - Data sources with metadata and content
- ✅ **Prompts** - Templates with dynamic arguments
- ✅ **Sampling** - LLM text generation coordination
- ✅ **Logging** - Structured event collection
- ✅ **Authentication** - OAuth 2.0 and custom providers
- ✅ **Cancellation** - Request lifecycle management

## 🛡️ Security Considerations

- Input validation on all user data
- Authentication token secure storage
- Transport layer encryption (HTTPS/WSS)
- Rate limiting and request timeouts
- Audit logging for sensitive operations

## 📊 Performance

- Async I/O prevents blocking operations
- Connection pooling for HTTP transports
- Efficient JSON parsing and serialization
- Memory-conscious streaming for large datasets
- Built-in request debouncing and batching

## 🤝 Community

- [GitHub Issues](https://github.com/dalehurley/php-mcp-sdk/issues) - Bug reports and feature requests
- [Discussions](https://github.com/dalehurley/php-mcp-sdk/discussions) - Community support
- [Contributing Guide](contributing.md) - How to contribute
- [Code of Conduct](CODE_OF_CONDUCT.md) - Community guidelines

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](../LICENSE) file for details.

---

_This documentation is continuously updated. If you find any issues or have suggestions, please [open an issue](https://github.com/dalehurley/php-mcp-sdk/issues)._
