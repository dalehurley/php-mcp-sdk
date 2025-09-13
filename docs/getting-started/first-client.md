# Build Your First MCP Client in 10 Minutes

Now that you understand MCP servers, let's build a client that can connect to and interact with any MCP server. You'll create a versatile client that can discover and use server capabilities automatically.

## 🎯 What You'll Build

A **Universal MCP Client** that can:

- ✅ **Auto-discover** server capabilities (tools, resources, prompts)
- ✅ **Interactive mode** - Chat with any MCP server
- ✅ **Batch operations** - Execute multiple tools efficiently
- ✅ **Error recovery** - Handle failures gracefully
- ✅ **Resource monitoring** - Watch for changes

## 📋 Prerequisites

- Completed [Build Your First Server](first-server.md) tutorial
- PHP 8.1+ and Composer installed
- 10 minutes of your time

## 🚀 Let's Build!

### Step 1: Create Client Project (1 minute)

```bash
# Create new directory
mkdir my-first-mcp-client
cd my-first-mcp-client

# Initialize project
composer init --name="my-company/mcp-client" --no-interaction
composer require dalehurley/php-mcp-sdk
```

### Step 2: Create the Universal Client (4 minutes)

Create `universal-client.php`:

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;
use function Amp\async;

class UniversalMCPClient
{
    private Client $client;
    private bool $connected = false;
    private array $serverInfo = [];

    public function __construct()
    {
        $this->client = new Client(
            new Implementation(
                'universal-client',
                '1.0.0',
                'Universal MCP Client for testing any server'
            )
        );
    }

    /**
     * Connect to an MCP server
     */
    public function connect(string $serverCommand, array $args = []): \Generator
    {
        echo "🔌 Connecting to MCP server...\n";
        echo "Command: {$serverCommand} " . implode(' ', $args) . "\n\n";

        $transport = new StdioClientTransport([
            'command' => $serverCommand,
            'args' => $args
        ]);

        try {
            $initResult = yield $this->client->connect($transport);
            $this->connected = true;
            $this->serverInfo = [
                'name' => $initResult->serverInfo->name,
                'version' => $initResult->serverInfo->version,
                'capabilities' => $initResult->capabilities
            ];

            echo "✅ Connected successfully!\n";
            echo "Server: {$this->serverInfo['name']} v{$this->serverInfo['version']}\n\n";

            return $initResult;
        } catch (\Exception $e) {
            echo "❌ Connection failed: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * Discover all server capabilities
     */
    public function discoverCapabilities(): \Generator
    {
        if (!$this->connected) {
            throw new \Exception('Not connected to server');
        }

        echo "🔍 Discovering server capabilities...\n\n";

        // Discover tools
        echo "🔧 Available Tools:\n";
        try {
            $toolsResult = yield $this->client->listTools();

            if (empty($toolsResult->tools)) {
                echo "   No tools available\n";
            } else {
                foreach ($toolsResult->tools as $tool) {
                    echo "   📋 {$tool->name}\n";
                    echo "      Description: {$tool->description}\n";

                    if ($tool->inputSchema) {
                        $required = $tool->inputSchema['required'] ?? [];
                        $properties = array_keys($tool->inputSchema['properties'] ?? []);
                        echo "      Parameters: " . implode(', ', $properties) . "\n";
                        if (!empty($required)) {
                            echo "      Required: " . implode(', ', $required) . "\n";
                        }
                    }
                    echo "\n";
                }
            }
        } catch (\Exception $e) {
            echo "   ❌ Failed to list tools: {$e->getMessage()}\n";
        }

        // Discover resources
        echo "📦 Available Resources:\n";
        try {
            $resourcesResult = yield $this->client->listResources();

            if (empty($resourcesResult->resources)) {
                echo "   No resources available\n";
            } else {
                foreach ($resourcesResult->resources as $resource) {
                    echo "   📄 {$resource->uri}\n";
                    echo "      Name: {$resource->name}\n";
                    echo "      Type: {$resource->mimeType}\n";
                    if ($resource->description) {
                        echo "      Description: {$resource->description}\n";
                    }
                    echo "\n";
                }
            }
        } catch (\Exception $e) {
            echo "   ❌ Failed to list resources: {$e->getMessage()}\n";
        }

        // Discover prompts
        echo "💭 Available Prompts:\n";
        try {
            $promptsResult = yield $this->client->listPrompts();

            if (empty($promptsResult->prompts)) {
                echo "   No prompts available\n";
            } else {
                foreach ($promptsResult->prompts as $prompt) {
                    echo "   💡 {$prompt->name}\n";
                    echo "      Description: {$prompt->description}\n";

                    if ($prompt->arguments) {
                        echo "      Arguments:\n";
                        foreach ($prompt->arguments as $arg) {
                            $required = $arg->required ? ' (required)' : ' (optional)';
                            echo "        - {$arg->name}{$required}: {$arg->description}\n";
                        }
                    }
                    echo "\n";
                }
            }
        } catch (\Exception $e) {
            echo "   ❌ Failed to list prompts: {$e->getMessage()}\n";
        }
    }

    /**
     * Interactive mode - chat with the server
     */
    public function interactiveMode(): \Generator
    {
        echo "🎮 Interactive Mode\n";
        echo "==================\n";
        echo "Type 'help' for available commands, 'quit' to exit\n\n";

        while (true) {
            echo "MCP> ";
            $input = trim(fgets(STDIN));

            if ($input === 'quit' || $input === 'exit') {
                echo "👋 Goodbye!\n";
                break;
            }

            if ($input === 'help') {
                $this->showInteractiveHelp();
                continue;
            }

            if ($input === 'discover') {
                yield $this->discoverCapabilities();
                continue;
            }

            if (str_starts_with($input, 'tool ')) {
                yield $this->handleToolCommand($input);
                continue;
            }

            if (str_starts_with($input, 'resource ')) {
                yield $this->handleResourceCommand($input);
                continue;
            }

            if (str_starts_with($input, 'prompt ')) {
                yield $this->handlePromptCommand($input);
                continue;
            }

            echo "❓ Unknown command. Type 'help' for available commands.\n\n";
        }
    }

    /**
     * Handle tool commands
     */
    private function handleToolCommand(string $input): \Generator
    {
        // Parse: tool <name> <json-params>
        $parts = explode(' ', $input, 3);
        $toolName = $parts[1] ?? '';
        $paramsJson = $parts[2] ?? '{}';

        if (empty($toolName)) {
            echo "❌ Usage: tool <name> [json-params]\n";
            echo "Example: tool calculate {\"expression\":\"2+2\"}\n\n";
            return;
        }

        try {
            $params = json_decode($paramsJson, true);
            if ($params === null && $paramsJson !== '{}') {
                echo "❌ Invalid JSON parameters\n\n";
                return;
            }

            echo "🔧 Calling tool: {$toolName}\n";
            echo "Parameters: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

            $result = yield $this->client->callTool($toolName, $params);

            echo "✅ Result:\n";
            foreach ($result->content as $content) {
                if ($content->type === 'text') {
                    echo $content->text . "\n";
                }
            }
            echo "\n";

        } catch (McpError $e) {
            echo "❌ Tool error: {$e->getMessage()}\n\n";
        } catch (\Exception $e) {
            echo "❌ Error: {$e->getMessage()}\n\n";
        }
    }

    /**
     * Handle resource commands
     */
    private function handleResourceCommand(string $input): \Generator
    {
        // Parse: resource <uri>
        $parts = explode(' ', $input, 2);
        $uri = $parts[1] ?? '';

        if (empty($uri)) {
            echo "❌ Usage: resource <uri>\n";
            echo "Example: resource system://info\n\n";
            return;
        }

        try {
            echo "📦 Reading resource: {$uri}\n";

            $result = yield $this->client->readResource($uri);

            echo "✅ Resource content:\n";
            foreach ($result->contents as $content) {
                echo "URI: {$content->uri}\n";
                echo "Type: {$content->mimeType}\n";

                if (isset($content->text)) {
                    echo "Content:\n{$content->text}\n";
                } elseif (isset($content->blob)) {
                    echo "Binary content: " . strlen($content->blob) . " bytes\n";
                }
            }
            echo "\n";

        } catch (McpError $e) {
            echo "❌ Resource error: {$e->getMessage()}\n\n";
        } catch (\Exception $e) {
            echo "❌ Error: {$e->getMessage()}\n\n";
        }
    }

    /**
     * Handle prompt commands
     */
    private function handlePromptCommand(string $input): \Generator
    {
        // Parse: prompt <name> [json-args]
        $parts = explode(' ', $input, 3);
        $promptName = $parts[1] ?? '';
        $argsJson = $parts[2] ?? '{}';

        if (empty($promptName)) {
            echo "❌ Usage: prompt <name> [json-args]\n";
            echo "Example: prompt help {\"topic\":\"calculator\"}\n\n";
            return;
        }

        try {
            $args = json_decode($argsJson, true);
            if ($args === null && $argsJson !== '{}') {
                echo "❌ Invalid JSON arguments\n\n";
                return;
            }

            echo "💭 Getting prompt: {$promptName}\n";
            echo "Arguments: " . json_encode($args, JSON_PRETTY_PRINT) . "\n";

            $result = yield $this->client->getPrompt($promptName, $args);

            echo "✅ Prompt: {$result->description}\n\n";
            foreach ($result->messages as $message) {
                echo "Role: {$message->role}\n";
                foreach ($message->content as $content) {
                    if ($content->type === 'text') {
                        echo "Content:\n{$content->text}\n";
                    }
                }
                echo "\n";
            }

        } catch (McpError $e) {
            echo "❌ Prompt error: {$e->getMessage()}\n\n";
        } catch (\Exception $e) {
            echo "❌ Error: {$e->getMessage()}\n\n";
        }
    }

    /**
     * Show interactive help
     */
    private function showInteractiveHelp(): void
    {
        echo "📚 Interactive Commands:\n";
        echo "  discover                     - Discover server capabilities\n";
        echo "  tool <name> [json-params]    - Call a tool\n";
        echo "  resource <uri>               - Read a resource\n";
        echo "  prompt <name> [json-args]    - Get a prompt\n";
        echo "  help                         - Show this help\n";
        echo "  quit                         - Exit interactive mode\n\n";

        echo "📝 Examples:\n";
        echo "  tool calculate {\"expression\":\"2+2\"}\n";
        echo "  resource system://info\n";
        echo "  prompt help {\"topic\":\"calculator\"}\n\n";
    }

    /**
     * Test server with predefined scenarios
     */
    public function runTests(): \Generator
    {
        echo "🧪 Running Client Tests\n";
        echo "======================\n\n";

        $tests = [
            [
                'name' => 'Server Discovery',
                'action' => fn() => $this->discoverCapabilities()
            ],
            [
                'name' => 'Calculator Tool',
                'action' => fn() => $this->testCalculator()
            ],
            [
                'name' => 'Notes Management',
                'action' => fn() => $this->testNotes()
            ],
            [
                'name' => 'System Resource',
                'action' => fn() => $this->testSystemResource()
            ],
            [
                'name' => 'Help Prompt',
                'action' => fn() => $this->testHelpPrompt()
            ]
        ];

        $passed = 0;
        $failed = 0;

        foreach ($tests as $test) {
            echo "🔬 Test: {$test['name']}\n";
            echo str_repeat("-", 30) . "\n";

            try {
                yield $test['action']();
                echo "✅ PASSED\n\n";
                $passed++;
            } catch (\Exception $e) {
                echo "❌ FAILED: {$e->getMessage()}\n\n";
                $failed++;
            }
        }

        echo "📊 Test Results:\n";
        echo "  Passed: {$passed}\n";
        echo "  Failed: {$failed}\n";
        echo "  Total: " . ($passed + $failed) . "\n\n";

        if ($failed === 0) {
            echo "🎉 All tests passed! Your client works perfectly.\n";
        } else {
            echo "⚠️  Some tests failed. Check the error messages above.\n";
        }
    }

    /**
     * Test calculator functionality
     */
    private function testCalculator(): \Generator
    {
        $expressions = ['2 + 2', '10 * 5', '(100 - 20) / 4'];

        foreach ($expressions as $expr) {
            $result = yield $this->client->callTool('calculate', ['expression' => $expr]);
            echo "  {$expr} → {$result->content[0]->text}\n";
        }
    }

    /**
     * Test notes functionality
     */
    private function testNotes(): \Generator
    {
        // Save a note
        yield $this->client->callTool('save-note', [
            'title' => 'Test Note',
            'content' => 'This is a test note created by the universal client.'
        ]);
        echo "  ✅ Note saved\n";

        // Retrieve the note
        $result = yield $this->client->callTool('get-note', ['title' => 'Test Note']);
        echo "  ✅ Note retrieved: " . substr($result->content[0]->text, 0, 50) . "...\n";

        // List all notes
        $listResult = yield $this->client->callTool('list-notes', []);
        echo "  ✅ Notes listed\n";
    }

    /**
     * Test system resource
     */
    private function testSystemResource(): \Generator
    {
        $result = yield $this->client->readResource('system://info');
        $info = json_decode($result->contents[0]->text, true);

        echo "  Server: {$info['server_name']}\n";
        echo "  PHP Version: {$info['php_version']}\n";
        echo "  Memory Usage: " . round($info['memory_usage'] / 1024 / 1024, 2) . " MB\n";
    }

    /**
     * Test help prompt
     */
    private function testHelpPrompt(): \Generator
    {
        $result = yield $this->client->getPrompt('help', ['topic' => 'calculator']);
        echo "  ✅ Help prompt retrieved\n";
        echo "  Content length: " . strlen($result->messages[0]->content[0]->text) . " characters\n";
    }

    /**
     * Demonstrate batch operations
     */
    public function demonstrateBatchOperations(): \Generator
    {
        echo "⚡ Batch Operations Demo\n";
        echo "======================\n\n";

        echo "🔄 Running multiple calculations concurrently...\n";

        // Prepare multiple tool calls
        $calculations = [
            ['expression' => '5 + 3'],
            ['expression' => '10 * 2'],
            ['expression' => '50 / 5'],
            ['expression' => '(8 + 2) * 3']
        ];

        $startTime = microtime(true);

        // Execute all calculations concurrently
        $promises = [];
        foreach ($calculations as $calc) {
            $promises[] = $this->client->callTool('calculate', $calc);
        }

        $results = yield $promises;
        $endTime = microtime(true);

        echo "✅ Results:\n";
        foreach ($results as $index => $result) {
            $expr = $calculations[$index]['expression'];
            echo "  {$expr} → {$result->content[0]->text}\n";
        }

        $duration = round(($endTime - $startTime) * 1000, 2);
        echo "\n⏱️  Completed " . count($calculations) . " calculations in {$duration}ms\n";
        echo "💡 Concurrent execution is much faster than sequential!\n\n";
    }

    /**
     * Demonstrate error handling
     */
    public function demonstrateErrorHandling(): \Generator
    {
        echo "🛡️  Error Handling Demo\n";
        echo "======================\n\n";

        $errorTests = [
            [
                'name' => 'Invalid tool name',
                'action' => fn() => $this->client->callTool('nonexistent-tool', [])
            ],
            [
                'name' => 'Invalid parameters',
                'action' => fn() => $this->client->callTool('calculate', ['wrong_param' => 'value'])
            ],
            [
                'name' => 'Invalid expression',
                'action' => fn() => $this->client->callTool('calculate', ['expression' => 'invalid expression'])
            ],
            [
                'name' => 'Nonexistent resource',
                'action' => fn() => $this->client->readResource('nonexistent://resource')
            ]
        ];

        foreach ($errorTests as $test) {
            echo "🧪 Testing: {$test['name']}\n";

            try {
                yield $test['action']();
                echo "   ⚠️  Expected error but got success\n";
            } catch (McpError $e) {
                echo "   ✅ Caught MCP error: {$e->getMessage()}\n";
            } catch (\Exception $e) {
                echo "   ✅ Caught general error: {$e->getMessage()}\n";
            }

            echo "\n";
        }

        echo "💡 Proper error handling helps create robust applications!\n\n";
    }

    /**
     * Close connection
     */
    public function disconnect(): \Generator
    {
        if ($this->connected) {
            echo "🔌 Disconnecting from server...\n";
            yield $this->client->close();
            $this->connected = false;
            echo "✅ Disconnected successfully!\n";
        }
    }
}

// 🎮 Command Line Interface
if (basename($_SERVER['argv'][0]) === 'universal-client.php') {
    $client = new UniversalMCPClient();

    // Parse command line arguments
    $mode = $argv[1] ?? 'test';
    $serverCommand = $argv[2] ?? 'php';
    $serverScript = $argv[3] ?? '../my-first-mcp-server/personal-assistant-server.php';

    async(function() use ($client, $mode, $serverCommand, $serverScript) {
        try {
            // Connect to server
            yield $client->connect($serverCommand, [$serverScript]);

            switch ($mode) {
                case 'discover':
                    yield $client->discoverCapabilities();
                    break;

                case 'interactive':
                    yield $client->discoverCapabilities();
                    yield $client->interactiveMode();
                    break;

                case 'batch':
                    yield $client->demonstrateBatchOperations();
                    break;

                case 'errors':
                    yield $client->demonstrateErrorHandling();
                    break;

                case 'test':
                default:
                    yield $client->runTests();
                    break;
            }

        } catch (\Exception $e) {
            echo "❌ Client error: {$e->getMessage()}\n";
        } finally {
            yield $client->disconnect();
        }
    })->await();

    echo "\n📚 What's Next?\n";
    echo "Try different modes:\n";
    echo "  php universal-client.php discover    # Discover capabilities\n";
    echo "  php universal-client.php interactive # Interactive mode\n";
    echo "  php universal-client.php batch      # Batch operations demo\n";
    echo "  php universal-client.php errors     # Error handling demo\n";
    echo "  php universal-client.php test       # Run all tests\n\n";

    echo "Connect to different servers:\n";
    echo "  php universal-client.php test php /path/to/other-server.php\n";
    echo "  php universal-client.php interactive node /path/to/js-server.js\n\n";

    echo "🎉 Happy MCP development!\n";
}
```

### Step 3: Test Your Client (2 minutes)

Make sure you have your server from the previous tutorial:

```bash
chmod +x universal-client.php

# Test with your personal assistant server
php universal-client.php test php ../my-first-mcp-server/personal-assistant-server.php
```

You should see:

```
🔌 Connecting to MCP server...
✅ Connected successfully!
🔍 Discovering server capabilities...
🧪 Running Client Tests
✅ All tests passed!
```

### Step 4: Try Interactive Mode (2 minutes)

```bash
php universal-client.php interactive php ../my-first-mcp-server/personal-assistant-server.php
```

Try these commands:

```
MCP> tool calculate {"expression":"15 + 27"}
MCP> tool save-note {"title":"My Note","content":"Hello from the client!"}
MCP> resource system://info
MCP> prompt help {"topic":"calculator"}
MCP> quit
```

### Step 5: Explore Advanced Features (1 minute)

```bash
# Test batch operations
php universal-client.php batch

# Test error handling
php universal-client.php errors

# Just discover capabilities
php universal-client.php discover
```

## 🧠 Understanding Your Client

### 🏗️ Client Architecture

```php
// 1. Client Creation
$client = new Client($implementation, $options);

// 2. Transport Configuration
$transport = new StdioClientTransport($config);

// 3. Connection
yield $client->connect($transport);

// 4. Operations
$tools = yield $client->listTools();
$result = yield $client->callTool($name, $params);

// 5. Cleanup
yield $client->close();
```

### 🔄 Operation Patterns

#### Discovery Pattern

```php
// Always discover capabilities first
$tools = yield $client->listTools();
$resources = yield $client->listResources();
$prompts = yield $client->listPrompts();

// Then use what's available
foreach ($tools->tools as $tool) {
    echo "Available: {$tool->name}\n";
}
```

#### Error Handling Pattern

```php
try {
    $result = yield $client->callTool($name, $params);
    // Handle success
} catch (McpError $e) {
    // Handle MCP protocol errors
    switch ($e->getErrorCode()) {
        case ErrorCode::MethodNotFound:
            echo "Tool not found\n";
            break;
        case ErrorCode::InvalidParams:
            echo "Invalid parameters\n";
            break;
    }
} catch (\Exception $e) {
    // Handle other errors
    echo "Unexpected error: {$e->getMessage()}\n";
}
```

#### Batch Processing Pattern

```php
// Concurrent execution
$promises = [
    $client->callTool('tool1', $params1),
    $client->callTool('tool2', $params2),
    $client->callTool('tool3', $params3)
];

$results = yield $promises; // All execute concurrently
```

## 🎯 Real-World Applications

Now that you have a working client, here are some practical applications:

### 1. **Server Testing Tool**

Use your client to test any MCP server:

```bash
php universal-client.php test php /path/to/any-server.php
```

### 2. **API Gateway Client**

Connect to multiple servers and aggregate results:

```php
$servers = [
    'weather' => 'php weather-server.php',
    'database' => 'php database-server.php',
    'files' => 'python file-server.py'
];

foreach ($servers as $name => $command) {
    yield $client->connect($command);
    // Use server capabilities...
}
```

### 3. **Monitoring Dashboard**

Build a dashboard that monitors multiple MCP servers:

```php
async function monitorServers(array $servers): void
{
    while (true) {
        foreach ($servers as $server) {
            try {
                yield $client->ping();
                echo "✅ {$server} is healthy\n";
            } catch (\Exception $e) {
                echo "❌ {$server} is down\n";
            }
        }

        yield Amp\delay(30000); // Check every 30 seconds
    }
}
```

### 4. **LLM Integration**

Use your client to connect LLMs to MCP servers:

```php
// In your LLM application
$mcpResult = yield $client->callTool('search-database', [
    'query' => $userQuery,
    'limit' => 10
]);

// Provide results to LLM for processing
$llmResponse = $llm->complete([
    'context' => $mcpResult->content[0]->text,
    'query' => $userQuery
]);
```

## 🎉 Congratulations!

You've built a universal MCP client! Here's what you accomplished:

✅ **Created a versatile client** that works with any MCP server  
✅ **Implemented auto-discovery** of server capabilities  
✅ **Added interactive mode** for real-time testing  
✅ **Built error handling** for robust operation  
✅ **Demonstrated batch operations** for performance  
✅ **Tested everything** with comprehensive test suite

## 🚀 Next Steps

### Immediate Enhancements

1. **Add Configuration Management**:

   ```php
   // Store server connections
   $config = [
       'servers' => [
           'assistant' => 'php personal-assistant-server.php',
           'weather' => 'python weather-server.py'
       ]
   ];
   ```

2. **Add Result Caching**:

   ```php
   // Cache expensive tool calls
   $cacheKey = md5($toolName . serialize($params));
   if ($cached = $cache->get($cacheKey)) {
       return $cached;
   }
   ```

3. **Add Logging**:

   ```php
   use Monolog\Logger;

   $logger->info('Tool called', [
       'tool' => $toolName,
       'params' => $params,
       'result' => $result
   ]);
   ```

### Learning Path

1. **🔐 Add Authentication**: [Authentication Guide](../guides/security/authentication.md)
2. **🌐 HTTP Clients**: [HTTP Transport Guide](../guides/transport/http-transport.md)
3. **🏗️ Framework Integration**: [Laravel Integration](../guides/integrations/laravel-integration.md)
4. **🤖 AI Integration**: [OpenAI Tool Calling](../guides/integrations/openai-tool-calling.md)
5. **📊 Production Deployment**: [Deployment Strategies](../guides/real-world/deployment-strategies.md)

### Real-World Projects

- [🤖 AI Assistant](../examples/integrations/openai-assistant/) - Build an AI assistant
- [📊 Analytics Dashboard](../examples/real-world/analytics-dashboard/) - Data visualization
- [🔌 API Gateway](../examples/real-world/api-gateway/) - Service orchestration
- [📱 Mobile Backend](../examples/integrations/mobile-backend/) - Mobile app backend

## 🆘 Troubleshooting

### Common Issues

**Connection timeout**:

```php
// Add timeout configuration
$transport = new StdioClientTransport([
    'command' => 'php',
    'args' => ['server.php'],
    'timeout' => 30 // 30 seconds
]);
```

**Server not responding**:

```bash
# Test server directly
echo '{"jsonrpc":"2.0","id":1,"method":"ping"}' | php server.php
```

**Tool call failures**:

```php
// Add detailed error information
catch (McpError $e) {
    echo "Error Code: {$e->getErrorCode()}\n";
    echo "Message: {$e->getMessage()}\n";
    echo "Data: " . json_encode($e->getData()) . "\n";
}
```

### Getting Help

- 📖 [Client Development Guide](../guides/client-development/creating-clients.md)
- 🔧 [API Reference](../api/client.md)
- 🐛 [Report Issues](https://github.com/dalehurley/php-mcp-sdk/issues)
- 💬 [Community Support](https://github.com/dalehurley/php-mcp-sdk/discussions)

## 🎯 What You've Learned

- ✅ **MCP Client Architecture** - How clients connect and communicate
- ✅ **Discovery Patterns** - How to find and use server capabilities
- ✅ **Error Handling** - How to handle failures gracefully
- ✅ **Async Programming** - How to use PHP async patterns effectively
- ✅ **Testing Strategies** - How to verify your client works correctly

**Ready for more advanced topics?** → [Understanding MCP Protocol](understanding-mcp.md)

---

_🎉 You're now a confident MCP client developer! Time to build something amazing._
