#!/usr/bin/env php
<?php

/**
 * Simple OpenAI Tool Calling with MCP Demo
 * 
 * This is a simplified working example that demonstrates OpenAI function calling
 * with mock MCP tools. It shows the concept without requiring actual MCP server connection.
 * 
 * Usage:
 *   php examples/client/openai-mcp-simple.php [request]
 * 
 * Examples:
 *   php examples/client/openai-mcp-simple.php
 *   php examples/client/openai-mcp-simple.php "show me all products"
 *   php examples/client/openai-mcp-simple.php "create a feature called 'User Dashboard'"
 * 
 * Environment Variables:
 *   OPENAI_API_KEY - Your OpenAI API key (required)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenAI\Client as OpenAIClient;

class SimpleOpenAIMCPDemo
{
    private OpenAIClient $openai;

    public function __construct()
    {
        if (!$_ENV['OPENAI_API_KEY']) {
            throw new \Exception('OPENAI_API_KEY environment variable is required');
        }

        $this->openai = \OpenAI::client($_ENV['OPENAI_API_KEY']);
    }

    /**
     * Mock MCP tool definitions (in real implementation, these come from MCP server)
     */
    public function getMockMCPTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_products',
                    'description' => 'List all products in the system',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum number of products to return',
                                'minimum' => 1,
                                'maximum' => 50
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_feature',
                    'description' => 'Create a new feature for a product',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'string',
                                'description' => 'The ID of the product'
                            ],
                            'name' => [
                                'type' => 'string',
                                'description' => 'The name of the feature'
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Description of the feature'
                            ]
                        ],
                        'required' => ['product_id', 'name', 'description']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product_details',
                    'description' => 'Get detailed information about a specific product',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'string',
                                'description' => 'The ID of the product'
                            ]
                        ],
                        'required' => ['product_id']
                    ]
                ]
            ]
        ];
    }

    /**
     * Mock MCP tool execution (simulates actual MCP calls)
     */
    public function executeMockTool(string $toolName, array $arguments): array
    {
        echo "🔧 Executing MCP tool: {$toolName}\n";
        echo "   Arguments: " . json_encode($arguments, JSON_PRETTY_PRINT) . "\n";

        switch ($toolName) {
            case 'list_products':
                $result = [
                    'success' => true,
                    'data' => [
                        ['id' => 'prod-1', 'name' => 'Project Management Suite', 'features' => 12],
                        ['id' => 'prod-2', 'name' => 'Customer Support Platform', 'features' => 8],
                        ['id' => 'prod-3', 'name' => 'Analytics Dashboard', 'features' => 15]
                    ],
                    'message' => 'Successfully retrieved products'
                ];
                break;

            case 'create_feature':
                $result = [
                    'success' => true,
                    'data' => [
                        'id' => 'feat-' . uniqid(),
                        'product_id' => $arguments['product_id'],
                        'name' => $arguments['name'],
                        'description' => $arguments['description'],
                        'status' => 'Planning'
                    ],
                    'message' => "Successfully created feature '{$arguments['name']}'"
                ];
                break;

            case 'get_product_details':
                $result = [
                    'success' => true,
                    'data' => [
                        'id' => $arguments['product_id'],
                        'name' => 'Project Management Suite',
                        'description' => 'Comprehensive project management tool',
                        'feature_count' => 12,
                        'requirement_count' => 34
                    ],
                    'message' => 'Successfully retrieved product details'
                ];
                break;

            default:
                $result = [
                    'success' => false,
                    'error' => "Unknown tool: {$toolName}"
                ];
        }

        echo "   ✅ Tool executed: " . $result['message'] . "\n";
        return $result;
    }

    /**
     * Process user request with OpenAI function calling
     */
    public function processRequest(string $message): string
    {
        echo "🤖 Processing: \"{$message}\"\n\n";

        try {
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You help manage products using FullCX. Use tools when needed.'],
                    ['role' => 'user', 'content' => $message]
                ],
                'tools' => $this->getMockMCPTools(),
                'tool_choice' => 'auto'
            ]);

            $msg = $response->choices[0]->message;

            if ($msg->toolCalls) {
                echo "🔧 OpenAI is calling " . count($msg->toolCalls) . " tool(s):\n\n";

                $messages = [
                    ['role' => 'system', 'content' => 'You help manage products using FullCX.'],
                    ['role' => 'user', 'content' => $message],
                    ['role' => 'assistant', 'content' => $msg->content, 'tool_calls' => $msg->toolCalls]
                ];

                foreach ($msg->toolCalls as $call) {
                    $args = json_decode($call->function->arguments, true);
                    $result = $this->executeMockTool($call->function->name, $args);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call->id,
                        'content' => json_encode($result)
                    ];
                    echo "\n";
                }

                // Get final response
                $finalResponse = $this->openai->chat()->create([
                    'model' => 'gpt-4.1',
                    'messages' => $messages
                ]);

                echo "🎯 Final Response:\n";
                echo $finalResponse->choices[0]->message->content . "\n";

                return $finalResponse->choices[0]->message->content;
            } else {
                echo "💬 Direct Response:\n";
                echo $msg->content . "\n";
                return $msg->content;
            }
        } catch (\Exception $e) {
            echo "❌ Error: {$e->getMessage()}\n";
            return "Error: " . $e->getMessage();
        }
    }
}

// Command line usage
if ($argc > 1) {
    $agent = new SimpleOpenAIMCPDemo();
    $message = implode(' ', array_slice($argv, 1));
    $agent->processRequest($message);
} else {
    echo "🚀 Simple OpenAI + MCP Demo\n";
    echo "===========================\n\n";

    if (!$_ENV['OPENAI_API_KEY']) {
        echo "❌ OPENAI_API_KEY environment variable is required\n";
        exit(1);
    }

    $agent = new SimpleOpenAIMCPDemo();

    $demoRequests = [
        "show me all products",
        "get details for product prod-1",
        "create a feature called 'Advanced Search' for product prod-1"
    ];

    foreach ($demoRequests as $index => $request) {
        echo "Demo " . ($index + 1) . ": {$request}\n";
        echo str_repeat("-", 40) . "\n";
        $agent->processRequest($request);
        echo "\n";
    }

    echo "🎉 Demo completed!\n";
}
