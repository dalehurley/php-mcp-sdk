# Server API Reference

Complete API reference for building MCP servers with the PHP MCP SDK.

## McpServer Class

The high-level server class that provides a convenient API for creating MCP servers.

### Constructor

```php
public function __construct(
    Implementation $implementation,
    ?ServerOptions $options = null
)
```

**Parameters:**
- `$implementation` - Server identification (name, version, description)
- `$options` - Optional server configuration

**Example:**
```php
use MCP\Server\McpServer;
use MCP\Types\Implementation;
use MCP\Server\ServerOptions;

$server = new McpServer(
    new Implementation('my-server', '1.0.0', 'My MCP Server'),
    new ServerOptions(
        requestTimeout: 30000,
        debouncedNotifications: true
    )
);
```

### Properties

#### server (readonly)
```php
public readonly Server $server;
```
The underlying `Server` instance for advanced operations like sending custom notifications.

### Core Methods

#### connect()
Connect the server to a transport layer.

```php
public function connect(Transport $transport): Future<void>
```

**Parameters:**
- `$transport` - Transport implementation (STDIO, HTTP, etc.)

**Returns:** `Future<void>` - Resolves when connection is established

**Example:**
```php
use MCP\Server\Transport\StdioServerTransport;
use Amp\Loop;

$transport = new StdioServerTransport();
Amp\async(function() use ($server, $transport) {
    yield $server->connect($transport);
    error_log("Server connected and ready");
});

Loop::run();
```

#### close()
Close the server connection.

```php  
public function close(): Future<void>
```

**Returns:** `Future<void>` - Resolves when connection is closed

### Tool Management

#### registerTool()
Register a tool that clients can invoke.

```php
public function registerTool(
    string $name,
    array $config,
    callable $handler
): RegisteredTool
```

**Parameters:**
- `$name` - Unique tool identifier
- `$config` - Tool configuration array
  - `title` (string, optional) - Human-readable display name
  - `description` (string) - Tool description  
  - `inputSchema` (array) - JSON Schema for input validation
  - `outputSchema` (array, optional) - JSON Schema for output validation
  - `annotations` (array, optional) - Tool metadata and hints
- `$handler` - Callable that processes tool invocations

**Returns:** `RegisteredTool` - Tool registration object for management

**Handler Signature:**
```php
function(array $params, ?AuthInfo $auth = null): array
```

**Handler Return Format:**
```php
[
    'content' => [
        [
            'type' => 'text',
            'text' => 'Result content'
        ],
        // Additional content blocks...
    ],
    'isError' => false // Optional, defaults to false
]
```

**Example:**
```php
$tool = $server->registerTool(
    'calculate',
    [
        'title' => 'Calculator',
        'description' => 'Perform mathematical calculations',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Mathematical expression to evaluate'
                ]
            ],
            'required' => ['expression']
        ],
        'annotations' => [
            'audience' => ['developers'],
            'category' => 'utility'
        ]
    ],
    function (array $params): array {
        $expression = $params['expression'];
        
        // Validate and evaluate expression
        $result = eval("return $expression;");
        
        return [
            'content' => [[
                'type' => 'text',
                'text' => (string) $result
            ]]
        ];
    }
);

// Tool can be managed dynamically
$tool->setEnabled(false); // Disable temporarily
$tool->updateConfig(['description' => 'Updated description']);
```

#### listTools()
List all registered tools (internal method).

```php
public function listTools(): array
```

**Returns:** Array of tool definitions

#### callTool()
Call a registered tool (internal method).

```php
public function callTool(string $name, array $params): array
```

### Resource Management

#### registerResource()
Register a static resource.

```php
public function registerResource(
    string $uri,
    array $config,
    callable $handler
): RegisteredResource
```

**Parameters:**
- `$uri` - Resource URI identifier
- `$config` - Resource configuration
  - `name` (string) - Resource display name
  - `description` (string, optional) - Resource description
  - `mimeType` (string, optional) - Content MIME type
  - `annotations` (array, optional) - Resource metadata
- `$handler` - Callable that provides resource content

**Handler Signature:**
```php
function(string $uri, ?AuthInfo $auth = null): array
```

**Handler Return Format:**
```php
[
    'contents' => [
        [
            'uri' => 'resource://path',
            'mimeType' => 'application/json',
            'text' => 'Content as string'
            // OR
            'blob' => 'base64-encoded-data'
        ]
    ]
]
```

**Example:**
```php
$resource = $server->registerResource(
    'config://app/settings',
    [
        'name' => 'Application Settings',
        'description' => 'Current application configuration',
        'mimeType' => 'application/json'
    ],
    function (string $uri): array {
        $config = loadAppConfig();
        
        return [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => 'application/json',
                'text' => json_encode($config, JSON_PRETTY_PRINT)
            ]]
        ];
    }
);
```

#### registerResourceTemplate()
Register a resource template with URI patterns.

```php
public function registerResourceTemplate(
    string $uriTemplate,
    array $config, 
    callable $handler
): RegisteredResourceTemplate
```

**Parameters:**
- `$uriTemplate` - URI template with placeholders (e.g., `files://{path}`)
- `$config` - Template configuration
- `$handler` - Callable that handles template-based resource requests

**Example:**
```php
$template = $server->registerResourceTemplate(
    'files://{path}',
    [
        'name' => 'File System',
        'description' => 'Access to file system resources',
        'mimeType' => 'text/plain'
    ],
    function (string $uri): array {
        // Extract path from URI: files://some/path -> some/path
        preg_match('/files:\/\/(.+)/', $uri, $matches);
        $path = urldecode($matches[1]);
        
        if (!file_exists($path)) {
            throw new McpError(ErrorCode::InvalidRequest, "File not found: $path");
        }
        
        $content = file_get_contents($path);
        $mimeType = mime_content_type($path) ?: 'text/plain';
        
        return [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => $mimeType,
                'text' => $content
            ]]
        ];
    }
);
```

### Prompt Management

#### registerPrompt()
Register a prompt template.

```php
public function registerPrompt(
    string $name,
    array $config,
    callable $handler
): RegisteredPrompt
```

**Parameters:**
- `$name` - Unique prompt identifier
- `$config` - Prompt configuration
  - `name` (string) - Display name
  - `description` (string, optional) - Prompt description
  - `arguments` (array, optional) - Prompt arguments definition
- `$handler` - Callable that generates prompt content

**Handler Signature:**
```php
function(array $arguments, ?AuthInfo $auth = null): array
```

**Handler Return Format:**
```php
[
    'description' => 'Generated prompt description',
    'messages' => [
        [
            'role' => 'system|user|assistant',
            'content' => [
                'type' => 'text',
                'text' => 'Message content'
            ]
        ]
    ]
]
```

**Example:**
```php
$prompt = $server->registerPrompt(
    'code-review',
    [
        'name' => 'Code Review Assistant',
        'description' => 'Helps review code changes for best practices',
        'arguments' => [
            [
                'name' => 'language',
                'description' => 'Programming language',
                'required' => true
            ],
            [
                'name' => 'complexity',
                'description' => 'Code complexity level',
                'required' => false
            ]
        ]
    ],
    function (array $arguments): array {
        $language = $arguments['language'];
        $complexity = $arguments['complexity'] ?? 'medium';
        
        return [
            'description' => "Code review assistant for {$language}",
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        'type' => 'text',
                        'text' => "You are an expert {$language} code reviewer. Analyze code at {$complexity} complexity level focusing on:\n\n1. Correctness\n2. Performance\n3. Security\n4. Best practices\n5. Maintainability\n\nProvide specific, actionable feedback."
                    ]
                ]
            ]
        ];
    }
);
```

### Authentication

#### setAuthProvider()
Set OAuth authentication provider.

```php
public function setAuthProvider(OAuthServerProvider $provider): void
```

**Parameters:**
- `$provider` - OAuth provider instance

**Example:**
```php
use MCP\Server\Auth\Providers\DefaultProvider;

$authProvider = new DefaultProvider([
    'issuer' => 'https://your-auth-server.com',
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret'
]);

$server->setAuthProvider($authProvider);
```

### Logging

#### setLogger()
Set a PSR-3 compatible logger.

```php
public function setLogger(LoggerInterface $logger): void
```

**Parameters:**
- `$logger` - PSR-3 logger instance

**Example:**
```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('mcp-server');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

$server->setLogger($logger);
```

### Notifications

#### sendToolListChanged()
Notify clients that the tool list has changed.

```php
public function sendToolListChanged(): Future<void>
```

**Example:**
```php
// After dynamically adding/removing tools
yield $server->sendToolListChanged();
```

#### sendResourceListChanged()
Notify clients that the resource list has changed.

```php
public function sendResourceListChanged(): Future<void>
```

#### sendPromptListChanged()  
Notify clients that the prompt list has changed.

```php
public function sendPromptListChanged(): Future<void>
```

#### sendResourceUpdated()
Notify subscribed clients that a resource has been updated.

```php
public function sendResourceUpdated(string $uri): Future<void>
```

**Parameters:**
- `$uri` - URI of the updated resource

### Sampling (LLM Integration)

#### requestSampling()
Request LLM text completion (requires client sampling support).

```php
public function requestSampling(array $request): Future<array>
```

**Parameters:**
- `$request` - Sampling request configuration
  - `messages` (array) - Conversation messages
  - `modelPreferences` (array, optional) - Model selection hints
  - `systemPrompt` (string, optional) - System prompt
  - `includeContext` (string, optional) - Context inclusion level
  - `temperature` (float, optional) - Sampling temperature
  - `maxTokens` (int, optional) - Maximum tokens to generate

**Example:**
```php
$server->registerTool(
    'ai-analysis',
    [
        'description' => 'AI-powered data analysis',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'string']
            ]
        ]
    ],
    function (array $params) use ($server): array {
        $result = yield $server->requestSampling([
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => "Analyze this data and provide insights:\n\n{$params['data']}"
                    ]
                ]
            ],
            'maxTokens' => 500,
            'temperature' => 0.7
        ]);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $result['content'][0]['text']
                ]
            ]
        ];
    }
);
```

## RegisteredTool Class

Represents a registered tool that can be managed dynamically.

### Methods

#### getName()
```php
public function getName(): string
```

Get the tool name.

#### getConfig()
```php
public function getConfig(): array
```

Get the current tool configuration.

#### updateConfig()
```php
public function updateConfig(array $config): void
```

Update tool configuration.

#### setEnabled()
```php
public function setEnabled(bool $enabled): void
```

Enable or disable the tool.

#### isEnabled()
```php
public function isEnabled(): bool
```

Check if tool is enabled.

## RegisteredResource Class

Represents a registered resource.

### Methods

#### getUri()
```php
public function getUri(): string
```

#### getConfig()  
```php
public function getConfig(): array
```

#### updateConfig()
```php
public function updateConfig(array $config): void
```

## RegisteredPrompt Class

Represents a registered prompt.

### Methods

#### getName()
```php
public function getName(): string  
```

#### getConfig()
```php
public function getConfig(): array
```

#### updateConfig()
```php
public function updateConfig(array $config): void
```

## Error Handling

### McpError Exception

All MCP-specific errors throw `McpError` exceptions:

```php
use MCP\Types\McpError;
use MCP\Types\ErrorCode;

throw new McpError(
    ErrorCode::InvalidParams,
    'Missing required parameter: location',
    ['parameter' => 'location']
);
```

### Error Codes

```php
enum ErrorCode: int {
    // JSON-RPC standard errors
    case ParseError = -32700;
    case InvalidRequest = -32600;
    case MethodNotFound = -32601;
    case InvalidParams = -32602;
    case InternalError = -32603;
    
    // MCP-specific errors  
    case Forbidden = -32000;
    case Unauthorized = -32001;
    case ResourceNotFound = -32002;
    case ToolExecutionError = -32003;
}
```

## Configuration Classes

### ServerOptions

```php
class ServerOptions
{
    public function __construct(
        public ?int $requestTimeout = null,
        public ?bool $debouncedNotifications = null,
        public ?array $capabilities = null
    ) {}
}
```

### Implementation

```php
class Implementation
{
    public function __construct(
        private string $name,
        private string $version, 
        private ?string $title = null
    ) {}

    public function getName(): string;
    public function getVersion(): string;
    public function getTitle(): ?string;
}
```

## Best Practices

### Error Handling in Handlers

```php
$server->registerTool(
    'safe-tool',
    $config,
    function (array $params): array {
        try {
            // Validate parameters
            if (empty($params['required_field'])) {
                throw new McpError(
                    ErrorCode::InvalidParams,
                    'required_field cannot be empty'
                );
            }
            
            // Perform operation
            $result = performOperation($params);
            
            return [
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($result)
                ]]
            ];
            
        } catch (McpError $e) {
            // Re-throw MCP errors
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions
            throw new McpError(
                ErrorCode::InternalError,
                'Operation failed: ' . $e->getMessage()
            );
        }
    }
);
```

### Input Validation

```php
use Respect\Validation\Validator as v;

$server->registerTool(
    'email-tool',
    [
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email'],
                'subject' => ['type' => 'string', 'minLength' => 1]
            ],
            'required' => ['email', 'subject']
        ]
    ],
    function (array $params): array {
        // Additional validation beyond JSON schema
        if (!v::email()->validate($params['email'])) {
            throw new McpError(
                ErrorCode::InvalidParams,
                'Invalid email format'
            );
        }
        
        // Process the email...
    }
);
```

### Resource Security

```php
$server->registerResourceTemplate(
    'files://{path}',
    $config,
    function (string $uri, ?AuthInfo $auth = null): array {
        // Extract and validate path
        preg_match('/files:\/\/(.+)/', $uri, $matches);
        $path = urldecode($matches[1]);
        
        // Security: Prevent directory traversal
        $realPath = realpath($path);
        $allowedDir = realpath('/allowed/directory');
        
        if (!$realPath || !str_starts_with($realPath, $allowedDir)) {
            throw new McpError(
                ErrorCode::Forbidden,
                'Access denied'
            );
        }
        
        // Check authentication
        if ($auth && !$auth->hasPermission('read:files')) {
            throw new McpError(
                ErrorCode::Unauthorized,
                'Insufficient permissions'
            );
        }
        
        // Serve file...
    }
);
```

## Next Steps

- [📱 Client API Reference](client.md)
- [🔌 Transport APIs](transports.md)
- [📋 Types & Schemas](types.md)
- [🖥️ Advanced Server Guide](../guides/creating-servers.md)