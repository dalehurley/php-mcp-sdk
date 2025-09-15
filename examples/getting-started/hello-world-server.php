#!/usr/bin/env php
<?php

/**
 * Hello World MCP Server.
 *
 * The simplest possible MCP server - demonstrates basic server setup
 * and provides a single "say_hello" tool.
 *
 * This is the absolute minimum code needed to create a working MCP server.
 * Perfect for understanding the core concepts before building more complex servers.
 *
 * Usage:
 *   php hello-world-server.php
 *
 * Test with Claude Desktop or any MCP client by adding to your configuration.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use function Amp\async;

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;

// Create the simplest possible MCP server
$server = new McpServer(
    new Implementation(
        'hello-world-server',
        '1.0.0'
    )
);

// Add a simple "say_hello" tool
$server->tool(
    'say_hello',
    'Says hello to someone',
    [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Name of the person to greet',
            ],
        ],
        'required' => ['name'],
    ],
    function (array $args): array {
        $name = $args['name'] ?? 'World';

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Hello, {$name}! 👋 Welcome to MCP!",
                ],
            ],
        ];
    }
);

// Start the server
async(function () use ($server) {
    echo "🚀 Hello World MCP Server starting...\n" . PHP_EOL;

    // Connect to STDIO transport and run
    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
