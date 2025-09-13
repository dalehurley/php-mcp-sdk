# Client API Reference

Complete API reference for building MCP clients with the PHP MCP SDK.

## Client Class

The MCP client class for connecting to and interacting with MCP servers.

### Constructor

```php
public function __construct(
    Implementation $clientInfo,
    ?ClientOptions $options = null
)
```

**Parameters:**
- `$clientInfo` - Client identification (name, version, description)
- `$options` - Optional client configuration

**Example:**
```php
use MCP\Client\Client;
use MCP\Types\Implementation;
use MCP\Client\ClientOptions;
use MCP\Types\Capabilities\ClientCapabilities;

$client = new Client(
    new Implementation('my-client', '1.0.0', 'My MCP Client'),
    new ClientOptions(
        capabilities: new ClientCapabilities(
            sampling: true,
            roots: ['file:///project'],
            experimental: []
        )
    )
);
```

### Connection Management

#### connect()
Connect to an MCP server via a transport.

```php
public function connect(Transport $transport): Future<InitializeResult>
```

**Parameters:**
- `$transport` - Transport implementation (STDIO, HTTP, WebSocket)

**Returns:** `Future<InitializeResult>` - Server initialization information

**Example:**
```php
use MCP\Client\Transport\StdioClientTransport;
use Amp\Loop;

$transport = new StdioClientTransport([
    'command' => 'php',
    'args' => ['/path/to/server.php']
]);

Amp\async(function() use ($client, $transport) {
    $result = yield $client->connect($transport);
    
    echo "Connected to: {$result->serverInfo->name}\n";
    echo "Version: {$result->serverInfo->version}\n";
    echo "Protocol version: {$result->protocolVersion}\n";
});

Loop::run();
```

#### initialize()
Get server initialization information (called automatically by `connect()`).

```php
public function initialize(): Future<InitializeResult>
```

**Returns:** `Future<InitializeResult>` - Server capabilities and info

#### close()
Close the connection to the server.

```php
public function close(): Future<void>
```

**Example:**
```php
yield $client->close();
echo "Disconnected from server\n";
```

### Server Information

#### getServerCapabilities()
Get the server's capabilities.

```php
public function getServerCapabilities(): ?ServerCapabilities
```

**Returns:** `ServerCapabilities` object or null if not connected

**Example:**
```php
$capabilities = $client->getServerCapabilities();

if ($capabilities) {
    echo "Tools: " . ($capabilities->tools ? "supported" : "not supported") . "\n";
    echo "Resources: " . ($capabilities->resources ? "supported" : "not supported") . "\n";
    echo "Prompts: " . ($capabilities->prompts ? "supported" : "not supported") . "\n";
}
```

#### getServerVersion()
Get server version information.

```php
public function getServerVersion(): ?Implementation
```

**Returns:** `Implementation` object with server details

### Tool Operations

#### listTools()
List all available tools on the server.

```php
public function listTools(?PaginatedRequest $request = null): Future<ListToolsResult>
```

**Parameters:**
- `$request` - Optional pagination parameters

**Returns:** `Future<ListToolsResult>` - Available tools

**Example:**
```php
$result = yield $client->listTools();

foreach ($result->tools as $tool) {
    echo "Tool: {$tool->name}\n";
    echo "  Description: {$tool->description}\n";
    
    if ($tool->inputSchema) {
        echo "  Input schema: " . json_encode($tool->inputSchema) . "\n";
    }
}
```

#### callTool()
Call a tool on the server.

```php
public function callTool(
    string $name,
    array $arguments = [],
    ?RequestOptions $options = null
): Future<CallToolResult>
```

**Parameters:**
- `$name` - Tool name to call
- `$arguments` - Tool arguments as associative array
- `$options` - Optional request configuration

**Returns:** `Future<CallToolResult>` - Tool execution result

**Example:**
```php
try {
    $result = yield $client->callTool('get-weather', [
        'location' => 'London, UK',
        'units' => 'celsius'
    ]);

    foreach ($result->content as $content) {
        if ($content->type === 'text') {
            echo "Result: {$content->text}\n";
        }
    }
    
} catch (McpError $e) {
    echo "Tool error: {$e->getMessage()}\n";
}
```

### Resource Operations

#### listResources()
List available resources on the server.

```php
public function listResources(?PaginatedRequest $request = null): Future<ListResourcesResult>
```

**Parameters:**
- `$request` - Optional pagination parameters

**Returns:** `Future<ListResourcesResult>` - Available resources

**Example:**
```php
$result = yield $client->listResources();

foreach ($result->resources as $resource) {
    echo "Resource: {$resource->uri}\n";
    echo "  Name: {$resource->name}\n";
    echo "  Type: {$resource->mimeType}\n";
    if ($resource->description) {
        echo "  Description: {$resource->description}\n";
    }
}
```

#### listResourceTemplates()
List available resource templates.

```php
public function listResourceTemplates(?PaginatedRequest $request = null): Future<ListResourceTemplatesResult>
```

**Parameters:**
- `$request` - Optional pagination parameters

**Returns:** `Future<ListResourceTemplatesResult>` - Available resource templates

**Example:**
```php
$result = yield $client->listResourceTemplates();

foreach ($result->resourceTemplates as $template) {
    echo "Template: {$template->uriTemplate}\n";
    echo "  Name: {$template->name}\n";
    if ($template->description) {
        echo "  Description: {$template->description}\n";
    }
}
```

#### readResource()
Read the content of a specific resource.

```php
public function readResource(
    string $uri,
    ?RequestOptions $options = null
): Future<ReadResourceResult>
```

**Parameters:**
- `$uri` - Resource URI to read
- `$options` - Optional request configuration

**Returns:** `Future<ReadResourceResult>` - Resource content

**Example:**
```php
try {
    $result = yield $client->readResource('config://app/settings');

    foreach ($result->contents as $content) {
        echo "URI: {$content->uri}\n";
        echo "Type: {$content->mimeType}\n";
        
        if (isset($content->text)) {
            echo "Content:\n{$content->text}\n";
        } elseif (isset($content->blob)) {
            echo "Binary content: " . strlen($content->blob) . " bytes\n";
        }
    }
    
} catch (McpError $e) {
    echo "Resource error: {$e->getMessage()}\n";
}
```

### Prompt Operations

#### listPrompts()
List available prompts on the server.

```php
public function listPrompts(?PaginatedRequest $request = null): Future<ListPromptsResult>
```

**Parameters:**
- `$request` - Optional pagination parameters

**Returns:** `Future<ListPromptsResult>` - Available prompts

**Example:**
```php
$result = yield $client->listPrompts();

foreach ($result->prompts as $prompt) {
    echo "Prompt: {$prompt->name}\n";
    if ($prompt->description) {
        echo "  Description: {$prompt->description}\n";
    }
    
    if ($prompt->arguments) {
        echo "  Arguments:\n";
        foreach ($prompt->arguments as $arg) {
            echo "    - {$arg->name}: {$arg->description}\n";
        }
    }
}
```

#### getPrompt()
Get a prompt with its content.

```php
public function getPrompt(
    string $name,
    array $arguments = [],
    ?RequestOptions $options = null
): Future<GetPromptResult>
```

**Parameters:**
- `$name` - Prompt name
- `$arguments` - Prompt arguments as associative array
- `$options` - Optional request configuration

**Returns:** `Future<GetPromptResult>` - Prompt content

**Example:**
```php
$result = yield $client->getPrompt('code-review', [
    'language' => 'php',
    'complexity' => 'medium'
]);

echo "Prompt: {$result->description}\n\n";

foreach ($result->messages as $message) {
    echo "Role: {$message->role}\n";
    foreach ($message->content as $content) {
        if ($content->type === 'text') {
            echo "Content: {$content->text}\n";
        }
    }
    echo "\n";
}
```

### Resource Subscription

#### subscribe()
Subscribe to notifications about resource changes.

```php
public function subscribe(
    string $uri,
    ?RequestOptions $options = null
): Future<EmptyResult>
```

**Parameters:**
- `$uri` - Resource URI to watch
- `$options` - Optional request configuration

**Returns:** `Future<EmptyResult>`

**Example:**
```php
// Subscribe to configuration changes
yield $client->subscribe('config://app/settings');

// Set up notification handler (see Event Handling section)
$client->setNotificationHandler(
    ResourceUpdatedNotification::class,
    function (ResourceUpdatedNotification $notification) {
        echo "Resource updated: {$notification->uri}\n";
        
        // Re-read the resource
        $content = yield $this->readResource($notification->uri);
        // Process updated content...
    }
);
```

#### unsubscribe()
Unsubscribe from resource change notifications.

```php
public function unsubscribe(
    string $uri,
    ?RequestOptions $options = null
): Future<EmptyResult>
```

**Parameters:**
- `$uri` - Resource URI to stop watching
- `$options` - Optional request configuration

### Sampling Operations (if supported)

#### createMessage()
Create a message completion request (requires sampling capability).

```php
public function createMessage(
    CreateMessageRequest $request,
    ?RequestOptions $options = null
): Future<CreateMessageResult>
```

**Example:**
```php
$request = new CreateMessageRequest([
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Explain quantum computing'
            ]
        ]
    ],
    'maxTokens' => 500
]);

$result = yield $client->createMessage($request);

foreach ($result->content as $content) {
    if ($content->type === 'text') {
        echo "Response: {$content->text}\n";
    }
}
```

### Event Handling

#### setNotificationHandler()
Set a handler for server notifications.

```php
public function setNotificationHandler(
    string $notificationClass,
    callable $handler
): void
```

**Parameters:**
- `$notificationClass` - Notification class name
- `$handler` - Callable to handle notifications

**Available Notification Types:**
- `ToolListChangedNotification`
- `ResourceListChangedNotification` 
- `ResourceUpdatedNotification`
- `PromptListChangedNotification`
- `RootsListChangedNotification`
- `ProgressNotification`
- `LoggingMessageNotification`

**Example:**
```php
use MCP\Types\Notifications\ToolListChangedNotification;
use MCP\Types\Notifications\ResourceUpdatedNotification;
use MCP\Types\Notifications\ProgressNotification;

// Handle tool list changes
$client->setNotificationHandler(
    ToolListChangedNotification::class,
    function (ToolListChangedNotification $notification) {
        echo "Tool list changed, refreshing...\n";
        $tools = yield $this->listTools();
        // Update local tool cache...
    }
);

// Handle resource updates
$client->setNotificationHandler(
    ResourceUpdatedNotification::class,
    function (ResourceUpdatedNotification $notification) {
        echo "Resource updated: {$notification->uri}\n";
        // Reload resource if needed...
    }
);

// Handle progress notifications
$client->setNotificationHandler(
    ProgressNotification::class,
    function (ProgressNotification $notification) {
        if ($notification->progress !== null) {
            $percent = ($notification->progress / $notification->total) * 100;
            echo "Progress: {$percent}% - {$notification->progress}/{$notification->total}\n";
        }
    }
);
```

### Utility Methods

#### ping()
Send a ping request to test connectivity.

```php
public function ping(?RequestOptions $options = null): Future<EmptyResult>
```

**Example:**
```php
try {
    yield $client->ping();
    echo "Server is responsive\n";
} catch (\Exception $e) {
    echo "Server is not responding: {$e->getMessage()}\n";
}
```

#### setLoggingLevel()
Set the logging level on the server.

```php
public function setLoggingLevel(
    LoggingLevel $level,
    ?RequestOptions $options = null
): Future<EmptyResult>
```

**Parameters:**
- `$level` - Logging level (DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY)

**Example:**
```php
yield $client->setLoggingLevel(LoggingLevel::DEBUG);
```

## Transport Classes

### StdioClientTransport

For process-based communication.

```php
class StdioClientTransport implements Transport
{
    public function __construct(array $options)
}
```

**Options:**
- `command` (string) - Command to execute
- `args` (array) - Command arguments
- `env` (array, optional) - Environment variables
- `cwd` (string, optional) - Working directory

**Example:**
```php
$transport = new StdioClientTransport([
    'command' => 'php',
    'args' => ['/path/to/server.php'],
    'env' => ['DEBUG' => '1'],
    'cwd' => '/project/directory'
]);
```

### HttpClientTransport

For HTTP-based communication.

```php
class HttpClientTransport implements Transport
{
    public function __construct(string $baseUrl, array $options = [])
}
```

**Parameters:**
- `$baseUrl` - Server base URL
- `$options` - HTTP client options

**Example:**
```php
$transport = new HttpClientTransport('https://api.example.com/mcp', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'User-Agent' => 'MyClient/1.0'
    ],
    'timeout' => 30,
    'verify_ssl' => true
]);
```

### WebSocketClientTransport

For WebSocket-based communication (coming soon).

```php
$transport = new WebSocketClientTransport('wss://api.example.com/mcp', [
    'headers' => ['Authorization' => 'Bearer ' . $token],
    'reconnect' => true,
    'reconnect_interval' => 5
]);
```

## Configuration Classes

### ClientOptions

```php
class ClientOptions
{
    public function __construct(
        public ?ClientCapabilities $capabilities = null,
        public ?RequestOptions $requestOptions = null
    ) {}
}
```

### ClientCapabilities

```php
class ClientCapabilities
{
    public function __construct(
        public ?array $experimental = null,
        public ?array $roots = null,
        public ?bool $sampling = null
    ) {}
}
```

### RequestOptions

```php
class RequestOptions
{
    public function __construct(
        public ?int $timeout = null,
        public ?string $requestId = null
    ) {}
}
```

## Error Handling

### Exception Types

```php
// MCP protocol errors
use MCP\Types\McpError;
use MCP\Types\ErrorCode;

// Transport errors  
use MCP\Client\Transport\TransportError;

// Connection errors
use MCP\Client\Transport\ConnectionError;
```

### Error Handling Patterns

```php
try {
    $result = yield $client->callTool('example-tool', $params);
    // Handle success...
    
} catch (McpError $e) {
    // MCP protocol error
    switch ($e->getErrorCode()) {
        case ErrorCode::MethodNotFound:
            echo "Tool not found\n";
            break;
        case ErrorCode::InvalidParams:
            echo "Invalid parameters: {$e->getMessage()}\n";
            break;
        case ErrorCode::InternalError:
            echo "Server error: {$e->getMessage()}\n";
            break;
        default:
            echo "MCP error: {$e->getMessage()}\n";
    }
    
} catch (TransportError $e) {
    // Transport layer error
    echo "Transport error: {$e->getMessage()}\n";
    // Maybe try to reconnect...
    
} catch (\Exception $e) {
    // Other errors
    echo "Unexpected error: {$e->getMessage()}\n";
}
```

### Retry Logic

```php
async function callWithRetry(Client $client, string $tool, array $params, int $maxRetries = 3): array
{
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            return yield $client->callTool($tool, $params);
            
        } catch (TransportError $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            
            echo "Attempt {$attempt} failed, retrying...\n";
            yield Amp\delay(1000 * $attempt); // Exponential backoff
            
        } catch (McpError $e) {
            // Don't retry protocol errors
            throw $e;
        }
    }
}
```

## Best Practices

### Connection Management

```php
class ManagedClient
{
    private Client $client;
    private Transport $transport;
    private bool $connected = false;
    
    public function __construct(Client $client, Transport $transport)
    {
        $this->client = $client;
        $this->transport = $transport;
    }
    
    public function ensureConnected(): Promise<void>
    {
        if (!$this->connected) {
            yield $this->client->connect($this->transport);
            $this->connected = true;
            echo "Connected to server\n";
        }
    }
    
    public function disconnect(): Promise<void>
    {
        if ($this->connected) {
            yield $this->client->close();
            $this->connected = false;
            echo "Disconnected from server\n";
        }
    }
}
```

### Resource Caching

```php
class CachingClient
{
    private Client $client;
    private array $resourceCache = [];
    
    public function readResourceCached(string $uri, int $ttl = 300): Promise<ReadResourceResult>
    {
        $cacheKey = $uri;
        $now = time();
        
        if (isset($this->resourceCache[$cacheKey])) {
            $cached = $this->resourceCache[$cacheKey];
            if ($cached['expires'] > $now) {
                return $cached['data'];
            }
        }
        
        $result = yield $this->client->readResource($uri);
        
        $this->resourceCache[$cacheKey] = [
            'data' => $result,
            'expires' => $now + $ttl
        ];
        
        return $result;
    }
}
```

### Batch Operations

```php
async function batchToolCalls(Client $client, array $calls): array
{
    $promises = [];
    
    foreach ($calls as $call) {
        $promises[] = $client->callTool($call['tool'], $call['params']);
    }
    
    return yield $promises; // Execute all concurrently
}

// Usage
$calls = [
    ['tool' => 'get-weather', 'params' => ['location' => 'London']],
    ['tool' => 'get-weather', 'params' => ['location' => 'Paris']],
    ['tool' => 'get-weather', 'params' => ['location' => 'Tokyo']]
];

$results = yield batchToolCalls($client, $calls);
```

## Next Steps

- [🔧 Server API Reference](server.md)
- [🔌 Transport APIs](transports.md)
- [📋 Types & Schemas](types.md)
- [📱 Advanced Client Guide](../guides/creating-clients.md)