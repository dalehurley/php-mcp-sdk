# PHP MCP SDK Examples

This directory contains comprehensive examples demonstrating the PHP MCP SDK capabilities, including server implementations, client applications, Laravel integration, utilities, and Docker configurations.

## 📁 Directory Structure

```
examples/
├── server/              # MCP server implementations
├── client/              # MCP client applications
├── laravel/             # Laravel integration examples
├── utils/               # Utility tools (inspector, monitor)
├── docker/              # Docker configurations
└── README.md           # This file
```

## 🚀 Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer
- Required PHP extensions: `pcntl`, `sockets`, `pdo_sqlite`

### Installation

```bash
# Install dependencies
composer install

# Make scripts executable (Unix/Linux/macOS)
chmod +x examples/server/*.php
chmod +x examples/client/*.php
chmod +x examples/utils/*.php
```

### Running Your First Example

```bash
# Terminal 1: Start a simple server
php examples/server/simple-server.php

# Terminal 2: Connect with a client
php examples/client/simple-stdio-client.php
```

## 🔧 Server Examples

### Simple Server (`simple-server.php`)

A basic MCP server demonstrating:

- Mathematical calculations
- Static resources
- Prompt templates
- Basic MCP protocol implementation

```bash
php examples/server/simple-server.php
```

**Features:**

- `calculate` tool for mathematical expressions
- Server information resource
- Dynamic data resources
- Greeting prompt template

### Weather Server (`weather-server.php`)

Advanced server with external API integration:

- Weather data retrieval
- API caching and rate limiting
- Error handling
- Multiple tools and endpoints

```bash
# Set API key (optional, uses demo mode without it)
export OPENWEATHER_API_KEY=your_api_key_here
php examples/server/weather-server.php
```

**Features:**

- `current-weather` - Get current weather for a location
- `weather-forecast` - 5-day weather forecast
- `weather-alerts` - Weather alerts and warnings
- `cache-status` - View cache statistics

### Database Server (`sqlite-server.php`)

Database integration example:

- SQLite database operations
- Safe SQL query execution
- Schema inspection
- Search functionality

```bash
php examples/server/sqlite-server.php
```

**Features:**

- `query-select` - Execute safe SELECT queries
- `table-stats` - Database table statistics
- `search` - Search across tables
- Database schema as resource

### OAuth Server (`oauth-server.php`)

Authentication and authorization example:

- OAuth 2.0 implementation
- Protected resources and tools
- Scope-based access control
- Token management

```bash
php examples/server/oauth-server.php
```

**Features:**

- `demo-login` - Get demo access tokens
- `list-users` - List users (requires read scope)
- `update-profile` - Update user profile (requires write scope)
- `admin-stats` - Admin statistics (requires admin scope)

### Resource Server (`resource-server.php`)

Resource management demonstration:

- Static and dynamic resources
- File system access
- Resource subscriptions
- Background resource creation

```bash
php examples/server/resource-server.php
```

**Features:**

- Static configuration and documentation resources
- Dynamic resource creation via tools
- File system directory listing
- Resource change notifications

## 💻 Client Examples

### Simple Client (`simple-stdio-client.php`)

Basic client demonstrating MCP operations:

- Server connection and initialization
- Tool calls and resource access
- Prompt generation
- Error handling

```bash
php examples/client/simple-stdio-client.php
```

### Parallel Tools Client (`parallel-tools-client.php`)

Advanced client showing concurrent operations:

- Multiple simultaneous tool calls
- Performance comparison (sequential vs parallel)
- Notification handling
- Cross-server operations

```bash
# Connect to different servers
php examples/client/parallel-tools-client.php calculator
php examples/client/parallel-tools-client.php weather
php examples/client/parallel-tools-client.php database
```

### OAuth Client (`oauth-client.php`)

Authentication flow demonstration:

- OAuth 2.0 authorization
- Token management and refresh
- Authenticated requests
- Scope-based access testing

```bash
php examples/client/oauth-client.php
```

### HTTP Client (`http-client.php`)

HTTP transport example:

- Streamable HTTP connections
- Server-Sent Events (SSE)
- Session management
- Connection recovery

```bash
# Set server URL (optional)
export MCP_HTTP_SERVER_URL=http://localhost:3000
php examples/client/http-client.php
```

### Multiple Servers Client (`multiple-servers-client.php`)

Multi-server management:

- Sequential and parallel server connections
- Cross-server operations
- Connection health monitoring
- Aggregated results

```bash
php examples/client/multiple-servers-client.php
```

## 🌐 Laravel Integration

### Service Provider (`ExampleMcpServiceProvider.php`)

Laravel service provider demonstrating:

- MCP server registration
- Laravel-specific tools (database, cache, Artisan)
- Resource exposure (config, routes, logs)
- Code generation prompts

### Inertia Controller (`McpDemoController.php`)

Laravel controller with Inertia.js integration:

- Web-based MCP client interface
- Real-time server monitoring
- Interactive tool testing
- Server-Sent Events for notifications

### React Components (`Demo.tsx`)

Inertia.js React component providing:

- Dashboard-style interface
- Server connection management
- Real-time metrics display
- Interactive MCP operations

### Configuration (`mcp-config.php`)

Comprehensive Laravel configuration for:

- Transport settings (HTTP, WebSocket)
- Authentication and authorization
- Rate limiting and caching
- Security and monitoring

## 🛠️ Utility Tools

### Inspector (`utils/inspector.php`)

Comprehensive server analysis tool:

```bash
# Basic inspection
php examples/utils/inspector.php --server=examples/server/simple-server.php

# Interactive mode
php examples/utils/inspector.php --server=examples/server/weather-server.php --interactive

# Generate report
php examples/utils/inspector.php --server=examples/server/sqlite-server.php --report --output=report.json --format=json

# Run all tests
php examples/utils/inspector.php --server=examples/server/oauth-server.php --test-all
```

**Features:**

- Server capability analysis
- Tool, resource, and prompt testing
- Interactive exploration mode
- Comprehensive reporting
- JSON and text output formats

### Monitor (`utils/monitor.php`)

Real-time server monitoring:

```bash
# Basic monitoring
php examples/utils/monitor.php --server=examples/server/simple-server.php

# Dashboard mode with alerts
php examples/utils/monitor.php --server=examples/server/weather-server.php --dashboard --alerts

# JSON output with logging
php examples/utils/monitor.php --server=examples/server/sqlite-server.php --json --log=monitor.log

# Limited duration monitoring
php examples/utils/monitor.php --server=examples/server/oauth-server.php --interval=10 --duration=300
```

**Features:**

- Real-time health monitoring
- Performance metrics tracking
- Alert system for issues
- Dashboard-style display
- Historical data logging

## 🐳 Docker Examples

### Quick Start with Docker

```bash
# Build and start all services
cd examples/docker
docker-compose up -d

# View running services
docker-compose ps

# Check logs
docker-compose logs -f mcp-simple-server

# Connect to client
docker-compose exec mcp-client bash
```

### Individual Services

```bash
# Start specific server
docker-compose up mcp-weather-server

# Run client in different modes
docker-compose run --rm mcp-client
MCP_CLIENT_MODE=inspector docker-compose run --rm mcp-client
MCP_CLIENT_MODE=monitor docker-compose run --rm mcp-client

# Laravel integration
docker-compose up laravel-mcp
# Access at http://localhost:8080
```

### Development Environment

```bash
# Start development environment
docker-compose --profile development up dev-tools

# Interactive development container
docker-compose exec dev-tools bash
```

## 📊 Usage Patterns

### Basic Server-Client Pattern

```php
// Server side
$server = new McpServer($implementation, $options);
$server->tool('my-tool', 'Description', $schema, $handler);
$server->connect(new StdioServerTransport())->await();

// Client side
$client = new Client($implementation, $options);
$client->connect(new StdioClientTransport($serverParams))->await();
$result = $client->callToolByName('my-tool', $params)->await();
```

### Laravel Integration Pattern

```php
// Service Provider
$server = app(McpServer::class);
$server->tool('laravel-tool', 'Description', $schema, function($params) {
    return User::where('name', 'like', "%{$params['search']}%")->get();
});

// Controller
public function callTool(Request $request) {
    $result = $this->mcpClient->callToolByName(
        $request->input('tool_name'),
        $request->input('parameters', [])
    )->await();
    return response()->json(['result' => $result]);
}
```

### Monitoring Pattern

```php
// Health check
async function checkServerHealth($serverConfig) {
    try {
        $client = new Client($implementation, $options);
        $transport = new StdioClientTransport($serverParams);
        $client->connect($transport)->await();

        $tools = $client->listTools()->await();
        $client->close()->await();

        return ['status' => 'healthy', 'tools' => count($tools->getTools())];
    } catch (\Exception $e) {
        return ['status' => 'error', 'error' => $e->getMessage()];
    }
}
```

## 🔧 Configuration

### Environment Variables

```bash
# Weather Server
OPENWEATHER_API_KEY=your_api_key_here

# OAuth Server
OAUTH_CLIENT_ID=your_client_id
OAUTH_CLIENT_SECRET=your_client_secret
OAUTH_ISSUER=https://your-auth-server.com

# HTTP Client
MCP_HTTP_SERVER_URL=http://localhost:3000

# Laravel Integration
MCP_ENABLED=true
MCP_HTTP_ENABLED=true
MCP_AUTH_ENABLED=true
```

### Docker Environment

```bash
# Server Configuration
MCP_SERVER_TYPE=simple|weather|database|oauth|resource
MCP_LOG_LEVEL=info|debug|error
MCP_ENVIRONMENT=production|development

# Client Configuration
MCP_CLIENT_MODE=interactive|parallel|oauth|http|inspector|monitor
MCP_TARGET_SERVER=simple-server|weather-server|database-server
```

## 📚 Learning Path

1. **Start with Simple Examples**

   - Run `simple-server.php` and `simple-stdio-client.php`
   - Understand basic MCP concepts

2. **Explore Advanced Features**

   - Try the weather server for API integration
   - Use the database server for data operations

3. **Test Authentication**

   - Run the OAuth server and client
   - Understand scope-based access control

4. **Use Utility Tools**

   - Inspect servers with the inspector tool
   - Monitor performance with the monitor tool

5. **Try Laravel Integration**

   - Set up the Laravel service provider
   - Use the Inertia.js interface

6. **Deploy with Docker**
   - Use Docker Compose for full stack deployment
   - Scale with multiple server instances

## 🐛 Troubleshooting

### Common Issues

**Server won't start:**

```bash
# Check PHP syntax
php -l examples/server/simple-server.php

# Check required extensions
php -m | grep -E "(pcntl|sockets|pdo_sqlite)"
```

**Client connection fails:**

```bash
# Verify server is running
ps aux | grep php

# Check for port conflicts
netstat -tulpn | grep :3000
```

**Permission errors:**

```bash
# Make scripts executable
chmod +x examples/server/*.php examples/client/*.php examples/utils/*.php

# Check file permissions
ls -la examples/server/
```

**Docker issues:**

```bash
# Rebuild containers
docker-compose build --no-cache

# Check container logs
docker-compose logs mcp-simple-server

# Clean up
docker-compose down -v
docker system prune
```

### Performance Tips

1. **Use parallel operations** when calling multiple tools
2. **Enable caching** for frequently accessed resources
3. **Monitor memory usage** with long-running servers
4. **Use connection pooling** for multiple clients
5. **Implement proper error handling** and retries

## 📖 Additional Resources

- [PHP MCP SDK Documentation](../../README.md)
- [MCP Protocol Specification](https://modelcontextprotocol.io)
- [TypeScript SDK Examples](https://github.com/modelcontextprotocol/typescript-sdk/tree/main/src/examples)
- [Laravel Documentation](https://laravel.com/docs)
- [Inertia.js Documentation](https://inertiajs.com)

## 🤝 Contributing

Found an issue or want to improve an example?

1. Check existing examples for patterns
2. Follow PSR-12 coding standards
3. Include proper error handling
4. Add documentation and comments
5. Test with different configurations
6. Update this README if needed

## 📄 License

These examples are part of the PHP MCP SDK and follow the same license terms.
