# Building Powerful MCP Tools

Tools are the heart of MCP servers - they're the functions that AI models can call to perform actions and get things done. This comprehensive guide will teach you how to build powerful, robust, and user-friendly tools that make your MCP server incredibly valuable.

## 🎯 What You'll Learn

- 🔧 **Tool Design Principles** - How to design effective tools
- 📝 **Input Schema Design** - Creating clear, validated inputs
- ⚡ **Handler Implementation** - Writing efficient tool handlers
- 🛡️ **Error Handling** - Robust error management
- 🧪 **Testing Strategies** - Ensuring tool reliability
- 🎨 **Advanced Patterns** - Complex tool architectures

## 🔧 Tool Fundamentals

### Basic Tool Structure

Every MCP tool consists of four key components:

```php
$server->tool(
    'tool_name',           // 1. Unique identifier
    'Tool description',    // 2. Human-readable description
    $inputSchema,          // 3. JSON Schema for validation
    $handlerFunction       // 4. Implementation function
);
```

### Simple Example

```php
$server->tool(
    'greet_user',
    'Generate a personalized greeting',
    [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Name of the person to greet'
            ],
            'style' => [
                'type' => 'string',
                'enum' => ['formal', 'casual', 'enthusiastic'],
                'description' => 'Greeting style',
                'default' => 'casual'
            ]
        ],
        'required' => ['name']
    ],
    function (array $args): array {
        $name = $args['name'];
        $style = $args['style'] ?? 'casual';

        $greeting = match($style) {
            'formal' => "Good day, {$name}. I hope this message finds you well.",
            'enthusiastic' => "Hey there, {$name}! So excited to connect with you! 🎉",
            default => "Hi {$name}! Nice to meet you."
        };

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $greeting
                ]
            ]
        ];
    }
);
```

## 📝 Input Schema Design

### JSON Schema Basics

MCP uses JSON Schema to validate tool inputs. Here are the essential patterns:

#### String Parameters

```php
'name' => [
    'type' => 'string',
    'description' => 'User name',
    'minLength' => 1,
    'maxLength' => 100,
    'pattern' => '^[a-zA-Z\\s]+$'  // Only letters and spaces
]
```

#### Number Parameters

```php
'age' => [
    'type' => 'integer',
    'description' => 'User age',
    'minimum' => 0,
    'maximum' => 150
],
'price' => [
    'type' => 'number',
    'description' => 'Product price',
    'minimum' => 0,
    'multipleOf' => 0.01  // Cents precision
]
```

#### Enum Parameters

```php
'priority' => [
    'type' => 'string',
    'enum' => ['low', 'medium', 'high', 'critical'],
    'description' => 'Task priority level',
    'default' => 'medium'
]
```

#### Array Parameters

```php
'tags' => [
    'type' => 'array',
    'items' => [
        'type' => 'string',
        'minLength' => 1
    ],
    'description' => 'List of tags',
    'maxItems' => 10,
    'uniqueItems' => true
]
```

#### Object Parameters

```php
'user' => [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'email' => ['type' => 'string', 'format' => 'email'],
        'age' => ['type' => 'integer', 'minimum' => 0]
    ],
    'required' => ['name', 'email'],
    'additionalProperties' => false
]
```

## ⚡ Handler Implementation

### Handler Function Signature

```php
function (array $args): array {
    // Your implementation here
    return [
        'content' => [
            [
                'type' => 'text',
                'text' => 'Your result here'
            ]
        ]
    ];
}
```

### Content Types

MCP supports multiple content types in responses:

#### Text Content

```php
return [
    'content' => [
        [
            'type' => 'text',
            'text' => 'Plain text response'
        ]
    ]
];
```

#### Image Content

```php
return [
    'content' => [
        [
            'type' => 'image',
            'data' => base64_encode($imageData),
            'mimeType' => 'image/png'
        ]
    ]
];
```

#### Resource References

```php
return [
    'content' => [
        [
            'type' => 'resource',
            'resource' => [
                'uri' => 'file://path/to/resource',
                'text' => 'Optional description'
            ]
        ]
    ]
];
```

### Advanced Handler Patterns

#### Database Integration

```php
$server->tool(
    'get_user',
    'Retrieve user information',
    [
        'type' => 'object',
        'properties' => [
            'user_id' => ['type' => 'integer', 'minimum' => 1]
        ],
        'required' => ['user_id']
    ],
    function (array $args) use ($database): array {
        try {
            $user = $database->findUser($args['user_id']);

            if (!$user) {
                throw new McpError(
                    code: -32602,
                    message: "User with ID {$args['user_id']} not found"
                );
            }

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "User: {$user['name']} ({$user['email']})"
                    ]
                ]
            ];

        } catch (DatabaseException $e) {
            throw new McpError(
                code: -32603,
                message: 'Database error: ' . $e->getMessage()
            );
        }
    }
);
```

#### External API Integration

```php
$server->tool(
    'get_weather',
    'Get current weather for a location',
    [
        'type' => 'object',
        'properties' => [
            'location' => ['type' => 'string'],
            'units' => ['type' => 'string', 'enum' => ['metric', 'imperial'], 'default' => 'metric']
        ],
        'required' => ['location']
    ],
    function (array $args) use ($httpClient, $apiKey): array {
        try {
            $response = await $httpClient->get(
                "https://api.openweathermap.org/data/2.5/weather",
                [
                    'query' => [
                        'q' => $args['location'],
                        'appid' => $apiKey,
                        'units' => $args['units'] ?? 'metric'
                    ]
                ]
            );

            $data = json_decode($response->getBody(), true);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Weather in {$data['name']}: " .
                                 "{$data['main']['temp']}° {$data['weather'][0]['description']}"
                    ]
                ]
            ];

        } catch (Exception $e) {
            throw new McpError(
                code: -32603,
                message: 'Weather API error: ' . $e->getMessage()
            );
        }
    }
);
```

## 🛡️ Error Handling

### MCP Error Codes

Use standard JSON-RPC error codes:

```php
// Invalid parameters
throw new McpError(-32602, 'Invalid parameters: email format is incorrect');

// Internal error
throw new McpError(-32603, 'Database connection failed');

// Custom application errors
throw new McpError(-32000, 'Business rule violation: insufficient funds');
```

### Validation Patterns

```php
function (array $args): array {
    // 1. Validate required parameters
    if (empty($args['email'])) {
        throw new McpError(-32602, 'Email is required');
    }

    // 2. Validate format
    if (!filter_var($args['email'], FILTER_VALIDATE_EMAIL)) {
        throw new McpError(-32602, 'Invalid email format');
    }

    // 3. Validate business rules
    if ($args['age'] < 18) {
        throw new McpError(-32000, 'User must be 18 or older');
    }

    // 4. Your implementation
    return $this->processUser($args);
}
```

## 🎨 Advanced Tool Patterns

### File Processing Tool

```php
$server->tool(
    'process_file',
    'Process and analyze a file',
    [
        'type' => 'object',
        'properties' => [
            'file_path' => ['type' => 'string'],
            'operation' => [
                'type' => 'string',
                'enum' => ['analyze', 'transform', 'validate']
            ]
        ],
        'required' => ['file_path', 'operation']
    ],
    function (array $args) use ($fileProcessor): array {
        $filePath = $args['file_path'];
        $operation = $args['operation'];

        // Security: Validate file path
        if (!$fileProcessor->isValidPath($filePath)) {
            throw new McpError(-32602, 'Invalid file path');
        }

        if (!file_exists($filePath)) {
            throw new McpError(-32602, "File not found: {$filePath}");
        }

        try {
            $result = match($operation) {
                'analyze' => $fileProcessor->analyzeFile($filePath),
                'transform' => $fileProcessor->transformFile($filePath),
                'validate' => $fileProcessor->validateFile($filePath),
                default => throw new McpError(-32602, "Unknown operation: {$operation}")
            };

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "File {$operation} completed: " . json_encode($result, JSON_PRETTY_PRINT)
                    ]
                ]
            ];

        } catch (Exception $e) {
            throw new McpError(-32603, "File processing failed: " . $e->getMessage());
        }
    }
);
```

### Batch Operation Tool

```php
$server->tool(
    'batch_process',
    'Process multiple items in a batch',
    [
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'maxItems' => 100
            ],
            'operation' => ['type' => 'string']
        ],
        'required' => ['items', 'operation']
    ],
    function (array $args): array {
        $items = $args['items'];
        $operation = $args['operation'];

        $results = [];
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $result = $this->processItem($item, $operation);
                $results[] = [
                    'index' => $index,
                    'item' => $item,
                    'result' => $result,
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'item' => $item,
                    'error' => $e->getMessage(),
                    'status' => 'error'
                ];
            }
        }

        $summary = "Batch processing completed:\n";
        $summary .= "✅ Successful: " . count($results) . "\n";
        $summary .= "❌ Failed: " . count($errors) . "\n";

        if (!empty($errors)) {
            $summary .= "\nErrors:\n";
            foreach ($errors as $error) {
                $summary .= "• Item {$error['index']}: {$error['error']}\n";
            }
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $summary
                ]
            ]
        ];
    }
);
```

## 🧪 Testing Your Tools

### Unit Testing

```php
use PHPUnit\Framework\TestCase;

class CalculatorToolTest extends TestCase
{
    private McpServer $server;

    protected function setUp(): void
    {
        $this->server = new McpServer(new Implementation('test-server', '1.0.0'));

        // Register the tool under test
        $this->server->tool(
            'add',
            'Add two numbers',
            [
                'type' => 'object',
                'properties' => [
                    'a' => ['type' => 'number'],
                    'b' => ['type' => 'number']
                ],
                'required' => ['a', 'b']
            ],
            function (array $args): array {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => ($args['a'] + $args['b'])
                        ]
                    ]
                ];
            }
        );
    }

    public function testAdditionTool(): void
    {
        // Test normal operation
        $result = $this->server->callToolDirectly('add', ['a' => 5, 'b' => 3]);
        $this->assertEquals('8', $result['content'][0]['text']);

        // Test edge cases
        $result = $this->server->callToolDirectly('add', ['a' => 0, 'b' => 0]);
        $this->assertEquals('0', $result['content'][0]['text']);

        // Test negative numbers
        $result = $this->server->callToolDirectly('add', ['a' => -5, 'b' => 3]);
        $this->assertEquals('-2', $result['content'][0]['text']);
    }

    public function testInvalidInput(): void
    {
        $this->expectException(McpError::class);
        $this->server->callToolDirectly('add', ['a' => 'not a number', 'b' => 3]);
    }
}
```

### Integration Testing

```php
class ToolIntegrationTest extends TestCase
{
    public function testToolWithRealServer(): void
    {
        $server = new McpServer(new Implementation('test', '1.0.0'));

        // Add your tool
        $server->tool('my_tool', 'Description', $schema, $handler);

        // Test with real transport
        $transport = new StdioServerTransport();

        async(function() use ($server, $transport) {
            await $server->connect($transport);

            // Server is now ready for real client connections
            $this->assertTrue($server->isConnected());
        });
    }
}
```

## 🎨 Design Principles

### 1. Single Responsibility

Each tool should do one thing well:

```php
// ✅ Good: Focused tool
$server->tool('calculate_tax', 'Calculate tax amount', $schema, $handler);

// ❌ Bad: Does too many things
$server->tool('process_order', 'Calculate tax, apply discounts, send email, update inventory...', $schema, $handler);
```

### 2. Clear Naming

Use descriptive, action-oriented names:

```php
// ✅ Good: Clear action verbs
'create_user', 'send_email', 'calculate_total', 'generate_report'

// ❌ Bad: Vague or unclear
'process', 'handle', 'do_stuff', 'utility'
```

### 3. Comprehensive Descriptions

Write descriptions that help AI models understand when to use your tool:

```php
// ✅ Good: Detailed, helpful description
'Calculate the total cost of an order including tax, shipping, and any applicable discounts'

// ❌ Bad: Too brief or unclear
'Calculate total'
```

### 4. Robust Input Validation

Always validate inputs thoroughly:

```php
function (array $args): array {
    // Validate presence
    if (!isset($args['amount'])) {
        throw new McpError(-32602, 'Amount is required');
    }

    // Validate type
    if (!is_numeric($args['amount'])) {
        throw new McpError(-32602, 'Amount must be a number');
    }

    // Validate business rules
    if ($args['amount'] < 0) {
        throw new McpError(-32602, 'Amount cannot be negative');
    }

    // Continue with implementation...
}
```

## 🔄 Advanced Patterns

### Stateful Tools

```php
class StatefulCalculator
{
    private float $memory = 0;

    public function getMemoryTool(): callable
    {
        return function (array $args): array {
            $operation = $args['operation'] ?? 'recall';
            $value = $args['value'] ?? 0;

            switch ($operation) {
                case 'store':
                    $this->memory = $value;
                    return ['content' => [['type' => 'text', 'text' => "Stored: {$value}"]]];

                case 'recall':
                    return ['content' => [['type' => 'text', 'text' => "Memory: {$this->memory}"]]];

                case 'add':
                    $this->memory += $value;
                    return ['content' => [['type' => 'text', 'text' => "Memory: {$this->memory}"]]];

                case 'clear':
                    $this->memory = 0;
                    return ['content' => [['type' => 'text', 'text' => "Memory cleared"]]];

                default:
                    throw new McpError(-32602, "Unknown operation: {$operation}");
            }
        };
    }
}

$calculator = new StatefulCalculator();
$server->tool('memory', 'Calculator memory operations', $schema, $calculator->getMemoryTool());
```

### Async Tools

```php
$server->tool(
    'fetch_data',
    'Fetch data from external API',
    $schema,
    function (array $args) use ($httpClient): array {
        return async(function() use ($args, $httpClient) {
            try {
                $response = await $httpClient->get($args['url']);
                $data = json_decode($response->getBody(), true);

                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode($data, JSON_PRETTY_PRINT)
                        ]
                    ]
                ];

            } catch (Exception $e) {
                throw new McpError(-32603, 'Failed to fetch data: ' . $e->getMessage());
            }
        });
    }
);
```

### Tool Chaining

```php
class ToolChain
{
    private array $results = [];

    public function addChainedTool(McpServer $server): void
    {
        $server->tool(
            'process_chain',
            'Process a chain of operations',
            [
                'type' => 'object',
                'properties' => [
                    'steps' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'operation' => ['type' => 'string'],
                                'parameters' => ['type' => 'object']
                            ]
                        ]
                    ]
                ],
                'required' => ['steps']
            ],
            function (array $args): array {
                $results = [];
                $previousResult = null;

                foreach ($args['steps'] as $step) {
                    $parameters = $step['parameters'];

                    // Allow referencing previous result
                    if (isset($parameters['use_previous_result'])) {
                        $parameters['input'] = $previousResult;
                    }

                    $result = $this->executeStep($step['operation'], $parameters);
                    $results[] = $result;
                    $previousResult = $result;
                }

                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Chain completed: ' . json_encode($results, JSON_PRETTY_PRINT)
                        ]
                    ]
                ];
            }
        );
    }
}
```

## 📊 Tool Performance

### Optimization Tips

1. **Cache Expensive Operations**

```php
private array $cache = [];

function (array $args): array {
    $cacheKey = md5(json_encode($args));

    if (isset($this->cache[$cacheKey])) {
        return $this->cache[$cacheKey];
    }

    $result = $this->expensiveOperation($args);
    $this->cache[$cacheKey] = $result;

    return $result;
}
```

2. **Use Async for I/O Operations**

```php
function (array $args) use ($httpClient): array {
    return async(function() use ($args, $httpClient) {
        // Non-blocking HTTP request
        $response = await $httpClient->get($args['url']);
        return $this->processResponse($response);
    });
}
```

3. **Implement Timeouts**

```php
function (array $args): array {
    $timeout = $args['timeout'] ?? 30; // seconds

    return async(function() use ($args, $timeout) {
        $promise = $this->longRunningOperation($args);

        try {
            $result = await timeout($promise, $timeout);
            return $result;
        } catch (TimeoutException $e) {
            throw new McpError(-32603, 'Operation timed out');
        }
    });
}
```

## 🔍 Tool Discovery and Documentation

### Self-Documenting Tools

```php
$server->tool(
    'advanced_search',
    'Search with advanced filtering and sorting options',
    [
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'Search query string'
            ],
            'filters' => [
                'type' => 'object',
                'description' => 'Additional filters to apply',
                'properties' => [
                    'category' => ['type' => 'string'],
                    'date_range' => [
                        'type' => 'object',
                        'properties' => [
                            'start' => ['type' => 'string', 'format' => 'date'],
                            'end' => ['type' => 'string', 'format' => 'date']
                        ]
                    ]
                ]
            },
            'sort' => [
                'type' => 'object',
                'properties' => [
                    'field' => ['type' => 'string'],
                    'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']]
                ]
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 10
            ]
        ],
        'required' => ['query'],
        'examples' => [
            [
                'description' => 'Basic search',
                'value' => ['query' => 'MCP tutorial']
            ],
            [
                'description' => 'Filtered search',
                'value' => [
                    'query' => 'programming',
                    'filters' => ['category' => 'tutorials'],
                    'sort' => ['field' => 'date', 'direction' => 'desc'],
                    'limit' => 5
                ]
            ]
        ]
    ],
    $searchHandler
);
```

## 🎯 Best Practices Summary

### ✅ Do's

- **Use clear, descriptive names** for tools
- **Write comprehensive descriptions** that help AI understand usage
- **Validate all inputs** thoroughly
- **Handle errors gracefully** with appropriate error codes
- **Return consistent response formats**
- **Include examples** in your schema when helpful
- **Test thoroughly** with unit and integration tests
- **Document edge cases** and limitations

### ❌ Don'ts

- **Don't create overly complex tools** that do too many things
- **Don't skip input validation** - always validate
- **Don't ignore error handling** - plan for failures
- **Don't use unclear parameter names** like 'data' or 'input'
- **Don't return inconsistent response formats**
- **Don't forget to test edge cases**
- **Don't expose sensitive information** in error messages

## 🔗 Related Resources

- [Server API Reference](../../api/server.md)
- [Working Examples](../../../examples/getting-started/)
- [Error Handling Guide](../client-development/error-handling.md)
- [Testing Strategies](testing-servers.md)

---

**Master these tool development patterns and you'll be able to build MCP servers that are powerful, reliable, and delightful to use!** 🚀
