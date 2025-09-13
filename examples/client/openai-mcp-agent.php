#!/usr/bin/env php
<?php

/**
 * OpenAI Tool Calling with FullCX MCP Integration
 * 
 * This example demonstrates how OpenAI can use function calling to interact 
 * with FullCX MCP server for product management tasks.
 * 
 * Usage:
 *   php examples/client/openai-mcp-agent.php "your request here"
 * 
 * Examples:
 *   php examples/client/openai-mcp-agent.php "show me all products"
 *   php examples/client/openai-mcp-agent.php "create a feature called 'User Dashboard' for product-123"
 *   php examples/client/openai-mcp-agent.php "analyze product product-123 and create 2 requirements"
 * 
 * Environment Variables:
 *   FULLCX_API_TOKEN   - Your FullCX API token (required)
 *   FULLCX_MCP_URL     - FullCX MCP server URL (default: https://full.cx/mcp)
 *   OPENAI_API_KEY     - OpenAI API key (required)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use OpenAI\Client as OpenAIClient;
use function Amp\async;
use function Amp\delay;

class OpenAIMCPAgent
{
    private FullCXClient $fullcx;
    private OpenAIClient $openai;

    private array $config;

    public function __construct()
    {
        $this->config = [
            'fullcx_token' => $_ENV['FULLCX_API_TOKEN'] ?? null,
            'openai_key' => $_ENV['OPENAI_API_KEY'] ?? null,
            'openai_org' => $_ENV['OPENAI_ORGANIZATION'] ?? null,
            'openai_timeout' => (int)($_ENV['OPENAI_REQUEST_TIMEOUT'] ?? 300),
        ];

        if (!$this->config['fullcx_token']) {
            throw new \Exception('FULLCX_API_TOKEN environment variable is required');
        }

        if (!$this->config['openai_key']) {
            throw new \Exception('OPENAI_API_KEY environment variable is required');
        }

        // Initialize FullCX MCP client
        $this->fullcx = new FullCXClient(
            url: 'https://full.cx/mcp',
            bearerToken: $this->config['fullcx_token']
        );

        // Initialize OpenAI client
        $factory = \OpenAI::factory()
            ->withApiKey($this->config['openai_key'])
            ->withHttpHeader('User-Agent', 'FullCX-OpenAI-Agent/1.0');

        if ($this->config['openai_org']) {
            $factory = $factory->withOrganization($this->config['openai_org']);
        }

        // Add timeout configuration
        $factory = $factory->withHttpClient(
            new \GuzzleHttp\Client([
                'timeout' => $this->config['openai_timeout']
            ])
        );

        $this->openai = $factory->make();
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
    public function getToolDefinitions(): \Generator
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
    public function executeTool(string $toolName, array $arguments): \Generator
    {
        echo "🔧 Executing MCP tool: {$toolName}\n";
        echo "   Arguments: " . json_encode($arguments, JSON_PRETTY_PRINT) . "\n";

        try {
            // Call the MCP tool directly using the client's callToolByName method
            $result = yield $this->fullcx->callToolByName($toolName, $arguments);

            // Parse the result - MCP tools typically return content in text format
            $content = $result['content'] ?? [];
            $textContent = '';
            $data = null;

            if (!empty($content)) {
                $textContent = $content[0]['text'] ?? '';

                // Try to parse as JSON if it looks like JSON
                if ($textContent && (str_starts_with($textContent, '{') || str_starts_with($textContent, '['))) {
                    $data = json_decode($textContent, true);
                }
            }

            echo "   ✅ Tool executed successfully\n";

            return [
                'success' => true,
                'tool_name' => $toolName,
                'raw_result' => $result,
                'text_content' => $textContent,
                'parsed_data' => $data,
                'message' => "Successfully executed tool '{$toolName}'"
            ];
        } catch (\Exception $e) {
            echo "   ❌ Tool execution failed: {$e->getMessage()}\n";
            return [
                'success' => false,
                'tool_name' => $toolName,
                'error' => $e->getMessage(),
                'message' => "Failed to execute tool '{$toolName}': {$e->getMessage()}"
            ];
        }
    }

    /**
     * Process user request using OpenAI function calling
     */
    public function processRequest(string $userMessage): \Generator
    {
        echo "🤖 Processing request: \"{$userMessage}\"\n\n";

        yield $this->fullcx->connect();

        try {
            // Get available tools from MCP server
            $toolDefinitions = yield $this->getToolDefinitions();

            // Initial OpenAI call with available tools
            echo "💭 OpenAI is analyzing the request...\n";
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful product management assistant that can interact with FullCX to manage products, features, requirements, and ideas. Use the available tools to fulfill user requests. Always be helpful and provide clear explanations of what you\'re doing.'
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

            // Build conversation history
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful product management assistant.'],
                ['role' => 'user', 'content' => $userMessage],
                [
                    'role' => 'assistant',
                    'content' => $message->content,
                    'tool_calls' => $message->toolCalls
                ]
            ];

            // Process tool calls if any
            if ($message->toolCalls) {
                echo "🔧 OpenAI wants to call " . count($message->toolCalls) . " tool(s):\n\n";

                foreach ($message->toolCalls as $toolCall) {
                    $toolName = $toolCall->function->name;
                    $arguments = json_decode($toolCall->function->arguments, true);

                    // Execute the MCP tool
                    $result = yield $this->executeTool($toolName, $arguments);

                    // Add tool result to conversation
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall->id,
                        'content' => json_encode($result)
                    ];

                    echo "\n";
                }

                // Get final response from OpenAI with tool results
                echo "💭 OpenAI is formulating the final response...\n";
                $finalResponse = $this->openai->chat()->create([
                    'model' => 'gpt-4.1',
                    'messages' => $messages
                ]);

                $finalMessage = $finalResponse->choices[0]->message->content;

                echo "\n🎯 Final Response:\n";
                echo str_repeat("-", 50) . "\n";
                echo $finalMessage . "\n";
                echo str_repeat("-", 50) . "\n";

                return $finalMessage;
            } else {
                // Direct response without tool calls
                echo "\n💬 Direct Response:\n";
                echo str_repeat("-", 50) . "\n";
                echo $message->content . "\n";
                echo str_repeat("-", 50) . "\n";

                return $message->content;
            }
        } catch (\Exception $e) {
            echo "❌ Error processing request: {$e->getMessage()}\n";
            return "I encountered an error: " . $e->getMessage();
        } finally {
            yield $this->fullcx->close();
        }
    }

    /**
     * Interactive demo mode
     */
    public function runDemo(): \Generator
    {
        echo "🎬 OpenAI + FullCX MCP Integration Demo\n";
        echo str_repeat("=", 50) . "\n\n";

        $demoRequests = [
            "Show me all the products in the system",
            "Get detailed information about the first product",
            "Create a new feature called 'Advanced Search' for the first product with description 'Implement advanced search functionality with filters and sorting'",
            "Create a high-priority requirement for the first product called 'Search Performance' with description 'Search results should load within 2 seconds'",
            "Generate an idea for improving user experience in the first product with high impact and medium effort"
        ];

        foreach ($demoRequests as $index => $request) {
            echo "Demo Step " . ($index + 1) . ":\n";
            echo str_repeat("=", 70) . "\n";

            yield $this->processRequest($request);

            echo "\n";

            // Pause between requests
            if ($index < count($demoRequests) - 1) {
                echo "⏸️  Pausing for 2 seconds...\n\n";
                yield \Amp\delay(2000);
            }
        }

        echo "🎉 Demo completed!\n";
    }
}

// Command line interface
if (basename($_SERVER['argv'][0]) === 'openai-mcp-agent.php') {
    try {
        echo "🚀 FullCX OpenAI MCP Agent\n";
        echo str_repeat("=", 30) . "\n\n";

        // Show configuration
        echo "Configuration:\n";
        echo "  FullCX Token: " . (($_ENV['FULLCX_API_TOKEN'] ?? '') ? 'Set ✅' : 'Not set ❌') . "\n";
        echo "  OpenAI Key: " . (($_ENV['OPENAI_API_KEY'] ?? '') ? 'Set ✅' : 'Not set ❌') . "\n";
        echo "  OpenAI Org: " . ($_ENV['OPENAI_ORGANIZATION'] ?? 'Not set') . "\n";
        echo "  Timeout: " . ($_ENV['OPENAI_REQUEST_TIMEOUT'] ?? '300') . " seconds\n\n";

        $agent = new OpenAIMCPAgent();

        if ($argc > 1) {
            // Process specific request
            $message = implode(' ', array_slice($argv, 1));

            async(function () use ($agent, $message) {
                yield $agent->processRequest($message);
            })->await();
        } else {
            // Run interactive demo
            async(function () use ($agent) {
                yield $agent->runDemo();
            })->await();
        }
    } catch (\Exception $e) {
        echo "❌ Failed to initialize: {$e->getMessage()}\n\n";

        echo "Required Environment Variables:\n";
        echo "  FULLCX_API_TOKEN   - Your FullCX API token\n";
        echo "  OPENAI_API_KEY     - Your OpenAI API key\n";
        echo "  OPENAI_ORGANIZATION - Your OpenAI organization ID (optional)\n";
        echo "  OPENAI_REQUEST_TIMEOUT - Request timeout in seconds (optional, default: 300)\n\n";

        echo "Setup Instructions:\n";
        echo "1. Install dependencies: composer install\n";
        echo "2. Set environment variables in your shell or .env file\n";
        echo "3. Run: php examples/client/openai-mcp-agent.php\n\n";

        echo "Usage Examples:\n";
        echo "  php examples/client/openai-mcp-agent.php                    # Run demo\n";
        echo "  php examples/client/openai-mcp-agent.php \"show me all products\"\n";
        echo "  php examples/client/openai-mcp-agent.php \"create a feature called 'User Profile'\"\n";
        echo "  php examples/client/openai-mcp-agent.php \"analyze the first product and suggest improvements\"\n";

        exit(1);
    }
}
