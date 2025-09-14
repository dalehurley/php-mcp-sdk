# PHP MCP SDK API Reference

Welcome to the comprehensive API reference for the PHP MCP SDK. This reference provides detailed documentation for all classes, methods, and interfaces in the SDK.

## 📚 API Structure

The PHP MCP SDK is organized into several key namespaces:

### Core Components

- **[Server API](server.md)** - `MCP\Server\*` - Complete server implementation
- **[Client API](client.md)** - `MCP\Client\*` - Complete client implementation
- **[Types System](types/)** - `MCP\Types\*` - Type definitions and validation
- **[Transport Layer](transport/)** - `MCP\*\Transport\*` - Communication transports
- **[Authentication](auth/)** - `MCP\Server\Auth\*` - Authentication providers
- **[Utilities](utilities/)** - `MCP\Utils\*` - Helper classes and utilities

## 🚀 Quick Navigation

### Most Common APIs

| Component     | Class                  | Description                       |
| ------------- | ---------------------- | --------------------------------- |
| **Server**    | `McpServer`            | High-level server API             |
| **Client**    | `Client`               | High-level client API             |
| **Transport** | `StdioServerTransport` | STDIO server transport            |
| **Transport** | `StdioClientTransport` | STDIO client transport            |
| **Types**     | `Implementation`       | Server/client implementation info |
| **Types**     | `McpError`             | MCP protocol errors               |

### Server Development

```php
use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;

$server = new McpServer(new Implementation('my-server', '1.0.0'));
$server->tool('my_tool', 'Description', $schema, $handler);
$server->resource('My Resource', 'uri://resource', $metadata, $handler);
$server->prompt('my_prompt', 'Description', $handler);
```

### Client Development

```php
use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;

$client = new Client(new Implementation('my-client', '1.0.0'));
$transport = new StdioClientTransport(['php', 'server.php']);
await $client->connect($transport);
await $client->initialize();
$result = await $client->callTool('tool_name', $parameters);
```

## 📖 Detailed References

### Server Components

- **[McpServer](server.md#mcpserver)** - Main server class
- **[ServerOptions](server.md#serveroptions)** - Server configuration
- **[Tool Registration](server.md#tool-registration)** - Adding tools to servers
- **[Resource Management](server.md#resource-management)** - Managing resources
- **[Prompt Handling](server.md#prompt-handling)** - Creating prompts
- **[Authentication](auth/)** - Server authentication

### Client Components

- **[Client](client.md#client)** - Main client class
- **[Connection Management](client.md#connection-management)** - Connecting to servers
- **[Tool Calling](client.md#tool-calling)** - Executing server tools
- **[Resource Access](client.md#resource-access)** - Reading server resources
- **[Prompt Usage](client.md#prompt-usage)** - Using server prompts

### Transport Layer

- **[STDIO Transport](transport/stdio.md)** - Process-to-process communication
- **[HTTP Transport](transport/http.md)** - Web-based communication
- **[WebSocket Transport](transport/websocket.md)** - Real-time communication
- **[Custom Transports](transport/custom.md)** - Building custom transports

### Type System

- **[Core Types](types/core-types.md)** - Implementation, Capabilities, etc.
- **[Request/Response Types](types/request-response.md)** - Protocol message types
- **[Error Types](types/errors.md)** - Error handling and codes
- **[Validation System](types/validation.md)** - Input/output validation

## 🔧 Code Examples

Each API reference page includes:

- **Complete method signatures** with parameter types
- **Usage examples** with working code
- **Return value documentation** with type information
- **Error handling examples** with exception types
- **Integration patterns** with framework examples

## 🎯 Learning Path

1. **Start with [Server API](server.md)** - Learn server development
2. **Explore [Client API](client.md)** - Understand client integration
3. **Study [Types](types/)** - Master the type system
4. **Review [Transport](transport/)** - Choose the right transport
5. **Implement [Authentication](auth/)** - Add security to your applications

## 📚 Additional Resources

- **[Getting Started Guide](../getting-started/README.md)** - Step-by-step tutorials
- **[Working Examples](../../examples/README.md)** - 20+ tested examples
- **[Framework Guides](../guides/integrations/)** - Laravel, Symfony integration
- **[Agentic AI Tutorial](../tutorials/specialized/agentic-ai-agents.md)** - Build AI agents

---

This API reference is automatically generated from the source code and is always up-to-date with the latest SDK version. All examples are tested and guaranteed to work with the documented APIs.
