# OpenAI Tool Calling with MCP Integration

This guide demonstrates how to use OpenAI's function calling feature to interact with MCP servers, specifically showing how OpenAI can call FullCX MCP tools to manage products, features, and requirements.

## Overview

OpenAI's function calling allows the AI model to call external functions/tools based on user requests. By integrating this with MCP (Model Context Protocol), we can give OpenAI the ability to:

- Query and manage products in FullCX
- Create and update features and requirements
- Analyze product data and generate insights
- Perform complex workflows across multiple MCP tools

## Prerequisites

- PHP 8.1+ with `json`, `mbstring` extensions
- Composer installed
- FullCX account with API access
- OpenAI API key with function calling support
- [OpenAI PHP client](https://github.com/openai-php/client): `composer require openai-php/client`
- PHP MCP SDK: `composer require dalehurley/php-mcp-sdk`

## Basic Setup

### Step 1: Environment Configuration

```bash
# .env file
FULLCX_API_TOKEN=your_fullcx_token_here
FULLCX_MCP_URL=https://full.cx/mcp
OPENAI_API_KEY=your_openai_key_here
```

### Step 2: Basic OpenAI Tool Calling with MCP

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use OpenAI;
use Amp\Loop;

class OpenAIMCPAgent
{
    private FullCXClient $fullcx;
    private OpenAI\Client $openai;

    public function __construct(string $fullcxToken, string $openaiKey)
    {
        $this->fullcx = new FullCXClient(
            url: 'https://full.cx/mcp',
            bearerToken: $fullcxToken
        );

        $this->openai = OpenAI::client($openaiKey);
    }

    /**
     * Convert MCP tool schema to OpenAI function definition
     */
    private function convertMCPToolToOpenAIFunction(\MCP\Types\Tool $mcpTool): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $mcpTool->getName(),
                'description' => $mcpTool->getDescription(),
                'parameters' => $mcpTool->getInputSchema() ?? [
                    'type' => 'object',
                    'properties' => []
                ]
            ]
        ];
    }

    /**
     * Get tool definitions dynamically from MCP server
     */
    public function getMCPToolDefinitions(): \Generator
    {
        echo "🔍 Discovering tools from MCP server...\n";

        // Get available tools from MCP server
        $toolsResult = yield $this->fullcx->listTools();
        $mcpTools = $toolsResult->getTools();

        $openaiTools = [];
        foreach ($mcpTools as $mcpTool) {
            $openaiFunction = $this->convertMCPToolToOpenAIFunction($mcpTool);
            $openaiTools[] = $openaiFunction;

            echo "  📋 Found tool: {$mcpTool->getName()}\n";
        }

        echo "✅ Discovered " . count($openaiTools) . " tools from MCP server\n\n";

        return $openaiTools;
    }

    /**
     * Execute MCP tool calls dynamically
     */
    public function executeMCPTool(string $toolName, array $arguments): \Generator
    {
        // Call the MCP tool directly using the client's callToolByName method
        $result = yield $this->fullcx->callToolByName($toolName, $arguments);

        // Parse the result - MCP tools typically return content in text format
        $content = $result['content'] ?? [];

        if (!empty($content)) {
            $textContent = $content[0]['text'] ?? '';

            // Try to parse as JSON if it looks like JSON, otherwise return as text
            if ($textContent && (str_starts_with($textContent, '{') || str_starts_with($textContent, '['))) {
                $data = json_decode($textContent, true);
                return json_encode([
                    'success' => true,
                    'data' => $data,
                    'raw_text' => $textContent
                ]);
            } else {
                return json_encode([
                    'success' => true,
                    'text' => $textContent,
                    'message' => "Tool '{$toolName}' executed successfully"
                ]);
            }
        }

        return json_encode([
            'success' => true,
            'result' => $result,
            'message' => "Tool '{$toolName}' executed successfully"
        ]);
    }

    /**
     * Process user request with OpenAI function calling
     */
    public function processRequest(string $userMessage): \Generator
    {
        yield $this->fullcx->connect();

        try {
            echo "🤖 Processing request: {$userMessage}\n\n";

            // Get available tools from MCP server dynamically
            $toolDefinitions = yield $this->getMCPToolDefinitions();

            // Initial OpenAI call with available tools
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a product management assistant that can help manage products, features, and requirements using FullCX. Use the available tools to fulfill user requests.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessage
                    ]
                ],
                'tools' => $toolDefinitions,
                'tool_choice' => 'auto'
            ]);

            $message = $response->choices[0]->message;
            $messages = [
                ['role' => 'system', 'content' => 'You are a product management assistant.'],
                ['role' => 'user', 'content' => $userMessage],
                ['role' => 'assistant', 'content' => $message->content, 'tool_calls' => $message->toolCalls]
            ];

            // Process tool calls if any
            if ($message->toolCalls) {
                echo "🔧 OpenAI is calling " . count($message->toolCalls) . " tools:\n";

                foreach ($message->toolCalls as $toolCall) {
                    $toolName = $toolCall->function->name;
                    $arguments = json_decode($toolCall->function->arguments, true);

                    echo "  📞 Calling: {$toolName}\n";
                    echo "     Arguments: " . json_encode($arguments) . "\n";

                    try {
                        $result = yield $this->executeMCPTool($toolName, $arguments);
                        echo "     ✅ Result: " . substr($result, 0, 100) . "...\n\n";

                        // Add tool result to messages
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall->id,
                            'content' => $result
                        ];
                    } catch (\Exception $e) {
                        echo "     ❌ Error: {$e->getMessage()}\n\n";

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall->id,
                            'content' => json_encode(['error' => $e->getMessage()])
                        ];
                    }
                }

                // Get final response from OpenAI with tool results
                $finalResponse = $this->openai->chat()->create([
                    'model' => 'gpt-4.1',
                    'messages' => $messages
                ]);

                echo "🎯 Final Response:\n";
                echo $finalResponse->choices[0]->message->content . "\n";

                return $finalResponse->choices[0]->message->content;
            } else {
                echo "💬 Direct Response:\n";
                echo $message->content . "\n";

                return $message->content;
            }

        } finally {
            yield $this->fullcx->close();
        }
    }
}

// Usage Example
$agent = new OpenAIMCPAgent(
    fullcxToken: $_ENV['FULLCX_API_TOKEN'],
    openaiKey: $_ENV['OPENAI_API_KEY']
);

Loop::run(function() use ($agent) {
    // Example requests that will trigger tool calls
    $requests = [
        "Show me all the products in the system",
        "Create a new feature called 'Advanced Analytics' for product ID 'product-123' with description 'Real-time analytics dashboard with customizable reports'",
        "Get detailed information about product ID 'product-123'",
        "Create a high-priority requirement for product 'product-123' called 'Performance Optimization' to improve page load times"
    ];

    foreach ($requests as $request) {
        echo str_repeat("=", 80) . "\n";
        yield $agent->processRequest($request);
        echo "\n";
    }
});
```

## Advanced Example: Multi-Step Workflow

This example shows OpenAI orchestrating a complex workflow across multiple MCP tools:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use OpenAI;
use Amp\Loop;

class AdvancedOpenAIMCPAgent
{
    private FullCXClient $fullcx;
    private OpenAI\Client $openai;

    public function __construct(string $fullcxToken, string $openaiKey)
    {
        $this->fullcx = new FullCXClient(
            url: 'https://full.cx/mcp',
            bearerToken: $fullcxToken
        );

        $this->openai = OpenAI::client($openaiKey);
    }

    /**
     * Extended tool definitions including analysis and workflow tools
     */
    public function getExtendedToolDefinitions(): array
    {
        return [
            // Basic CRUD tools
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_products',
                    'description' => 'List all products with their basic information',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'default' => 10]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'analyze_product',
                    'description' => 'Get comprehensive analysis of a product including features, requirements, and ideas',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => ['type' => 'string', 'description' => 'Product ID to analyze']
                        ],
                        'required' => ['product_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_feature_with_requirements',
                    'description' => 'Create a feature and multiple requirements in one workflow',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => ['type' => 'string'],
                            'feature_name' => ['type' => 'string'],
                            'feature_description' => ['type' => 'string'],
                            'requirements' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5]
                                    ]
                                ]
                            ]
                        ],
                        'required' => ['product_id', 'feature_name', 'feature_description', 'requirements']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_ideas',
                    'description' => 'Generate and create ideas for a product based on analysis',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => ['type' => 'string'],
                            'focus_area' => ['type' => 'string', 'description' => 'Area to focus idea generation on']
                        ],
                        'required' => ['product_id']
                    ]
                ]
            ]
        ];
    }

    /**
     * Execute extended MCP tools with complex workflows
     */
    public function executeExtendedTool(string $toolName, array $arguments): \Generator
    {
        switch ($toolName) {
            case 'list_products':
                $result = yield $this->fullcx->listProducts($arguments['limit'] ?? 10);
                return json_decode($result['content'][0]['text'], true);

            case 'analyze_product':
                // Get comprehensive product data
                $product = yield $this->fullcx->getProductDetails($arguments['product_id'], true, true);
                $productData = json_decode($product['content'][0]['text'], true);

                $features = yield $this->fullcx->listFeatures($arguments['product_id']);
                $featureData = json_decode($features['content'][0]['text'], true);

                $requirements = yield $this->fullcx->listRequirements($arguments['product_id']);
                $reqData = json_decode($requirements['content'][0]['text'], true);

                // Compile analysis
                $analysis = [
                    'product' => $productData,
                    'summary' => [
                        'total_features' => count($featureData),
                        'total_requirements' => count($reqData),
                        'feature_status_distribution' => $this->analyzeFeatureStatus($featureData),
                        'requirement_priority_distribution' => $this->analyzeRequirementPriorities($reqData)
                    ],
                    'features' => array_slice($featureData, 0, 5), // Top 5 features
                    'high_priority_requirements' => array_filter($reqData, fn($req) => ($req['priority'] ?? 3) <= 2)
                ];

                return $analysis;

            case 'create_feature_with_requirements':
                // Create feature first
                $featureResult = yield $this->fullcx->createFeature(
                    productId: $arguments['product_id'],
                    name: $arguments['feature_name'],
                    description: $arguments['feature_description']
                );

                $featureData = json_decode($featureResult['content'][0]['text'], true);
                $featureId = $featureData['id'];

                // Create requirements
                $createdRequirements = [];
                foreach ($arguments['requirements'] as $reqSpec) {
                    $reqResult = yield $this->fullcx->createRequirement(
                        productId: $arguments['product_id'],
                        name: $reqSpec['name'],
                        description: $reqSpec['description'],
                        featureId: $featureId,
                        priority: $reqSpec['priority'] ?? 2
                    );

                    $createdRequirements[] = json_decode($reqResult['content'][0]['text'], true);
                }

                return [
                    'feature' => $featureData,
                    'requirements' => $createdRequirements,
                    'summary' => "Created feature '{$arguments['feature_name']}' with " . count($createdRequirements) . " requirements"
                ];

            case 'generate_ideas':
                // This is a simplified version - in practice, you might use AI here too
                $focusArea = $arguments['focus_area'] ?? 'general improvement';

                $sampleIdeas = [
                    [
                        'name' => "Enhanced {$focusArea} Dashboard",
                        'description' => "Improve the {$focusArea} experience with better visualizations and real-time updates.",
                        'effort' => 6,
                        'impact' => 8,
                        'timeline' => 'Next'
                    ],
                    [
                        'name' => "AI-Powered {$focusArea} Insights",
                        'description' => "Add machine learning capabilities to provide intelligent insights for {$focusArea}.",
                        'effort' => 8,
                        'impact' => 9,
                        'timeline' => 'Later'
                    ]
                ];

                $createdIdeas = [];
                foreach ($sampleIdeas as $ideaSpec) {
                    $ideaResult = yield $this->fullcx->createIdea(
                        name: $ideaSpec['name'],
                        description: $ideaSpec['description'],
                        ideaableType: 'App\\Models\\Product',
                        ideaableId: $arguments['product_id'],
                        effort: $ideaSpec['effort'],
                        impact: $ideaSpec['impact'],
                        timeline: $ideaSpec['timeline'],
                        status: 'Concept'
                    );

                    $createdIdeas[] = json_decode($ideaResult['content'][0]['text'], true);
                }

                return [
                    'ideas' => $createdIdeas,
                    'focus_area' => $focusArea,
                    'summary' => "Generated " . count($createdIdeas) . " ideas focused on {$focusArea}"
                ];

            default:
                throw new \Exception("Unknown tool: {$toolName}");
        }
    }

    /**
     * Process complex multi-step requests
     */
    public function processComplexRequest(string $userMessage): \Generator
    {
        yield $this->fullcx->connect();

        try {
            echo "🚀 Processing complex request: {$userMessage}\n\n";

            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are an advanced product management AI assistant. You can analyze products, create features with requirements, generate ideas, and perform complex workflows. When users ask for comprehensive analysis or multi-step operations, break them down into appropriate tool calls.'
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage
                ]
            ];

            $maxIterations = 5; // Prevent infinite loops
            $iteration = 0;

            while ($iteration < $maxIterations) {
                $iteration++;
                echo "🔄 Iteration {$iteration}:\n";

                $response = $this->openai->chat()->create([
                    'model' => 'gpt-4.1',
                    'messages' => $messages,
                    'tools' => $this->getExtendedToolDefinitions(),
                    'tool_choice' => 'auto'
                ]);

                $message = $response->choices[0]->message;

                // Add assistant message
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $message->content,
                    'tool_calls' => $message->toolCalls
                ];

                // If no tool calls, we're done
                if (!$message->toolCalls) {
                    echo "💬 Final response: {$message->content}\n";
                    return $message->content;
                }

                // Execute tool calls
                foreach ($message->toolCalls as $toolCall) {
                    $toolName = $toolCall->function->name;
                    $arguments = json_decode($toolCall->function->arguments, true);

                    echo "  🔧 Executing: {$toolName}\n";
                    echo "     Arguments: " . json_encode($arguments, JSON_PRETTY_PRINT) . "\n";

                    try {
                        $result = yield $this->executeExtendedTool($toolName, $arguments);
                        $resultJson = json_encode($result, JSON_PRETTY_PRINT);

                        echo "     ✅ Success: " . substr($resultJson, 0, 200) . "...\n\n";

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall->id,
                            'content' => $resultJson
                        ];
                    } catch (\Exception $e) {
                        echo "     ❌ Error: {$e->getMessage()}\n\n";

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall->id,
                            'content' => json_encode(['error' => $e->getMessage()])
                        ];
                    }
                }
            }

            echo "⚠️ Reached maximum iterations\n";
            return "I've completed the available steps but reached the iteration limit.";

        } finally {
            yield $this->fullcx->close();
        }
    }

    // Helper methods
    private function analyzeFeatureStatus(array $features): array
    {
        $statusCounts = [];
        foreach ($features as $feature) {
            $status = $feature['status'] ?? 'Unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        return $statusCounts;
    }

    private function analyzeRequirementPriorities(array $requirements): array
    {
        $priorityCounts = [];
        foreach ($requirements as $req) {
            $priority = $req['priority'] ?? 3;
            $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;
        }
        ksort($priorityCounts);
        return $priorityCounts;
    }
}

// Advanced Usage Examples
$advancedAgent = new AdvancedOpenAIMCPAgent(
    fullcxToken: $_ENV['FULLCX_API_TOKEN'],
    openaiKey: $_ENV['OPENAI_API_KEY']
);

Loop::run(function() use ($advancedAgent) {
    $complexRequests = [
        "Analyze product 'product-123' and then create a new feature called 'User Dashboard' with 3 requirements focused on improving user experience",

        "Find the product with the most features, analyze it comprehensively, and generate 2 innovative ideas for improving it",

        "Create a complete feature set for 'Mobile Integration' in product 'product-123' including requirements for iOS app, Android app, and API endpoints, then generate ideas for future mobile enhancements"
    ];

    foreach ($complexRequests as $request) {
        echo str_repeat("=", 100) . "\n";
        yield $advancedAgent->processComplexRequest($request);
        echo "\n" . str_repeat("=", 100) . "\n\n";
    }
});
```

## Simple Interactive Example

Here's a simpler, more focused example perfect for getting started:

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use OpenAI\Client as OpenAIClient;
use function Amp\async;

/**
 * Simple OpenAI + FullCX MCP Integration
 *
 * Usage: php openai-mcp-example.php "your request here"
 *
 * Examples:
 *   php openai-mcp-example.php "show me all products"
 *   php openai-mcp-example.php "create a feature called 'User Profile' for product-123"
 */

class SimpleOpenAIMCP
{
    private FullCXClient $fullcx;
    private OpenAIClient $openai;

    public function __construct()
    {
        $this->fullcx = new FullCXClient(
            url: 'https://full.cx/mcp',
            bearerToken: $_ENV['FULLCX_API_TOKEN'] ?? throw new \Exception('FULLCX_API_TOKEN required')
        );

        $this->openai = \OpenAI::client(
            $_ENV['OPENAI_API_KEY'] ?? throw new \Exception('OPENAI_API_KEY required')
        );
    }

    public function chat(string $message): \Generator
    {
        yield $this->fullcx->connect();

        try {
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You help manage products using FullCX. Use tools when needed.'],
                    ['role' => 'user', 'content' => $message]
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'list_products',
                            'description' => 'List products in FullCX',
                            'parameters' => ['type' => 'object', 'properties' => []]
                        ]
                    ],
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'create_feature',
                            'description' => 'Create a new feature',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'product_id' => ['type' => 'string'],
                                    'name' => ['type' => 'string'],
                                    'description' => ['type' => 'string']
                                ],
                                'required' => ['product_id', 'name', 'description']
                            ]
                        ]
                    ]
                ]
            ]);

            $msg = $response->choices[0]->message;

            if ($msg->toolCalls) {
                foreach ($msg->toolCalls as $call) {
                    $args = json_decode($call->function->arguments, true);

                    if ($call->function->name === 'list_products') {
                        $result = yield $this->fullcx->listProducts();
                        echo "📦 Products:\n" . $result['content'][0]['text'] . "\n";
                    } elseif ($call->function->name === 'create_feature') {
                        $result = yield $this->fullcx->createFeature(
                            $args['product_id'],
                            $args['name'],
                            $args['description']
                        );
                        echo "✅ Created feature:\n" . $result['content'][0]['text'] . "\n";
                    }
                }
            }

            echo "🤖 " . $msg->content . "\n";

        } finally {
            yield $this->fullcx->close();
        }
    }
}

// Command line usage
if ($argc > 1) {
    $agent = new SimpleOpenAIMCP();
    $message = implode(' ', array_slice($argv, 1));

    async(function() use ($agent, $message) {
        yield $agent->chat($message);
    })->await();
} else {
    echo "Usage: php openai-mcp-example.php \"your request here\"\n";
}
```

## Key Concepts

### 1. Dynamic Tool Discovery

Instead of hardcoding tool definitions, discover them from the MCP server:

```php
// Get tools from MCP server
$toolsResult = yield $this->fullcx->listTools();
$mcpTools = $toolsResult->getTools();

// Convert to OpenAI function format
foreach ($mcpTools as $mcpTool) {
    $openaiTools[] = [
        'type' => 'function',
        'function' => [
            'name' => $mcpTool->getName(),
            'description' => $mcpTool->getDescription(),
            'parameters' => $mcpTool->getInputSchema() ?? ['type' => 'object', 'properties' => []]
        ]
    ];
}
```

### 2. Dynamic Tool Execution

Execute any MCP tool without hardcoding:

```php
// Generic execution - works with any MCP tool
$result = yield $this->fullcx->callToolByName($toolName, $arguments);
```

### 3. Response Flow

1. **Discovery**: Get available tools from MCP server
2. **User Request**: User makes natural language request
3. **AI Analysis**: OpenAI decides which tools to call
4. **Dynamic Execution**: Execute MCP tools without hardcoded logic
5. **Result Processing**: Parse and return results to OpenAI
6. **Final Response**: OpenAI provides human-friendly response

### 4. Benefits of Dynamic Approach

**✅ Automatic Updates**: When MCP server adds new tools, they're immediately available to OpenAI  
**✅ No Hardcoding**: No need to manually define each tool in your code  
**✅ Schema Accuracy**: Tool schemas come directly from the source  
**✅ Maintainability**: Single integration works with any MCP server  
**✅ Flexibility**: Easy to switch between different MCP servers

## Best Practices

### 1. Clear Tool Descriptions

```php
'description' => 'Create a new feature for a product with name and description'
// Better than: 'Creates feature'
```

### 2. Proper Error Handling

```php
try {
    $result = yield $this->executeMCPTool($toolName, $arguments);
    $messages[] = ['role' => 'tool', 'tool_call_id' => $toolCall->id, 'content' => $result];
} catch (\Exception $e) {
    $messages[] = ['role' => 'tool', 'tool_call_id' => $toolCall->id, 'content' => json_encode(['error' => $e->getMessage()])];
}
```

### 3. Structured Responses

Return JSON from MCP tools for consistent OpenAI processing:

```php
return json_encode([
    'success' => true,
    'data' => $mcpResult,
    'message' => 'Feature created successfully'
]);
```

## Common Use Cases

1. **Natural Language Product Management**: "Create a user authentication feature for my mobile app project"
2. **Intelligent Analysis**: "Analyze my top 3 products and suggest improvements"
3. **Workflow Automation**: "Set up a complete feature with requirements for user onboarding"
4. **Data Exploration**: "Show me products with the most high-priority requirements"

## Troubleshooting

### Tool Not Called

- Check tool descriptions are clear
- Verify parameters schema is correct
- Ensure user request matches tool capability

### MCP Connection Issues

```php
// Test MCP connection separately
yield $this->fullcx->connect();
$ping = yield $this->fullcx->ping();
echo "MCP connection: " . ($ping ? "OK" : "Failed") . "\n";
```

### OpenAI API Errors

```php
// Add error handling for OpenAI calls
try {
    $response = $this->openai->chat()->create([...]);
} catch (\OpenAI\Exceptions\ErrorException $e) {
    echo "OpenAI Error: " . $e->getMessage() . "\n";
}
```

This approach gives you the power of natural language interaction with structured MCP operations, making product management more intuitive and efficient!
