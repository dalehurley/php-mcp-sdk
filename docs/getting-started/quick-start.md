# Quick Start Guide

Get up and running with the PHP MCP SDK in minutes! This guide covers the essentials for building your first MCP server and client.

## Prerequisites

- PHP 8.1+ with `json`, `mbstring` extensions
- Composer installed
- Basic understanding of PHP async programming

If you haven't installed the SDK yet, see the [Installation Guide](installation.md).

## 🎯 Your First MCP Server

Let's create a simple weather server that demonstrates the core MCP concepts.

### Step 1: Create the Server

Create `weather-server.php`:

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use Amp\Loop;

// Create server with implementation info
$server = new McpServer(
    new Implementation(
        'weather-server',      // Server name
        '1.0.0',              // Version  
        'Simple Weather Server' // Description
    )
);

// Register a tool that clients can call
$server->registerTool(
    'get-weather',            // Tool name
    [
        'title' => 'Get Weather',
        'description' => 'Get current weather for a location',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name or coordinates'
                ],
                'units' => [
                    'type' => 'string', 
                    'enum' => ['celsius', 'fahrenheit'],
                    'default' => 'celsius'
                ]
            ],
            'required' => ['location']
        ]
    ],
    function (array $params): array {
        // Simulate weather API call
        $weather = [
            'location' => $params['location'],
            'temperature' => rand(15, 30),
            'condition' => ['sunny', 'cloudy', 'rainy', 'snowy'][rand(0, 3)],
            'humidity' => rand(40, 80),
            'wind_speed' => rand(5, 25)
        ];
        
        // Convert temperature if requested
        if (isset($params['units']) && $params['units'] === 'fahrenheit') {
            $weather['temperature'] = round($weather['temperature'] * 9/5 + 32);
            $units = '°F';
        } else {
            $units = '°C';
        }

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'location' => $weather['location'],
                    'temperature' => $weather['temperature'] . $units,
                    'condition' => $weather['condition'],
                    'humidity' => $weather['humidity'] . '%',
                    'wind_speed' => $weather['wind_speed'] . ' km/h'
                ], JSON_PRETTY_PRINT)
            ]]
        ];
    }
);

// Register a resource that provides weather data
$server->registerResource(
    'weather://current/{location}',
    [
        'name' => 'Current Weather',
        'description' => 'Current weather conditions for a location',
        'mimeType' => 'application/json'
    ],
    function (string $uri): array {
        // Extract location from URI
        preg_match('/weather:\/\/current\/(.+)/', $uri, $matches);
        $location = urldecode($matches[1] ?? 'Unknown');
        
        $weather = [
            'location' => $location,
            'temperature' => rand(15, 30),
            'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
            'timestamp' => date('c')
        ];

        return [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => 'application/json',
                'text' => json_encode($weather, JSON_PRETTY_PRINT)
            ]]
        ];
    }
);

// Start the server
$transport = new StdioServerTransport();
Amp\async(function() use ($server, $transport) {
    try {
        yield $server->connect($transport);
        error_log("✅ Weather server started and ready for connections");
    } catch (\Exception $e) {
        error_log("❌ Server error: " . $e->getMessage());
    }
});

Loop::run();
```

### Step 2: Make It Executable

```bash
chmod +x weather-server.php
```

### Step 3: Test with MCP Inspector

The MCP Inspector is a great tool for testing your server:

```bash
# Install MCP Inspector (requires Node.js)
npm install -g @modelcontextprotocol/inspector

# Test your server
mcp-inspector ./weather-server.php
```

This opens a web interface where you can:
- View available tools and resources
- Test tool calls with different parameters  
- Inspect the JSON-RPC messages
- Debug any issues

## 🔌 Your First MCP Client

Now let's create a client to interact with our weather server.

Create `weather-client.php`:

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use Amp\Loop;

// Create client instance
$client = new Client(
    new Implementation('weather-client', '1.0.0')
);

// Configure transport to connect to our weather server
$transport = new StdioClientTransport([
    'command' => 'php',
    'args' => [__DIR__ . '/weather-server.php']
]);

Amp\async(function() use ($client, $transport) {
    try {
        // Connect to the server
        echo "🔌 Connecting to weather server...\n";
        yield $client->connect($transport);
        echo "✅ Connected successfully!\n\n";

        // Get server information
        $serverInfo = yield $client->initialize();
        echo "📊 Server Info:\n";
        echo "   Name: {$serverInfo['serverInfo']['name']}\n";
        echo "   Version: {$serverInfo['serverInfo']['version']}\n\n";

        // List available tools
        echo "🔧 Available Tools:\n";
        $toolsResult = yield $client->listTools();
        foreach ($toolsResult['tools'] as $tool) {
            echo "   - {$tool['name']}: {$tool['description']}\n";
        }
        echo "\n";

        // List available resources
        echo "📦 Available Resources:\n";
        $resourcesResult = yield $client->listResources();
        foreach ($resourcesResult['resources'] as $resource) {
            echo "   - {$resource['uri']}: {$resource['name']}\n";
        }
        echo "\n";

        // Call the weather tool
        echo "🌤️  Getting weather for London...\n";
        $weatherResult = yield $client->callTool('get-weather', [
            'location' => 'London, UK',
            'units' => 'celsius'
        ]);

        echo "Weather Result:\n";
        echo $weatherResult['content'][0]['text'] . "\n\n";

        // Read a weather resource
        echo "📖 Reading weather resource for Paris...\n";
        $resourceResult = yield $client->readResource('weather://current/Paris%2C%20France');
        
        echo "Resource Content:\n";
        echo $resourceResult['contents'][0]['text'] . "\n\n";

        // Test error handling
        echo "🧪 Testing error handling...\n";
        try {
            yield $client->callTool('nonexistent-tool');
        } catch (\MCP\Types\McpError $e) {
            echo "Expected error caught: {$e->getMessage()}\n";
        }

        // Clean shutdown
        echo "\n🔌 Disconnecting...\n";
        yield $client->close();
        echo "✅ Disconnected successfully!\n";

    } catch (\Exception $error) {
        echo "❌ Client error: " . $error->getMessage() . "\n";
        error_log($error->getTraceAsString());
    } finally {
        Loop::stop();
    }
});

Loop::run();
```

### Step 4: Run the Client

```bash
chmod +x weather-client.php
php weather-client.php
```

You should see output showing the client connecting, discovering tools/resources, making calls, and handling responses.

## 🏗️ Understanding the Code

### Server Components

1. **Implementation**: Describes your server (name, version, description)
2. **Tools**: Callable functions that clients can invoke
3. **Resources**: Data sources that clients can read
4. **Transport**: Communication layer (STDIO, HTTP, WebSocket)

### Tool Registration

```php
$server->registerTool(
    $name,        // Unique tool identifier
    $config,      // JSON schema and metadata
    $handler      // Function that processes calls
);
```

### Resource Registration

```php
$server->registerResource(
    $uriTemplate, // URI pattern with placeholders
    $config,      // Resource metadata  
    $handler      // Function that provides content
);
```

### Client Operations

```php
// Connect to server
yield $client->connect($transport);

// Discover capabilities
$tools = yield $client->listTools();
$resources = yield $client->listResources();

// Use capabilities
$result = yield $client->callTool($name, $params);
$content = yield $client->readResource($uri);
```

## 🚀 Advanced Features

### Adding Prompts

Prompts are templates that help LLMs understand how to use your server:

```php
$server->registerPrompt(
    'weather-analysis',
    [
        'name' => 'Weather Analysis Prompt',
        'description' => 'Analyze weather data for insights',
        'arguments' => [
            [
                'name' => 'location',
                'description' => 'Location to analyze',
                'required' => true
            ],
            [
                'name' => 'days',
                'description' => 'Number of days to analyze',
                'required' => false
            ]
        ]
    ],
    function (array $arguments): array {
        $location = $arguments['location'];
        $days = $arguments['days'] ?? 7;
        
        return [
            'description' => "Weather analysis for {$location}",
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        'type' => 'text',
                        'text' => "Analyze weather patterns for {$location} over {$days} days. Provide insights about trends, recommendations for activities, and any notable conditions."
                    ]
                ]
            ]
        ];
    }
);
```

### Error Handling

```php
use MCP\Types\McpError;
use MCP\Types\ErrorCode;

$server->registerTool(
    'validated-tool',
    $config,
    function (array $params): array {
        // Validate required parameters
        if (!isset($params['location'])) {
            throw new McpError(
                ErrorCode::InvalidParams,
                'Missing required parameter: location'
            );
        }
        
        // Your tool logic here...
        
        return $result;
    }
);
```

### Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('weather-server');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

$server->setLogger($logger);
```

## 🧪 Testing Your Implementation

### Unit Tests

Create `tests/WeatherServerTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;
use MCP\Server\McpServer;
use MCP\Types\Implementation;

class WeatherServerTest extends TestCase
{
    public function testServerCreation(): void
    {
        $server = new McpServer(
            new Implementation('test-server', '1.0.0')
        );
        
        $this->assertInstanceOf(McpServer::class, $server);
    }
    
    public function testToolRegistration(): void
    {
        $server = new McpServer(
            new Implementation('test-server', '1.0.0')
        );
        
        $tool = $server->registerTool(
            'test-tool',
            ['description' => 'Test tool'],
            fn($params) => ['content' => [['type' => 'text', 'text' => 'test']]]
        );
        
        $this->assertEquals('test-tool', $tool->getName());
    }
}
```

Run tests:
```bash
vendor/bin/phpunit tests/
```

### Integration Tests

Test with real MCP clients:

```bash
# Test with Python MCP client
pip install mcp
python -c "
import asyncio
from mcp import StdioClientTransport, Client

async def test():
    transport = StdioClientTransport('php', ['./weather-server.php'])
    client = Client()
    await client.connect(transport)
    result = await client.call_tool('get-weather', {'location': 'Tokyo'})
    print(result)

asyncio.run(test())
"
```

## 🔄 Next Steps

Now that you have a working MCP server and client:

1. [📖 Learn Core Concepts](concepts.md) - Deeper understanding of MCP
2. [🖥️ Advanced Server Guide](../guides/creating-servers.md) - Production-ready servers
3. [📱 Advanced Client Guide](../guides/creating-clients.md) - Robust client implementation
4. [🔐 Authentication Guide](../guides/authentication.md) - Secure your MCP services
5. [🏗️ Laravel Integration](../guides/laravel-integration.md) - Framework integration

## 🆘 Troubleshooting

### Common Issues

**Server doesn't start**: Check PHP version and extension requirements
**Client connection fails**: Verify server is executable and paths are correct  
**JSON parsing errors**: Ensure proper UTF-8 encoding in your tool responses
**Memory issues**: Increase PHP memory limit for development

### Debugging Tips

1. **Enable verbose logging**:
   ```bash
   MCP_LOG_LEVEL=debug php weather-server.php
   ```

2. **Use MCP Inspector** to see raw JSON-RPC messages

3. **Add debug output**:
   ```php
   error_log("Debug: " . json_encode($data));
   ```

4. **Test with simple clients** before complex integration

### Getting Help

- [📖 Full Documentation](../README.md)
- [🐛 Report Issues](https://github.com/dalehurley/php-mcp-sdk/issues)  
- [💬 Community Discussions](https://github.com/dalehurley/php-mcp-sdk/discussions)

Happy coding with MCP! 🚀