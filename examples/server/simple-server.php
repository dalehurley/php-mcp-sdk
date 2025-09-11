<?php

/**
 * Simple MCP Server Example
 * 
 * This example demonstrates how to create a basic MCP server with:
 * - A tool that performs calculations
 * - A static resource that provides information
 * - A prompt template for generating responses
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\ServerOptions;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Server\ResourceTemplate;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\Results\ListResourcesResult;
use MCP\Types\Content\TextContent;
use MCP\Types\Prompts\PromptMessage;
use MCP\Types\Resources\TextResourceContents;
use function Amp\async;

// Create the server
$server = new McpServer(
    new Implementation('example-server', '1.0.0', 'Simple Example Server'),
    new ServerOptions(
        capabilities: new ServerCapabilities(
            tools: ['listChanged' => true],
            resources: ['listChanged' => true],
            prompts: ['listChanged' => true]
        ),
        instructions: "This is a simple example MCP server demonstrating basic functionality."
    )
);

// Register a calculation tool
$server->tool(
    'calculate',
    'Perform basic mathematical calculations',
    [
        'expression' => [
            'type' => 'string',
            'description' => 'A mathematical expression to evaluate (e.g., "2 + 2")',
            'pattern' => '^[0-9+\\-*/(). ]+$'
        ]
    ],
    function (array $args) {
        $expression = $args['expression'] ?? '';

        // Basic validation
        if (!preg_match('/^[0-9+\-*\/(). ]+$/', $expression)) {
            return new CallToolResult(
                content: [
                    ['type' => 'text', 'text' => 'Invalid expression. Only numbers and basic operators are allowed.']
                ],
                isError: true
            );
        }

        try {
            // Note: In production, use a proper expression parser instead of eval
            $result = eval("return $expression;");

            return new CallToolResult(
                content: [
                    ['type' => 'text', 'text' => "Result: $result"]
                ]
            );
        } catch (\Throwable $e) {
            return new CallToolResult(
                content: [
                    ['type' => 'text', 'text' => 'Error evaluating expression: ' . $e->getMessage()]
                ],
                isError: true
            );
        }
    }
);

// Register a static resource
$server->resource(
    'server-info',
    'file:///info.txt',
    [
        'title' => 'Server Information',
        'description' => 'Basic information about this MCP server',
        'mimeType' => 'text/plain'
    ],
    function ($uri, $extra) {
        $info = <<<EOF
Example MCP Server
==================

This is a simple demonstration of the PHP MCP SDK capabilities.

Available Tools:
- calculate: Perform basic math calculations

Available Resources:
- server-info: This information file
- dynamic-data: Dynamic resource with parameters

Available Prompts:
- greeting: Generate a personalized greeting
EOF;

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: 'file:///info.txt',
                    text: $info,
                    mimeType: 'text/plain'
                )
            ]
        );
    }
);

// Register a dynamic resource template
$dynamicTemplate = new ResourceTemplate(
    'data/{type}/{id}',
    [
        'list' => function ($extra) {
            // List some example resources
            return new ListResourcesResult([
                [
                    'uri' => 'data/user/123',
                    'name' => 'user-123',
                    'title' => 'User #123',
                    'description' => 'Information about user 123'
                ],
                [
                    'uri' => 'data/product/456',
                    'name' => 'product-456',
                    'title' => 'Product #456',
                    'description' => 'Details about product 456'
                ]
            ]);
        }
    ]
);

$server->resource(
    'dynamic-data',
    $dynamicTemplate,
    [
        'title' => 'Dynamic Data Resources',
        'description' => 'Access various types of data by ID'
    ],
    function ($uri, $variables, $extra) {
        $type = $variables['type'] ?? 'unknown';
        $id = $variables['id'] ?? '0';

        $data = "Dynamic $type data for ID: $id\n";
        $data .= "Retrieved at: " . date('Y-m-d H:i:s') . "\n";

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: $uri,
                    text: $data,
                    mimeType: 'text/plain'
                )
            ]
        );
    }
);

// Register a prompt template
$server->prompt(
    'greeting',
    'Generate a personalized greeting message',
    [
        'name' => [
            'type' => 'string',
            'description' => 'The name of the person to greet',
            'required' => true
        ],
        'style' => [
            'type' => 'string',
            'description' => 'The style of greeting (formal, casual, enthusiastic)',
            'enum' => ['formal', 'casual', 'enthusiastic'],
            'default' => 'casual'
        ]
    ],
    function (array $args) {
        $name = $args['name'] ?? 'friend';
        $style = $args['style'] ?? 'casual';

        $greeting = match ($style) {
            'formal' => "Good day, $name. I hope this message finds you well.",
            'enthusiastic' => "Hey there, $name! So excited to connect with you! 🎉",
            default => "Hi $name! Nice to meet you."
        };

        return new GetPromptResult(
            messages: [
                new PromptMessage(
                    role: 'assistant',
                    content: new TextContent($greeting)
                )
            ]
        );
    }
);

// Set up the transport and start the server
async(function () use ($server) {
    try {
        $transport = new StdioServerTransport();

        echo "Starting MCP server on stdio...\n";

        await($server->connect($transport));

        // Server will now handle requests until terminated

    } catch (\Throwable $e) {
        error_log("Server error: " . $e->getMessage());
        exit(1);
    }
})->await();
