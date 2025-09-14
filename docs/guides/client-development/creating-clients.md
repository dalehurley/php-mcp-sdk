# Complete Client Development Guide

MCP clients are applications that connect to and interact with MCP servers. This comprehensive guide teaches you how to build robust, efficient clients that can discover server capabilities and orchestrate complex workflows.

## 🎯 What You'll Learn

- 📱 **Client Architecture** - Understanding client components
- 🔌 **Connection Management** - Connecting to servers reliably
- 🛠️ **Tool Orchestration** - Calling tools effectively
- 📚 **Resource Access** - Reading server resources
- 💬 **Prompt Usage** - Leveraging server prompts
- 🔄 **Error Handling** - Robust error management
- ⚡ **Async Patterns** - Non-blocking operations

## 📱 Client Fundamentals

### Basic Client Structure

```php
use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use function Amp\async;

// Create client
$client = new Client(
    new Implementation(
        'my-client',        // Client name
        '1.0.0'            // Version
    )
);

// Connect to server
$transport = new StdioClientTransport(['php', 'server.php']);
await $client->connect($transport);
await $client->initialize();

// Use the client
$result = await $client->callTool('tool_name', $parameters);
```

### Complete Client Example

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use function Amp\async;

async(function () {
    try {
        // Create client
        $client = new Client(
            new Implementation('calculator-client', '1.0.0')
        );

        // Connect to calculator server
        $transport = new StdioClientTransport(['php', 'calculator-server.php']);
        await $client->connect($transport);

        echo "✅ Connected to calculator server\n";

        // Initialize connection
        await $client->initialize();
        echo "🔧 Client initialized\n";

        // Discover server capabilities
        $tools = await $client->listTools();
        echo "🛠️ Available tools: " . count($tools['tools']) . "\n";

        foreach ($tools['tools'] as $tool) {
            echo "   • {$tool['name']}: {$tool['description']}\n";
        }

        // Perform calculations
        echo "\n🧮 Performing calculations...\n";

        $addResult = await $client->callTool('add', ['a' => 15, 'b' => 27]);
        echo "Addition: " . $addResult['content'][0]['text'] . "\n";

        $multiplyResult = await $client->callTool('multiply', ['a' => 6, 'b' => 7]);
        echo "Multiplication: " . $multiplyResult['content'][0]['text'] . "\n";

        // Close connection
        await $client->close();
        echo "👋 Connection closed\n";

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
});
```

## 🔌 Connection Management

### Robust Connection Handling

```php
class RobustMCPClient
{
    private Client $client;
    private array $connectionConfig;
    private bool $isConnected = false;

    public function __construct(array $config)
    {
        $this->connectionConfig = $config;
        $this->client = new Client(
            new Implementation($config['client_name'], $config['version'])
        );
    }

    public async function connect(): void
    {
        $maxAttempts = $this->connectionConfig['max_attempts'] ?? 3;
        $retryDelay = $this->connectionConfig['retry_delay'] ?? 1000; // milliseconds

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                echo "🔌 Connection attempt {$attempt}/{$maxAttempts}...\n";

                $transport = new StdioClientTransport($this->connectionConfig['server_command']);
                await $this->client->connect($transport);
                await $this->client->initialize();

                $this->isConnected = true;
                echo "✅ Connected successfully\n";
                return;

            } catch (Exception $e) {
                echo "❌ Attempt {$attempt} failed: " . $e->getMessage() . "\n";

                if ($attempt < $maxAttempts) {
                    echo "⏳ Waiting {$retryDelay}ms before retry...\n";
                    await delay($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                }
            }
        }

        throw new Exception("Failed to connect after {$maxAttempts} attempts");
    }

    public async function callToolSafely(string $toolName, array $args): array
    {
        if (!$this->isConnected) {
            throw new Exception("Client not connected");
        }

        try {
            return await $this->client->callTool($toolName, $args);
        } catch (Exception $e) {
            // Log the error
            error_log("Tool call failed: {$toolName} - " . $e->getMessage());

            // Try to reconnect if connection was lost
            if ($this->isConnectionError($e)) {
                echo "🔄 Connection lost, attempting to reconnect...\n";
                $this->isConnected = false;
                await $this->connect();

                // Retry the tool call
                return await $this->client->callTool($toolName, $args);
            }

            throw $e;
        }
    }

    private function isConnectionError(Exception $e): bool
    {
        $connectionErrors = [
            'Connection refused',
            'Broken pipe',
            'Connection reset',
            'Transport closed'
        ];

        foreach ($connectionErrors as $errorPattern) {
            if (str_contains($e->getMessage(), $errorPattern)) {
                return true;
            }
        }

        return false;
    }
}
```

### Connection Pooling

```php
class MCPConnectionPool
{
    private array $connections = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public async function getConnection(string $serverName): Client
    {
        if (!isset($this->connections[$serverName])) {
            $this->connections[$serverName] = await $this->createConnection($serverName);
        }

        $client = $this->connections[$serverName];

        // Test if connection is still alive
        if (!await $this->isConnectionAlive($client)) {
            echo "🔄 Reconnecting to {$serverName}...\n";
            $this->connections[$serverName] = await $this->createConnection($serverName);
        }

        return $this->connections[$serverName];
    }

    private async function createConnection(string $serverName): Client
    {
        $serverConfig = $this->config['servers'][$serverName];

        $client = new Client(
            new Implementation($this->config['client_name'], $this->config['version'])
        );

        $transport = new StdioClientTransport($serverConfig['command']);
        await $client->connect($transport);
        await $client->initialize();

        return $client;
    }

    private async function isConnectionAlive(Client $client): bool
    {
        try {
            await $client->ping(); // If ping method exists
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
```

## 🛠️ Tool Orchestration Patterns

### Sequential Tool Execution

```php
class ToolOrchestrator
{
    private Client $client;

    public async function executeWorkflow(array $workflow): array
    {
        $results = [];
        $context = [];

        foreach ($workflow as $step) {
            echo "⚡ Executing: {$step['description']}\n";

            try {
                // Resolve parameters from previous results
                $parameters = $this->resolveParameters($step['parameters'], $context);

                // Execute the tool
                $result = await $this->client->callTool($step['tool'], $parameters);

                // Store result for future steps
                $context[$step['id']] = $result;
                $results[] = [
                    'step' => $step,
                    'result' => $result,
                    'success' => true
                ];

                echo "   ✅ Success\n";

            } catch (Exception $e) {
                echo "   ❌ Failed: " . $e->getMessage() . "\n";

                $results[] = [
                    'step' => $step,
                    'error' => $e->getMessage(),
                    'success' => false
                ];

                // Decide whether to continue or abort
                if ($step['critical'] ?? false) {
                    throw new Exception("Critical step failed: {$step['description']}");
                }
            }
        }

        return $results;
    }

    private function resolveParameters(array $parameters, array $context): array
    {
        // Replace placeholders like {{step_1.result.value}}
        array_walk_recursive($parameters, function(&$value) use ($context) {
            if (is_string($value) && preg_match('/\{\{(.+)\}\}/', $value, $matches)) {
                $path = explode('.', $matches[1]);
                $resolved = $context;

                foreach ($path as $key) {
                    $resolved = $resolved[$key] ?? null;
                    if ($resolved === null) break;
                }

                $value = $resolved ?? $value;
            }
        });

        return $parameters;
    }
}

// Usage example
$orchestrator = new ToolOrchestrator($client);

$workflow = [
    [
        'id' => 'step1',
        'description' => 'Calculate base amount',
        'tool' => 'multiply',
        'parameters' => ['a' => 100, 'b' => 1.5],
        'critical' => true
    ],
    [
        'id' => 'step2',
        'description' => 'Add tax',
        'tool' => 'multiply',
        'parameters' => ['a' => '{{step1.result.value}}', 'b' => 1.08],
        'critical' => false
    ]
];

$results = await $orchestrator->executeWorkflow($workflow);
```

### Parallel Tool Execution

```php
use function Amp\Future\await;

class ParallelToolExecutor
{
    private Client $client;

    public async function executeParallel(array $toolCalls): array
    {
        $futures = [];

        // Start all tool calls concurrently
        foreach ($toolCalls as $id => $call) {
            $futures[$id] = async(function() use ($call) {
                try {
                    return await $this->client->callTool($call['tool'], $call['parameters']);
                } catch (Exception $e) {
                    return ['error' => $e->getMessage()];
                }
            });
        }

        // Wait for all to complete
        $results = [];
        foreach ($futures as $id => $future) {
            $results[$id] = await $future;
        }

        return $results;
    }
}

// Usage
$executor = new ParallelToolExecutor($client);

$parallelCalls = [
    'calc1' => ['tool' => 'add', 'parameters' => ['a' => 10, 'b' => 5]],
    'calc2' => ['tool' => 'multiply', 'parameters' => ['a' => 3, 'b' => 7]],
    'calc3' => ['tool' => 'power', 'parameters' => ['base' => 2, 'exponent' => 8]]
];

$results = await $executor->executeParallel($parallelCalls);
```

## 📚 Resource Access Patterns

### Reading Resources

```php
async function readServerResources(Client $client): void
{
    try {
        // List available resources
        $resources = await $client->listResources();
        echo "📚 Available resources: " . count($resources['resources']) . "\n";

        foreach ($resources['resources'] as $resource) {
            echo "📄 {$resource['name']}: {$resource['description']}\n";

            // Read the resource content
            $content = await $client->readResource($resource['uri']);
            echo "   Content type: {$content['mimeType']}\n";
            echo "   Size: " . strlen($content['text']) . " bytes\n\n";
        }

    } catch (Exception $e) {
        echo "❌ Resource access failed: " . $e->getMessage() . "\n";
    }
}
```

### Resource Monitoring

```php
class ResourceMonitor
{
    private Client $client;
    private array $watchedResources = [];

    public async function watchResource(string $uri, callable $onChange): void
    {
        $this->watchedResources[$uri] = [
            'callback' => $onChange,
            'last_content' => null,
            'last_check' => 0
        ];

        // Start monitoring loop
        async(function() use ($uri) {
            while (isset($this->watchedResources[$uri])) {
                try {
                    $content = await $this->client->readResource($uri);
                    $watch = $this->watchedResources[$uri];

                    if ($content['text'] !== $watch['last_content']) {
                        $watch['callback']($uri, $content);
                        $this->watchedResources[$uri]['last_content'] = $content['text'];
                    }

                    $this->watchedResources[$uri]['last_check'] = time();

                } catch (Exception $e) {
                    echo "⚠️ Resource monitoring error for {$uri}: " . $e->getMessage() . "\n";
                }

                await delay(5000); // Check every 5 seconds
            }
        });
    }

    public function stopWatching(string $uri): void
    {
        unset($this->watchedResources[$uri]);
    }
}
```

## 🎯 Best Practices

### ✅ Client Development Do's

- **Always handle connection failures** gracefully
- **Implement retry logic** with exponential backoff
- **Validate server responses** before using them
- **Use connection pooling** for multiple servers
- **Implement proper timeouts** for all operations
- **Log important events** for debugging
- **Handle partial failures** in batch operations
- **Clean up resources** when done

### ❌ Client Development Don'ts

- **Don't assume servers are always available**
- **Don't ignore error responses** from servers
- **Don't block the main thread** with synchronous calls
- **Don't hardcode server configurations**
- **Don't skip input validation** on client side
- **Don't forget to close connections**
- **Don't expose sensitive data** in logs

## 🧪 Testing Clients

### Unit Testing

```php
class ClientTest extends TestCase
{
    public function testToolExecution(): void
    {
        $mockTransport = $this->createMock(Transport::class);
        $client = new Client(new Implementation('test-client', '1.0.0'));

        // Mock successful tool call
        $mockTransport->expects($this->once())
                     ->method('send')
                     ->willReturn(['result' => ['content' => [['type' => 'text', 'text' => 'Success']]]]);

        $client->setTransport($mockTransport);

        $result = $client->callToolDirectly('test_tool', ['param' => 'value']);
        $this->assertEquals('Success', $result['content'][0]['text']);
    }
}
```

## 📚 Related Resources

- [Client API Reference](../../api/client.md)
- [Connection Guide](connecting-servers.md)
- [Error Handling Guide](error-handling.md)
- [Async Patterns Guide](async-patterns.md)

---

**Master these client development patterns to build robust applications that can effectively leverage any MCP server!** 🚀
