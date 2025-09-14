# Core Types API Reference

This reference documents the core types used throughout the PHP MCP SDK. These types form the foundation of the MCP protocol implementation and are used by both servers and clients.

## 🏗️ Implementation

### `MCP\Types\Implementation`

Describes the name and version of an MCP implementation.

```php
class Implementation
{
    public function __construct(
        private string $name,
        private string $version,
        private ?string $title = null
    );

    public function getName(): string;
    public function getVersion(): string;
    public function getTitle(): ?string;
}
```

**Usage:**

```php
// Basic implementation
$impl = new Implementation('my-server', '1.0.0');

// With title
$impl = new Implementation('my-server', '1.0.0', 'My Awesome MCP Server');

// Access properties
echo $impl->getName();    // 'my-server'
echo $impl->getVersion(); // '1.0.0'
echo $impl->getTitle();   // 'My Awesome MCP Server'
```

## 🔧 Capabilities

### `MCP\Types\Capabilities\ServerCapabilities`

Defines what capabilities a server supports.

```php
class ServerCapabilities
{
    public function __construct(
        private ?ToolsCapability $tools = null,
        private ?ResourcesCapability $resources = null,
        private ?PromptsCapability $prompts = null,
        private ?LoggingCapability $logging = null,
        private ?SamplingCapability $sampling = null
    );
}
```

**Usage:**

```php
$capabilities = new ServerCapabilities(
    tools: new ToolsCapability(listChanged: true),
    resources: new ResourcesCapability(
        subscribe: true,
        listChanged: true
    ),
    prompts: new PromptsCapability(listChanged: true)
);
```

### `MCP\Types\Capabilities\ClientCapabilities`

Defines what capabilities a client supports.

```php
class ClientCapabilities
{
    public function __construct(
        private ?SamplingCapability $sampling = null,
        private ?RootsCapability $roots = null
    );
}
```

## 🛠️ Tools

### `MCP\Types\Tools\Tool`

Represents a tool that can be called by clients.

```php
class Tool
{
    public function __construct(
        private string $name,
        private ?string $description = null,
        private ?array $inputSchema = null
    );

    public function getName(): string;
    public function getDescription(): ?string;
    public function getInputSchema(): ?array;
}
```

**Usage:**

```php
$tool = new Tool(
    name: 'calculate_tax',
    description: 'Calculate tax amount for a given price',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'amount' => ['type' => 'number', 'minimum' => 0],
            'tax_rate' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1]
        ],
        'required' => ['amount', 'tax_rate']
    ]
);
```

### `MCP\Types\Results\CallToolResult`

Result returned from tool execution.

```php
class CallToolResult
{
    public function __construct(
        private array $content,
        private ?bool $isError = null
    );

    public function getContent(): array;
    public function isError(): bool;
}
```

## 📚 Resources

### `MCP\Types\Resources\Resource`

Represents a resource that provides data.

```php
class Resource
{
    public function __construct(
        private string $uri,
        private string $name,
        private ?string $description = null,
        private ?string $mimeType = null
    );

    public function getUri(): string;
    public function getName(): string;
    public function getDescription(): ?string;
    public function getMimeType(): ?string;
}
```

**Usage:**

```php
$resource = new Resource(
    uri: 'file://config/database.json',
    name: 'Database Configuration',
    description: 'Current database connection settings',
    mimeType: 'application/json'
);
```

### `MCP\Types\Resources\ResourceTemplate`

Template for parameterized resources.

```php
class ResourceTemplate
{
    public function __construct(
        private string $uriTemplate
    );

    public function getUriTemplate(): string;
    public function expandUri(array $variables): string;
}
```

**Usage:**

```php
$template = new ResourceTemplate('users://profile/{user_id}');
$uri = $template->expandUri(['user_id' => '123']); // 'users://profile/123'
```

## 💬 Prompts

### `MCP\Types\Prompts\Prompt`

Represents a prompt template.

```php
class Prompt
{
    public function __construct(
        private string $name,
        private ?string $description = null,
        private ?array $arguments = null
    );

    public function getName(): string;
    public function getDescription(): ?string;
    public function getArguments(): ?array;
}
```

### `MCP\Types\Prompts\PromptMessage`

Individual message in a prompt.

```php
class PromptMessage
{
    public function __construct(
        private string $role,
        private Content $content
    );

    public function getRole(): string;
    public function getContent(): Content;
}
```

## 📄 Content Types

### `MCP\Types\Content\TextContent`

Text content in responses.

```php
class TextContent implements Content
{
    public function __construct(private string $text);

    public function getText(): string;
    public function getType(): string; // Returns 'text'
}
```

### `MCP\Types\Content\ImageContent`

Image content in responses.

```php
class ImageContent implements Content
{
    public function __construct(
        private string $data,
        private string $mimeType
    );

    public function getData(): string;
    public function getMimeType(): string;
    public function getType(): string; // Returns 'image'
}
```

### `MCP\Types\Content\ResourceContent`

Resource reference in responses.

```php
class ResourceContent implements Content
{
    public function __construct(
        private ResourceReference $resource
    );

    public function getResource(): ResourceReference;
    public function getType(): string; // Returns 'resource'
}
```

## ❌ Error Types

### `MCP\Types\McpError`

Standard MCP error with JSON-RPC error codes.

```php
class McpError extends Exception
{
    public function __construct(
        private int $code,
        string $message,
        private mixed $data = null
    );

    public function getCode(): int;
    public function getData(): mixed;
}
```

**Standard Error Codes:**

```php
// JSON-RPC standard errors
-32700  // Parse error
-32600  // Invalid request
-32601  // Method not found
-32602  // Invalid params
-32603  // Internal error

// MCP-specific errors
-32000  // Connection closed
-32001  // Request timeout
-32002  // Cancelled
```

**Usage:**

```php
// Invalid parameters
throw new McpError(-32602, 'Invalid email format', [
    'field' => 'email',
    'provided' => $email,
    'expected' => 'valid email address'
]);

// Internal error
throw new McpError(-32603, 'Database connection failed');

// Custom application error
throw new McpError(-32000, 'Insufficient funds for transaction');
```

## 🔄 Request/Response Types

### Request Types

```php
// Tool call request
class CallToolRequest
{
    public string $method = 'tools/call';
    public array $params;
}

// Resource read request
class ReadResourceRequest
{
    public string $method = 'resources/read';
    public array $params;
}

// Prompt get request
class GetPromptRequest
{
    public string $method = 'prompts/get';
    public array $params;
}
```

### Response Types

```php
// Success response
class SuccessResponse
{
    public function __construct(
        private mixed $result,
        private ?string $id = null
    );
}

// Error response
class ErrorResponse
{
    public function __construct(
        private McpError $error,
        private ?string $id = null
    );
}
```

## 🔍 Validation Types

### `MCP\Types\Validation\JsonSchema`

JSON Schema validation support.

```php
class JsonSchema
{
    public static function validate(mixed $data, array $schema): ValidationResult;
    public static function getErrors(mixed $data, array $schema): array;
}
```

**Usage:**

```php
$schema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string', 'minLength' => 1],
        'age' => ['type' => 'integer', 'minimum' => 0]
    ],
    'required' => ['name']
];

$data = ['name' => 'John', 'age' => 30];

if (JsonSchema::validate($data, $schema)->isValid()) {
    echo "Data is valid!";
} else {
    $errors = JsonSchema::getErrors($data, $schema);
    foreach ($errors as $error) {
        echo "Validation error: {$error}\n";
    }
}
```

## 🎯 Usage Patterns

### Type Safety

```php
// Use type hints for better IDE support and runtime safety
function processUser(Implementation $impl, Tool $tool): CallToolResult
{
    // Implementation here
}

// Validate types at runtime
function validateImplementation($impl): void
{
    if (!$impl instanceof Implementation) {
        throw new TypeError('Expected Implementation instance');
    }
}
```

### Factory Patterns

```php
class ToolFactory
{
    public static function createCalculatorTool(string $operation): Tool
    {
        $schemas = [
            'add' => ['properties' => ['a' => ['type' => 'number'], 'b' => ['type' => 'number']]],
            'sqrt' => ['properties' => ['number' => ['type' => 'number', 'minimum' => 0]]]
        ];

        return new Tool(
            name: $operation,
            description: "Perform {$operation} operation",
            inputSchema: array_merge(['type' => 'object'], $schemas[$operation] ?? [])
        );
    }
}
```

### Builder Patterns

```php
class CapabilitiesBuilder
{
    private ?ToolsCapability $tools = null;
    private ?ResourcesCapability $resources = null;
    private ?PromptsCapability $prompts = null;

    public function withTools(bool $listChanged = false): self
    {
        $this->tools = new ToolsCapability(listChanged: $listChanged);
        return $this;
    }

    public function withResources(bool $subscribe = false, bool $listChanged = false): self
    {
        $this->resources = new ResourcesCapability(
            subscribe: $subscribe,
            listChanged: $listChanged
        );
        return $this;
    }

    public function build(): ServerCapabilities
    {
        return new ServerCapabilities(
            tools: $this->tools,
            resources: $this->resources,
            prompts: $this->prompts
        );
    }
}

// Usage
$capabilities = (new CapabilitiesBuilder())
    ->withTools(listChanged: true)
    ->withResources(subscribe: true, listChanged: true)
    ->build();
```

## 📚 Related References

- [Server API](../server.md) - Server implementation using these types
- [Client API](../client.md) - Client implementation using these types
- [Request/Response Types](request-response.md) - Protocol message types
- [Error Types](errors.md) - Comprehensive error handling
- [Validation System](validation.md) - Input/output validation

---

**These core types provide the foundation for all MCP operations. Master them to build robust, type-safe MCP applications!** 🚀
