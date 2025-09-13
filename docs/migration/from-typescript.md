# Migrating from TypeScript to PHP MCP SDK

This guide helps developers familiar with the TypeScript MCP SDK understand and migrate to the PHP implementation. While the core concepts remain the same, there are important differences in syntax, async handling, and language features.

## 🏗️ Architecture Comparison

Both SDKs follow the same architectural patterns:

| Concept | TypeScript | PHP |
|---------|------------|-----|
| **Server Creation** | `new Server(...)` | `new McpServer(...)` |
| **Client Creation** | `new Client(...)` | `new Client(...)` |
| **Async Operations** | `async/await` | `yield` with Amphp |
| **Promises** | `Promise<T>` | `Future<T>` |
| **Error Handling** | `try/catch` | `try/catch` with McpError |
| **Type Safety** | TypeScript interfaces | PHP 8.1+ classes and enums |

## 🔄 Key Differences

### 1. Async Programming

**TypeScript:**
```typescript
async function example() {
    const result = await client.callTool('example', { param: 'value' });
    return result;
}
```

**PHP:**
```php
use function Amp\async;

async(function() {
    $result = yield $client->callTool('example', ['param' => 'value']);
    return $result;
});
```

### 2. Type Definitions

**TypeScript:**
```typescript
interface Tool {
    name: string;
    description?: string;
    inputSchema: JSONSchema;
}

type ToolResult = {
    content: ContentBlock[];
    isError?: boolean;
};
```

**PHP:**
```php
// Uses classes instead of interfaces/types
class Tool {
    public function __construct(
        public string $name,
        public ?string $description = null,
        public array $inputSchema = []
    ) {}
}

// Return arrays instead of typed objects
function toolHandler(array $params): array {
    return [
        'content' => [/* ContentBlock[] */],
        'isError' => false
    ];
}
```

### 3. Error Handling

**TypeScript:**
```typescript
import { McpError, ErrorCode } from '@modelcontextprotocol/sdk/types.js';

try {
    const result = await client.callTool('example');
} catch (error) {
    if (error instanceof McpError) {
        console.error(`MCP Error ${error.code}: ${error.message}`);
    }
}
```

**PHP:**
```php
use MCP\Types\McpError;
use MCP\Types\ErrorCode;

try {
    $result = yield $client->callTool('example');
} catch (McpError $e) {
    error_log("MCP Error {$e->getErrorCode()->value}: {$e->getMessage()}");
}
```

### 4. Array vs Object Notation

**TypeScript** uses objects:
```typescript
const params = {
    location: 'London',
    units: 'celsius'
};
```

**PHP** uses associative arrays:
```php
$params = [
    'location' => 'London',
    'units' => 'celsius'
];
```

## 📋 API Migration Guide

### Server Creation

**TypeScript:**
```typescript
import { Server } from '@modelcontextprotocol/sdk/server/index.js';

const server = new Server(
    {
        name: 'weather-server',
        version: '1.0.0'
    },
    {
        capabilities: {
            tools: {}
        }
    }
);
```

**PHP:**
```php
use MCP\Server\McpServer;
use MCP\Types\Implementation;

$server = new McpServer(
    new Implementation('weather-server', '1.0.0')
);
```

### Tool Registration

**TypeScript:**
```typescript
server.setRequestHandler(CallToolRequestSchema, async (request) => {
    if (request.params.name === 'get-weather') {
        return {
            content: [{
                type: 'text',
                text: 'Sunny, 25°C'
            }]
        };
    }
    throw new McpError(ErrorCode.MethodNotFound, `Tool not found: ${request.params.name}`);
});

// List tools handler
server.setRequestHandler(ListToolsRequestSchema, async () => {
    return {
        tools: [{
            name: 'get-weather',
            description: 'Get weather information',
            inputSchema: {
                type: 'object',
                properties: {
                    location: { type: 'string' }
                },
                required: ['location']
            }
        }]
    };
});
```

**PHP:**
```php
$server->registerTool(
    'get-weather',
    [
        'description' => 'Get weather information',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string']
            ],
            'required' => ['location']
        ]
    ],
    function (array $params): array {
        return [
            'content' => [[
                'type' => 'text',
                'text' => 'Sunny, 25°C'
            ]]
        ];
    }
);
```

### Resource Registration

**TypeScript:**
```typescript
server.setRequestHandler(ListResourcesRequestSchema, async () => {
    return {
        resources: [{
            uri: 'config://app/settings',
            name: 'App Settings',
            mimeType: 'application/json'
        }]
    };
});

server.setRequestHandler(ReadResourceRequestSchema, async (request) => {
    if (request.params.uri === 'config://app/settings') {
        return {
            contents: [{
                uri: request.params.uri,
                mimeType: 'application/json',
                text: JSON.stringify({ theme: 'dark' }, null, 2)
            }]
        };
    }
    throw new McpError(ErrorCode.InvalidRequest, 'Resource not found');
});
```

**PHP:**
```php
$server->registerResource(
    'config://app/settings',
    [
        'name' => 'App Settings',
        'mimeType' => 'application/json'
    ],
    function (string $uri): array {
        return [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => 'application/json',
                'text' => json_encode(['theme' => 'dark'], JSON_PRETTY_PRINT)
            ]]
        ];
    }
);
```

### Transport Setup

**TypeScript (STDIO):**
```typescript
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';

const transport = new StdioServerTransport();
await server.connect(transport);
console.error('Server connected');
```

**PHP (STDIO):**
```php
use MCP\Server\Transport\StdioServerTransport;
use Amp\Loop;

$transport = new StdioServerTransport();
Amp\async(function() use ($server, $transport) {
    yield $server->connect($transport);
    error_log('Server connected');
});

Loop::run();
```

### Client Usage

**TypeScript:**
```typescript
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';

const client = new Client(
    { name: 'my-client', version: '1.0.0' },
    { capabilities: {} }
);

const transport = new StdioClientTransport({
    command: 'node',
    args: ['server.js']
});

await client.connect(transport);

const result = await client.request(
    { method: 'tools/call', params: { name: 'get-weather', arguments: { location: 'London' } } },
    CallToolResultSchema
);

console.log(result.content[0].text);
```

**PHP:**
```php
use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;

$client = new Client(
    new Implementation('my-client', '1.0.0')
);

$transport = new StdioClientTransport([
    'command' => 'php',
    'args' => ['server.php']
]);

Amp\async(function() use ($client, $transport) {
    yield $client->connect($transport);
    
    $result = yield $client->callTool('get-weather', [
        'location' => 'London'
    ]);
    
    echo $result->content[0]->text . "\n";
});
```

## 🔧 Common Patterns

### Error Creation

**TypeScript:**
```typescript
throw new McpError(ErrorCode.InvalidParams, 'Missing location parameter');
```

**PHP:**
```php
throw new McpError(ErrorCode::InvalidParams, 'Missing location parameter');
```

### Schema Validation

**TypeScript (using Zod):**
```typescript
import { z } from 'zod';

const ParamsSchema = z.object({
    location: z.string(),
    units: z.enum(['celsius', 'fahrenheit']).optional()
});

// In handler
const params = ParamsSchema.parse(request.params.arguments);
```

**PHP (manual validation or Respect/Validation):**
```php
use Respect\Validation\Validator as v;

// Manual validation
if (!isset($params['location']) || !is_string($params['location'])) {
    throw new McpError(ErrorCode::InvalidParams, 'location must be a string');
}

// Or with Respect/Validation
$validator = v::key('location', v::stringType()->notEmpty())
              ->key('units', v::optional(v::in(['celsius', 'fahrenheit'])));

if (!$validator->validate($params)) {
    throw new McpError(ErrorCode::InvalidParams, 'Invalid parameters');
}
```

### Notifications

**TypeScript:**
```typescript
// Send notification
await server.notification({
    method: 'notifications/tools/list_changed'
});

// Handle notification
server.setNotificationHandler(ProgressNotificationSchema, async (notification) => {
    console.log(`Progress: ${notification.params.progress}/${notification.params.total}`);
});
```

**PHP:**
```php
// Send notification  
yield $server->sendToolListChanged();

// Handle notification
$client->setNotificationHandler(
    ProgressNotification::class,
    function (ProgressNotification $notification) {
        echo "Progress: {$notification->progress}/{$notification->total}\n";
    }
);
```

## 📦 Package/Import Differences

### TypeScript Imports
```typescript
// Core types
import { McpError, ErrorCode } from '@modelcontextprotocol/sdk/types.js';

// Server
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';

// Client
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';

// Schemas
import { CallToolRequestSchema } from '@modelcontextprotocol/sdk/types.js';
```

### PHP Imports
```php
// Core types
use MCP\Types\McpError;
use MCP\Types\ErrorCode;

// Server
use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;

// Client
use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;

// Additional
use MCP\Types\Implementation;
use Amp\Loop;
use function Amp\async;
```

## 🔄 Environment & Runtime Differences

### Process Management

**TypeScript:**
```typescript
// Process spawning
import { spawn } from 'child_process';

const serverProcess = spawn('node', ['server.js'], {
    stdio: ['pipe', 'pipe', 'inherit']
});
```

**PHP:**
```php
// Using Amphp Process
use Amp\Process\Process;

$process = new Process(['php', 'server.php']);
yield $process->start();
```

### Configuration

**TypeScript (package.json):**
```json
{
    "dependencies": {
        "@modelcontextprotocol/sdk": "^1.0.0"
    }
}
```

**PHP (composer.json):**
```json
{
    "require": {
        "dalehurley/php-mcp-sdk": "^1.0"
    }
}
```

## 🧪 Testing Differences

### TypeScript Testing
```typescript
// Jest/Vitest
import { describe, it, expect } from 'vitest';

describe('MCP Server', () => {
    it('should handle tool calls', async () => {
        const result = await server.handleRequest({
            method: 'tools/call',
            params: { name: 'test-tool', arguments: {} }
        });
        
        expect(result.content).toBeDefined();
    });
});
```

### PHP Testing
```php
// PHPUnit
use PHPUnit\Framework\TestCase;

class McpServerTest extends TestCase
{
    public function testToolCalls(): void
    {
        $server = new McpServer(new Implementation('test', '1.0.0'));
        
        $tool = $server->registerTool('test-tool', [], fn() => [
            'content' => [['type' => 'text', 'text' => 'test']]
        ]);
        
        $this->assertEquals('test-tool', $tool->getName());
    }
}
```

## 📊 Performance Considerations

### Concurrency

**TypeScript:**
```typescript
// Concurrent operations
const [tools, resources, prompts] = await Promise.all([
    client.listTools(),
    client.listResources(),
    client.listPrompts()
]);
```

**PHP:**
```php
// Concurrent operations
$promises = [
    $client->listTools(),
    $client->listResources(),
    $client->listPrompts()
];

[$tools, $resources, $prompts] = yield $promises;
```

### Memory Management

**TypeScript:** Automatic garbage collection
**PHP:** Manual memory management in long-running processes

```php
// Clear large arrays periodically
unset($largeArray);

// Monitor memory usage
if (memory_get_usage() > 100 * 1024 * 1024) { // 100MB
    gc_collect_cycles();
}
```

## 🚀 Migration Checklist

### Pre-Migration
- [ ] Review TypeScript implementation architecture
- [ ] Identify async patterns and dependencies
- [ ] Document custom type definitions
- [ ] List external libraries used

### During Migration
- [ ] Convert `async/await` to `yield` patterns
- [ ] Replace TypeScript interfaces with PHP classes
- [ ] Update error handling to use PHP exceptions
- [ ] Convert object notation to associative arrays
- [ ] Update import statements
- [ ] Replace Zod schemas with PHP validation

### Post-Migration
- [ ] Test all async operations
- [ ] Verify error handling works correctly
- [ ] Check memory usage in long-running processes
- [ ] Update documentation
- [ ] Add PHP-specific type hints
- [ ] Run performance benchmarks

## 💡 Best Practices for Migration

### 1. Start Small
Begin with simple tools and gradually migrate complex functionality.

### 2. Maintain Type Safety
Use PHP 8.1+ features like union types, enums, and attributes:

```php
enum WeatherCondition: string {
    case Sunny = 'sunny';
    case Cloudy = 'cloudy';
    case Rainy = 'rainy';
}

class WeatherData {
    public function __construct(
        public string $location,
        public int $temperature,
        public WeatherCondition $condition
    ) {}
}
```

### 3. Handle Async Properly
Always use `yield` for async operations and proper error handling:

```php
Amp\async(function() use ($client) {
    try {
        $result = yield $client->callTool('example');
        // Handle result
    } catch (\Exception $e) {
        error_log("Error: " . $e->getMessage());
    }
});
```

### 4. Use Consistent Naming
Follow PHP naming conventions:
- Classes: `PascalCase` 
- Methods/Properties: `camelCase`
- Constants: `UPPER_SNAKE_CASE`

### 5. Leverage PHP Features
Use PHP-specific features that don't exist in TypeScript:

```php
// Null coalescing
$value = $params['optional'] ?? 'default';

// Spaceship operator
$comparison = $a <=> $b;

// Match expression (PHP 8.0+)
$result = match($condition) {
    'sunny' => '☀️',
    'cloudy' => '☁️',
    'rainy' => '🌧️',
    default => '❓'
};
```

## 🆘 Common Migration Issues

### 1. Async/Await Confusion
**Problem:** Using `await` instead of `yield`
**Solution:** Replace all `await` with `yield` and wrap in `Amp\async()`

### 2. Object vs Array Access
**Problem:** Using `object.property` syntax
**Solution:** Use `$array['key']` syntax consistently

### 3. Type Mismatches
**Problem:** Expecting typed objects, getting arrays
**Solution:** Use array destructuring and type validation

### 4. Missing Event Loop
**Problem:** Script exits immediately
**Solution:** Always call `Loop::run()` for async operations

### 5. Error Code Constants
**Problem:** Using TypeScript error codes as strings
**Solution:** Use PHP enum values: `ErrorCode::InvalidParams`

## 📚 Additional Resources

- [PHP MCP SDK Examples](../examples/README.md)
- [Amphp Documentation](https://amphp.org/amp)
- [PHP 8.1+ Features](https://www.php.net/releases/8.1/)
- [MCP Protocol Specification](https://spec.modelcontextprotocol.io/)

## 🤝 Getting Help

- [GitHub Issues](https://github.com/dalehurley/php-mcp-sdk/issues) - Report migration problems
- [Discussions](https://github.com/dalehurley/php-mcp-sdk/discussions) - Ask migration questions
- [TypeScript SDK](https://github.com/modelcontextprotocol/typescript-sdk) - Reference implementation

Migration from TypeScript to PHP requires attention to async patterns, type handling, and language-specific features, but the core MCP concepts remain consistent across both implementations! 🎯