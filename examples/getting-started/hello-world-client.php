#!/usr/bin/env php
<?php

/**
 * Hello World MCP Client
 * 
 * The simplest possible MCP client - connects to a server and calls a tool.
 * This demonstrates basic client setup and tool calling.
 * 
 * This is the absolute minimum code needed to create a working MCP client.
 * Perfect for understanding client-server communication.
 * 
 * Usage:
 *   # First, start the hello-world-server in another terminal:
 *   php hello-world-server.php
 *   
 *   # Then run this client (modify the server command as needed):
 *   php hello-world-client.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use function Amp\async;
use function Amp\delay;

async(function () {
    echo "🚀 Hello World MCP Client starting...\n\n";
    
    try {
        // Create client that connects to hello-world-server
        $client = new Client(
            new Implementation(
                name: 'hello-world-client',
                version: '1.0.0'
            )
        );
        
        // Connect to the hello-world-server
        // Note: In real usage, you'd typically connect to a running server process
        $serverCommand = ['php', __DIR__ . '/hello-world-server.php'];
        $transport = new StdioClientTransport($serverCommand);
        
        echo "🔌 Connecting to hello-world-server...\n";
        await $client->connect($transport);
        
        echo "✅ Connected! Initializing...\n";
        await $client->initialize();
        
        // List available tools
        echo "🔍 Discovering server tools...\n";
        $tools = await $client->listTools();
        
        echo "📋 Available tools:\n";
        foreach ($tools['tools'] as $tool) {
            echo "   - {$tool['name']}: {$tool['description']}\n";
        }
        
        // Call the say_hello tool
        echo "\n🛠️  Calling say_hello tool...\n";
        $result = await $client->callTool('say_hello', ['name' => 'PHP Developer']);
        
        echo "📢 Server response:\n";
        foreach ($result['content'] as $content) {
            if ($content['type'] === 'text') {
                echo "   " . $content['text'] . "\n";
            }
        }
        
        // Try with different name
        echo "\n🛠️  Calling say_hello with different name...\n";
        $result2 = await $client->callTool('say_hello', ['name' => 'MCP Explorer']);
        
        echo "📢 Server response:\n";
        foreach ($result2['content'] as $content) {
            if ($content['type'] === 'text') {
                echo "   " . $content['text'] . "\n";
            }
        }
        
        echo "\n🎉 Success! You've successfully created your first MCP client-server pair!\n";
        echo "💡 Next steps:\n";
        echo "   - Try the personal-assistant-server.php for more features\n";
        echo "   - Add more tools to your hello-world-server\n";
        echo "   - Explore resources and prompts\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo "🔧 Make sure the hello-world-server.php is working correctly.\n";
        exit(1);
    }
});
