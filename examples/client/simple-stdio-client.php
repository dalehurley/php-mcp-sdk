<?php

/**
 * Simple MCP Client Example
 * 
 * This example demonstrates how to create a basic MCP client that:
 * - Connects to a server via stdio
 * - Initializes the connection
 * - Lists available tools
 * - Calls a tool
 * - Lists resources
 * - Reads a resource
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load required files to ensure all classes are available
require_once __DIR__ . '/../../src/Shared/Protocol.php';
require_once __DIR__ . '/../../src/Client/ClientOptions.php';
require_once __DIR__ . '/../../src/Client/Client.php';
require_once __DIR__ . '/../../src/Client/Transport/StdioClientTransport.php';

use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Transport\StdioServerParameters;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ClientCapabilities;
use function Amp\async;

// Create the client
$client = new Client(
    new Implementation('example-client', '1.0.0', 'Simple Example Client'),
    new ClientOptions(
        capabilities: new ClientCapabilities()
    )
);

// Configure the server to connect to
$serverParams = new StdioServerParameters(
    command: 'php',
    args: [__DIR__ . '/../server/simple-server.php'],
    cwd: dirname(__DIR__, 2) // Project root
);

// Create the transport
$transport = new StdioClientTransport($serverParams);

// Run the client
async(function () use ($client, $transport) {
    try {
        echo "Connecting to server...\n";

        // Connect to the server
        $client->connect($transport)->await();

        echo "Connected! Server info: " . json_encode($client->getServerVersion()) . "\n\n";

        // List available tools
        echo "Listing tools...\n";
        $tools = $client->listTools()->await();
        foreach ($tools->getTools() as $tool) {
            echo "- {$tool->getName()}: {$tool->getDescription()}\n";
        }
        echo "\n";

        // Call the calculate tool using convenience method
        echo "Calling calculate tool with '2 + 2'...\n";
        $result = $client->callToolByName('calculate', ['expression' => '2 + 2'])->await();
        if ($result->isError()) {
            echo "Error: ";
        } else {
            echo "Result: ";
        }
        foreach ($result->getContent() as $content) {
            if ($content->getType() === 'text') {
                echo $content->getText() . "\n";
            }
        }
        echo "\n";

        // List resources
        echo "Listing resources...\n";
        $resources = $client->listResources()->await();
        foreach ($resources->getResources() as $resource) {
            echo "- {$resource->getName()}: {$resource->getUri()}\n";
            if ($resource->getDescription()) {
                echo "  Description: {$resource->getDescription()}\n";
            }
        }
        echo "\n";

        // Read a resource using convenience method
        echo "Reading server info resource...\n";
        $resourceContent = $client->readResourceByUri('file:///info.txt')->await();
        foreach ($resourceContent->getContents() as $content) {
            if ($content->getType() === 'text') {
                echo $content->getText() . "\n";
            }
        }
        echo "\n";

        // List prompts
        echo "Listing prompts...\n";
        $prompts = $client->listPrompts()->await();
        foreach ($prompts->getPrompts() as $prompt) {
            echo "- {$prompt->getName()}: {$prompt->getDescription()}\n";
            if ($prompt->hasArguments()) {
                echo "  Arguments:\n";
                foreach ($prompt->getArguments() as $arg) {
                    echo "    - {$arg->getName()}" . ($arg->isRequired() ? ' (required)' : '') . "\n";
                }
            }
        }
        echo "\n";

        // Get a prompt using convenience method
        echo "Getting greeting prompt with name='World' and style='enthusiastic'...\n";
        $promptResult = $client->getPromptByName(
            'greeting',
            ['name' => 'World', 'style' => 'enthusiastic']
        )->await();
        foreach ($promptResult->getMessages() as $message) {
            echo "Role: {$message->getRole()}\n";
            echo "Content: {$message->getContent()->getText()}\n";
        }
        echo "\n";

        echo "Closing connection...\n";
        $client->close()->await();

        echo "Done!\n";
    } catch (\Throwable $e) {
        error_log("Client error: " . $e->getMessage());
        error_log($e->getTraceAsString());
        exit(1);
    }
})->await();
