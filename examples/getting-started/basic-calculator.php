#!/usr/bin/env php
<?php

/**
 * Basic Calculator MCP Server.
 *
 * A simple calculator server that demonstrates:
 * - Multiple tools with different input schemas
 * - Error handling and validation
 * - Returning different types of responses
 *
 * This example shows how to build a practical MCP server with multiple
 * related tools that work together.
 *
 * Usage:
 *   php basic-calculator.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use function Amp\async;

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;

// Create calculator server
$server = new McpServer(
    new Implementation(
        'basic-calculator',
        '1.0.0',
        'A simple calculator server with basic math operations'
    )
);

// Add basic math operations
$server->tool(
    'add',
    'Add two numbers together',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number'],
        ],
        'required' => ['a', 'b'],
    ],
    function (array $args): array {
        $result = $args['a'] + $args['b'];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['a']} + {$args['b']} = {$result}",
                ],
            ],
        ];
    }
);

$server->tool(
    'subtract',
    'Subtract second number from first number',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number'],
        ],
        'required' => ['a', 'b'],
    ],
    function (array $args): array {
        $result = $args['a'] - $args['b'];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['a']} - {$args['b']} = {$result}",
                ],
            ],
        ];
    }
);

$server->tool(
    'multiply',
    'Multiply two numbers',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number'],
        ],
        'required' => ['a', 'b'],
    ],
    function (array $args): array {
        $result = $args['a'] * $args['b'];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['a']} × {$args['b']} = {$result}",
                ],
            ],
        ];
    }
);

$server->tool(
    'divide',
    'Divide first number by second number',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'Dividend (number to be divided)'],
            'b' => ['type' => 'number', 'description' => 'Divisor (number to divide by)'],
        ],
        'required' => ['a', 'b'],
    ],
    function (array $args): array {
        if ($args['b'] == 0) {
            throw new McpError(
                code: -32602,
                message: 'Division by zero is not allowed'
            );
        }

        $result = $args['a'] / $args['b'];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['a']} ÷ {$args['b']} = {$result}",
                ],
            ],
        ];
    }
);

$server->tool(
    'power',
    'Raise first number to the power of second number',
    [
        'type' => 'object',
        'properties' => [
            'base' => ['type' => 'number', 'description' => 'Base number'],
            'exponent' => ['type' => 'number', 'description' => 'Exponent'],
        ],
        'required' => ['base', 'exponent'],
    ],
    function (array $args): array {
        $result = pow($args['base'], $args['exponent']);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['base']}^{$args['exponent']} = {$result}",
                ],
            ],
        ];
    }
);

$server->tool(
    'sqrt',
    'Calculate square root of a number',
    [
        'type' => 'object',
        'properties' => [
            'number' => ['type' => 'number', 'description' => 'Number to find square root of'],
        ],
        'required' => ['number'],
    ],
    function (array $args): array {
        if ($args['number'] < 0) {
            throw new McpError(
                code: -32602,
                message: 'Cannot calculate square root of negative number'
            );
        }

        $result = sqrt($args['number']);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "√{$args['number']} = {$result}",
                ],
            ],
        ];
    }
);

// Add a resource that provides calculation history (simulated)
$server->resource(
    'Calculation History',
    'calculator://history',
    [
        'title' => 'Calculation History',
        'description' => 'History of recent calculations',
        'mimeType' => 'text/plain',
    ],
    function (): string {
        return "Calculator History:\n" .
            "- This is a demo calculator\n" .
            "- Available operations: add, subtract, multiply, divide, power, sqrt\n" .
            "- Use the tools to perform calculations\n" .
            "- All operations return formatted results\n";
    }
);

// Add a help prompt
$server->prompt(
    'calculator_help',
    'Get help using the calculator',
    function (): array {
        return [
            'description' => 'Calculator Help and Usage Guide',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I use this calculator?',
                        ],
                    ],
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "This calculator provides the following operations:\n\n" .
                                "• **add** - Add two numbers: add(a, b)\n" .
                                "• **subtract** - Subtract: subtract(a, b) = a - b\n" .
                                "• **multiply** - Multiply: multiply(a, b) = a × b\n" .
                                "• **divide** - Divide: divide(a, b) = a ÷ b\n" .
                                "• **power** - Exponentiation: power(base, exponent)\n" .
                                "• **sqrt** - Square root: sqrt(number)\n\n" .
                                "All operations include error handling for invalid inputs.\n" .
                                "Try: 'Use the add tool to calculate 5 + 3'",
                        ],
                    ],
                ],
            ],
        ];
    }
);

// Start the server
async(function () use ($server) {
    echo "🧮 Basic Calculator MCP Server starting...\n";
    echo "Available operations: add, subtract, multiply, divide, power, sqrt\n" . PHP_EOL;

    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
