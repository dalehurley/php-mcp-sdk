#!/usr/bin/env php
<?php

/**
 * MCP Inspector Utility.
 *
 * This utility provides comprehensive inspection capabilities for MCP servers:
 * - Connect to any MCP server and analyze its capabilities
 * - List and test all available tools, resources, and prompts
 * - Interactive mode for exploring server functionality
 * - Generate detailed reports about server capabilities
 * - Validate server compliance with MCP specification
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use function Amp\async;

use Amp\Future;
use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Transport\StdioServerParameters;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Implementation;

// Command line argument parsing
$options = getopt('', [
    'server:',
    'command:',
    'args:',
    'interactive',
    'report',
    'test-all',
    'output:',
    'format:',
    'help',
]);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// Configuration
$config = [
    'server' => $options['server'] ?? null,
    'command' => $options['command'] ?? 'php',
    'args' => isset($options['args']) ? explode(',', $options['args']) : [],
    'interactive' => isset($options['interactive']),
    'report' => isset($options['report']),
    'test_all' => isset($options['test-all']),
    'output' => $options['output'] ?? null,
    'format' => $options['format'] ?? 'text',
];

async(function () use ($config) {
    try {
        echo "🔍 MCP Server Inspector\n";
        echo "======================\n\n";

        if (!$config['server']) {
            echo "❌ Error: Server path is required\n";
            echo "Use --server=/path/to/server.php or --help for usage information\n";
            exit(1);
        }

        // Create and connect to server
        $client = connectToServer($config)->await();

        if ($config['interactive']) {
            runInteractiveMode($client)->await();
        } elseif ($config['report']) {
            generateReport($client, $config)->await();
        } elseif ($config['test_all']) {
            runAllTests($client)->await();
        } else {
            runBasicInspection($client)->await();
        }

        echo "\n🔌 Closing connection...\n";
        $client->close()->await();
        echo "✅ Inspection completed!\n";
    } catch (\Throwable $e) {
        echo '❌ Error: ' . $e->getMessage() . "\n";
        if ($config['format'] === 'json') {
            echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT) . "\n";
        }
        exit(1);
    }
})->await();

/**
 * Connect to the MCP server.
 */
function connectToServer(array $config): Future
{
    return \Amp\async(function () use ($config) {
        echo "🔌 Connecting to MCP server...\n";
        echo "   Server: {$config['server']}\n";
        echo "   Command: {$config['command']}\n";

        if (!empty($config['args'])) {
            echo '   Args: ' . implode(', ', $config['args']) . "\n";
        }
        echo "\n";

        $client = new Client(
            new Implementation('mcp-inspector', '1.0.0', 'MCP Server Inspector'),
            new ClientOptions(capabilities: new ClientCapabilities())
        );

        $serverParams = new StdioServerParameters(
            command: $config['command'],
            args: array_merge($config['args'], [$config['server']]),
            cwd: dirname(__DIR__, 2)
        );

        $transport = new StdioClientTransport($serverParams);
        $client->connect($transport)->await();

        $serverInfo = $client->getServerVersion();
        echo "✅ Connected to: {$serverInfo['name']} v{$serverInfo['version']}\n\n";

        return $client;
    });
}

/**
 * Run basic inspection of the server.
 */
function runBasicInspection(Client $client): Future
{
    return \Amp\async(function () use ($client) {
        echo "📋 Basic Server Inspection\n";
        echo "=========================\n\n";

        // Get server capabilities
        $serverInfo = $client->getServerVersion();
        echo "🔧 Server Information:\n";
        echo "   Name: {$serverInfo['name']}\n";
        echo "   Version: {$serverInfo['version']}\n";
        if (isset($serverInfo['description'])) {
            echo "   Description: {$serverInfo['description']}\n";
        }
        echo "\n";

        // List tools
        echo "🛠️  Available Tools:\n";

        try {
            $tools = $client->listTools()->await();
            if (count($tools->getTools()) === 0) {
                echo "   (No tools available)\n";
            } else {
                foreach ($tools->getTools() as $tool) {
                    echo "   - {$tool->getName()}: {$tool->getDescription()}\n";

                    $inputSchema = $tool->getInputSchema();
                    if ($inputSchema && isset($inputSchema['properties'])) {
                        echo '     Parameters: ' . implode(', ', array_keys($inputSchema['properties'])) . "\n";
                    }
                }
            }
        } catch (\Exception $e) {
            echo '   ❌ Error listing tools: ' . $e->getMessage() . "\n";
        }
        echo "\n";

        // List resources
        echo "📁 Available Resources:\n";

        try {
            $resources = $client->listResources()->await();
            if (count($resources->getResources()) === 0) {
                echo "   (No resources available)\n";
            } else {
                foreach ($resources->getResources() as $resource) {
                    echo "   - {$resource->getName()}: {$resource->getUri()}\n";
                    if ($resource->getDescription()) {
                        echo "     Description: {$resource->getDescription()}\n";
                    }
                    if ($resource->getMimeType()) {
                        echo "     MIME Type: {$resource->getMimeType()}\n";
                    }
                }
            }
        } catch (\Exception $e) {
            echo '   ❌ Error listing resources: ' . $e->getMessage() . "\n";
        }
        echo "\n";

        // List prompts
        echo "💬 Available Prompts:\n";

        try {
            $prompts = $client->listPrompts()->await();
            if (count($prompts->getPrompts()) === 0) {
                echo "   (No prompts available)\n";
            } else {
                foreach ($prompts->getPrompts() as $prompt) {
                    echo "   - {$prompt->getName()}: {$prompt->getDescription()}\n";

                    if ($prompt->hasArguments()) {
                        $args = [];
                        foreach ($prompt->getArguments() as $arg) {
                            $argStr = $arg->getName();
                            if ($arg->isRequired()) {
                                $argStr .= ' (required)';
                            }
                            $args[] = $argStr;
                        }
                        echo '     Arguments: ' . implode(', ', $args) . "\n";
                    }
                }
            }
        } catch (\Exception $e) {
            echo '   ❌ Error listing prompts: ' . $e->getMessage() . "\n";
        }
    });
}

/**
 * Run interactive mode for exploring the server.
 */
function runInteractiveMode(Client $client): Future
{
    return \Amp\async(function () use ($client) {
        echo "🎮 Interactive Mode\n";
        echo "==================\n\n";
        echo "Commands:\n";
        echo "  tools           - List all tools\n";
        echo "  resources       - List all resources\n";
        echo "  prompts         - List all prompts\n";
        echo "  call <tool>     - Call a tool\n";
        echo "  read <uri>      - Read a resource\n";
        echo "  get <prompt>    - Get a prompt\n";
        echo "  test <tool>     - Test a tool with sample data\n";
        echo "  info            - Show server information\n";
        echo "  help            - Show this help\n";
        echo "  quit            - Exit interactive mode\n\n";

        while (true) {
            echo 'mcp> ';
            $input = trim(fgets(STDIN));

            if (empty($input)) {
                continue;
            }

            $parts = explode(' ', $input, 2);
            $command = $parts[0];
            $args = $parts[1] ?? '';

            try {
                switch ($command) {
                    case 'quit':
                    case 'exit':
                        return;

                    case 'help':
                        echo "Available commands:\n";
                        echo "  tools, resources, prompts, call <tool>, read <uri>, get <prompt>\n";
                        echo "  test <tool>, info, help, quit\n";
                        break;

                    case 'info':
                        $serverInfo = $client->getServerVersion();
                        echo "Server: {$serverInfo['name']} v{$serverInfo['version']}\n";
                        break;

                    case 'tools':
                        listToolsInteractive($client)->await();
                        break;

                    case 'resources':
                        listResourcesInteractive($client)->await();
                        break;

                    case 'prompts':
                        listPromptsInteractive($client)->await();
                        break;

                    case 'call':
                        if (empty($args)) {
                            echo "Usage: call <tool_name>\n";
                            break;
                        }
                        callToolInteractive($client, $args)->await();
                        break;

                    case 'read':
                        if (empty($args)) {
                            echo "Usage: read <resource_uri>\n";
                            break;
                        }
                        readResourceInteractive($client, $args)->await();
                        break;

                    case 'get':
                        if (empty($args)) {
                            echo "Usage: get <prompt_name>\n";
                            break;
                        }
                        getPromptInteractive($client, $args)->await();
                        break;

                    case 'test':
                        if (empty($args)) {
                            echo "Usage: test <tool_name>\n";
                            break;
                        }
                        testToolInteractive($client, $args)->await();
                        break;

                    default:
                        echo "Unknown command: $command\n";
                        echo "Type 'help' for available commands\n";
                }
            } catch (\Exception $e) {
                echo '❌ Error: ' . $e->getMessage() . "\n";
            }

            echo "\n";
        }
    });
}

/**
 * Generate comprehensive report.
 */
function generateReport(Client $client, array $config): Future
{
    return \Amp\async(function () use ($client, $config) {
        echo "📊 Generating Comprehensive Report\n";
        echo "=================================\n\n";

        $report = [
            'timestamp' => date('c'),
            'inspector_version' => '1.0.0',
            'server' => null,
            'capabilities' => [],
            'tools' => [],
            'resources' => [],
            'prompts' => [],
            'tests' => [],
        ];

        // Server information
        $serverInfo = $client->getServerVersion();
        $report['server'] = $serverInfo;

        echo "📋 Analyzing server capabilities...\n";

        // Analyze tools
        try {
            $tools = $client->listTools()->await();
            foreach ($tools->getTools() as $tool) {
                $toolInfo = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'input_schema' => $tool->getInputSchema(),
                    'test_result' => null,
                ];

                // Test the tool if possible
                echo "   Testing tool: {$tool->getName()}...\n";

                try {
                    $testResult = testTool($client, $tool)->await();
                    $toolInfo['test_result'] = $testResult;
                } catch (\Exception $e) {
                    $toolInfo['test_result'] = ['error' => $e->getMessage()];
                }

                $report['tools'][] = $toolInfo;
            }
        } catch (\Exception $e) {
            $report['tools'] = ['error' => $e->getMessage()];
        }

        // Analyze resources
        echo "   Analyzing resources...\n";

        try {
            $resources = $client->listResources()->await();
            foreach ($resources->getResources() as $resource) {
                $resourceInfo = [
                    'name' => $resource->getName(),
                    'uri' => $resource->getUri(),
                    'description' => $resource->getDescription(),
                    'mime_type' => $resource->getMimeType(),
                    'accessible' => false,
                    'content_sample' => null,
                ];

                // Try to read a sample of the resource
                try {
                    $content = $client->readResourceByUri($resource->getUri())->await();
                    $resourceInfo['accessible'] = true;

                    // Get first few lines as sample
                    $contents = $content->getContents();
                    if (!empty($contents)) {
                        $text = $contents[0]->getText();
                        $lines = explode("\n", $text);
                        $resourceInfo['content_sample'] = implode("\n", array_slice($lines, 0, 3));
                        if (count($lines) > 3) {
                            $resourceInfo['content_sample'] .= "\n... (truncated)";
                        }
                    }
                } catch (\Exception $e) {
                    $resourceInfo['error'] = $e->getMessage();
                }

                $report['resources'][] = $resourceInfo;
            }
        } catch (\Exception $e) {
            $report['resources'] = ['error' => $e->getMessage()];
        }

        // Analyze prompts
        echo "   Analyzing prompts...\n";

        try {
            $prompts = $client->listPrompts()->await();
            foreach ($prompts->getPrompts() as $prompt) {
                $promptInfo = [
                    'name' => $prompt->getName(),
                    'description' => $prompt->getDescription(),
                    'arguments' => [],
                ];

                if ($prompt->hasArguments()) {
                    foreach ($prompt->getArguments() as $arg) {
                        $promptInfo['arguments'][] = [
                            'name' => $arg->getName(),
                            'description' => $arg->getDescription(),
                            'required' => $arg->isRequired(),
                        ];
                    }
                }

                $report['prompts'][] = $promptInfo;
            }
        } catch (\Exception $e) {
            $report['prompts'] = ['error' => $e->getMessage()];
        }

        // Output report
        if ($config['format'] === 'json') {
            $output = json_encode($report, JSON_PRETTY_PRINT);
        } else {
            $output = formatReportAsText($report);
        }

        if ($config['output']) {
            file_put_contents($config['output'], $output);
            echo "✅ Report saved to: {$config['output']}\n";
        } else {
            echo $output;
        }
    });
}

/**
 * Run all available tests.
 */
function runAllTests(Client $client): Future
{
    return \Amp\async(function () use ($client) {
        echo "🧪 Running All Tests\n";
        echo "===================\n\n";

        $testResults = [
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // Test all tools
        echo "Testing tools...\n";

        try {
            $tools = $client->listTools()->await();
            foreach ($tools->getTools() as $tool) {
                echo "  Testing {$tool->getName()}... ";

                try {
                    $result = testTool($client, $tool)->await();
                    if ($result['success']) {
                        echo "✅ PASS\n";
                        $testResults['passed']++;
                    } else {
                        echo "❌ FAIL: {$result['error']}\n";
                        $testResults['failed']++;
                    }
                } catch (\Exception $e) {
                    echo '⏭️  SKIP: ' . $e->getMessage() . "\n";
                    $testResults['skipped']++;
                }
            }
        } catch (\Exception $e) {
            echo '❌ Error testing tools: ' . $e->getMessage() . "\n";
        }

        // Test all resources
        echo "\nTesting resources...\n";

        try {
            $resources = $client->listResources()->await();
            foreach ($resources->getResources() as $resource) {
                echo "  Reading {$resource->getName()}... ";

                try {
                    $content = $client->readResourceByUri($resource->getUri())->await();
                    if (!empty($content->getContents())) {
                        echo "✅ PASS\n";
                        $testResults['passed']++;
                    } else {
                        echo "❌ FAIL: Empty content\n";
                        $testResults['failed']++;
                    }
                } catch (\Exception $e) {
                    echo '❌ FAIL: ' . $e->getMessage() . "\n";
                    $testResults['failed']++;
                }
            }
        } catch (\Exception $e) {
            echo '❌ Error testing resources: ' . $e->getMessage() . "\n";
        }

        // Summary
        echo "\n📊 Test Summary:\n";
        echo "   Passed: {$testResults['passed']}\n";
        echo "   Failed: {$testResults['failed']}\n";
        echo "   Skipped: {$testResults['skipped']}\n";
        echo '   Total: ' . array_sum($testResults) . "\n";
    });
}

/**
 * Interactive functions.
 */
function listToolsInteractive(Client $client): Future
{
    return \Amp\async(function () use ($client) {
        $tools = $client->listTools()->await();
        echo 'Available tools (' . count($tools->getTools()) . "):\n";
        foreach ($tools->getTools() as $i => $tool) {
            echo '  ' . ($i + 1) . ". {$tool->getName()} - {$tool->getDescription()}\n";
        }
    });
}

function listResourcesInteractive(Client $client): Future
{
    return \Amp\async(function () use ($client) {
        $resources = $client->listResources()->await();
        echo 'Available resources (' . count($resources->getResources()) . "):\n";
        foreach ($resources->getResources() as $i => $resource) {
            echo '  ' . ($i + 1) . ". {$resource->getName()} ({$resource->getUri()})\n";
            if ($resource->getDescription()) {
                echo "     {$resource->getDescription()}\n";
            }
        }
    });
}

function listPromptsInteractive(Client $client): Future
{
    return \Amp\async(function () use ($client) {
        $prompts = $client->listPrompts()->await();
        echo 'Available prompts (' . count($prompts->getPrompts()) . "):\n";
        foreach ($prompts->getPrompts() as $i => $prompt) {
            echo '  ' . ($i + 1) . ". {$prompt->getName()} - {$prompt->getDescription()}\n";
        }
    });
}

function callToolInteractive(Client $client, string $toolName): Future
{
    return \Amp\async(function () use ($client, $toolName) {
        echo 'Enter parameters (JSON format, or press Enter for empty): ';
        $paramsInput = trim(fgets(STDIN));

        $params = [];
        if (!empty($paramsInput)) {
            $params = json_decode($paramsInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "❌ Invalid JSON format\n";

                return;
            }
        }

        $result = $client->callToolByName($toolName, $params)->await();

        if ($result->isError()) {
            echo "❌ Tool call failed:\n";
        } else {
            echo "✅ Tool call succeeded:\n";
        }

        foreach ($result->getContent() as $content) {
            if ($content->getType() === 'text') {
                echo $content->getText() . "\n";
            }
        }
    });
}

function readResourceInteractive(Client $client, string $uri): Future
{
    return \Amp\async(function () use ($client, $uri) {
        $content = $client->readResourceByUri($uri)->await();

        echo "Resource content:\n";
        echo str_repeat('-', 40) . "\n";

        foreach ($content->getContents() as $item) {
            if ($item->getType() === 'text') {
                echo $item->getText() . "\n";
            }
        }

        echo str_repeat('-', 40) . "\n";
    });
}

function getPromptInteractive(Client $client, string $promptName): Future
{
    return \Amp\async(function () use ($client, $promptName) {
        echo 'Enter arguments (JSON format, or press Enter for empty): ';
        $argsInput = trim(fgets(STDIN));

        $args = [];
        if (!empty($argsInput)) {
            $args = json_decode($argsInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "❌ Invalid JSON format\n";

                return;
            }
        }

        $result = $client->getPromptByName($promptName, $args)->await();

        echo "Prompt result:\n";
        echo str_repeat('-', 40) . "\n";

        foreach ($result->getMessages() as $message) {
            echo "Role: {$message->getRole()}\n";
            echo "Content: {$message->getContent()->getText()}\n\n";
        }

        echo str_repeat('-', 40) . "\n";
    });
}

function testToolInteractive(Client $client, string $toolName): Future
{
    return \Amp\async(function () use ($client, $toolName) {
        // Get tool schema first
        $tools = $client->listTools()->await();
        $tool = null;

        foreach ($tools->getTools() as $t) {
            if ($t->getName() === $toolName) {
                $tool = $t;
                break;
            }
        }

        if (!$tool) {
            echo "❌ Tool '$toolName' not found\n";

            return;
        }

        $testResult = testTool($client, $tool)->await();

        if ($testResult['success']) {
            echo "✅ Test passed\n";
            if (isset($testResult['result'])) {
                echo 'Result: ' . json_encode($testResult['result'], JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "❌ Test failed: {$testResult['error']}\n";
        }
    });
}

/**
 * Test a tool with sample data.
 */
function testTool(Client $client, $tool): Future
{
    return \Amp\async(function () use ($client, $tool) {
        $testParams = generateTestParameters($tool->getInputSchema());

        try {
            $result = $client->callToolByName($tool->getName(), $testParams)->await();

            return [
                'success' => !$result->isError(),
                'parameters' => $testParams,
                'result' => $result->isError() ? null : extractResultText($result),
                'error' => $result->isError() ? extractResultText($result) : null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'parameters' => $testParams,
                'error' => $e->getMessage(),
            ];
        }
    });
}

/**
 * Generate test parameters based on schema.
 */
function generateTestParameters(?array $schema): array
{
    if (!$schema || !isset($schema['properties'])) {
        return [];
    }

    $params = [];
    foreach ($schema['properties'] as $name => $property) {
        $type = $property['type'] ?? 'string';

        switch ($type) {
            case 'string':
                if (isset($property['enum'])) {
                    $params[$name] = $property['enum'][0];
                } else {
                    $params[$name] = 'test_value';
                }
                break;
            case 'integer':
                $params[$name] = 42;
                break;
            case 'number':
                $params[$name] = 3.14;
                break;
            case 'boolean':
                $params[$name] = true;
                break;
            case 'array':
                $params[$name] = ['test', 'array'];
                break;
            case 'object':
                $params[$name] = ['key' => 'value'];
                break;
        }
    }

    return $params;
}

/**
 * Extract text from result.
 */
function extractResultText($result): string
{
    $texts = [];
    foreach ($result->getContent() as $content) {
        if ($content->getType() === 'text') {
            $texts[] = $content->getText();
        }
    }

    return implode(' ', $texts);
}

/**
 * Format report as text.
 */
function formatReportAsText(array $report): string
{
    $output = "MCP Server Inspection Report\n";
    $output .= "===========================\n\n";
    $output .= "Generated: {$report['timestamp']}\n";
    $output .= "Inspector Version: {$report['inspector_version']}\n\n";

    if ($report['server']) {
        $output .= "Server Information:\n";
        $output .= "  Name: {$report['server']['name']}\n";
        $output .= "  Version: {$report['server']['version']}\n";
        if (isset($report['server']['description'])) {
            $output .= "  Description: {$report['server']['description']}\n";
        }
        $output .= "\n";
    }

    if (is_array($report['tools'])) {
        $output .= 'Tools (' . count($report['tools']) . "):\n";
        foreach ($report['tools'] as $tool) {
            $output .= "  - {$tool['name']}: {$tool['description']}\n";
            if (isset($tool['test_result']['success'])) {
                $status = $tool['test_result']['success'] ? '✅ PASS' : '❌ FAIL';
                $output .= "    Test: $status\n";
            }
        }
        $output .= "\n";
    }

    if (is_array($report['resources'])) {
        $output .= 'Resources (' . count($report['resources']) . "):\n";
        foreach ($report['resources'] as $resource) {
            $output .= "  - {$resource['name']} ({$resource['uri']})\n";
            $status = $resource['accessible'] ? '✅ Accessible' : '❌ Not accessible';
            $output .= "    Status: $status\n";
        }
        $output .= "\n";
    }

    if (is_array($report['prompts'])) {
        $output .= 'Prompts (' . count($report['prompts']) . "):\n";
        foreach ($report['prompts'] as $prompt) {
            $output .= "  - {$prompt['name']}: {$prompt['description']}\n";
            if (!empty($prompt['arguments'])) {
                $args = array_map(fn ($arg) => $arg['name'], $prompt['arguments']);
                $output .= '    Arguments: ' . implode(', ', $args) . "\n";
            }
        }
    }

    return $output;
}

/**
 * Show help information.
 */
function showHelp(): void
{
    echo "MCP Server Inspector\n";
    echo "===================\n\n";
    echo "Usage: php inspector.php --server=/path/to/server.php [options]\n\n";
    echo "Options:\n";
    echo "  --server=PATH        Path to the MCP server script (required)\n";
    echo "  --command=CMD        Command to run server (default: php)\n";
    echo "  --args=ARG1,ARG2     Additional arguments for server command\n";
    echo "  --interactive        Run in interactive mode\n";
    echo "  --report             Generate comprehensive report\n";
    echo "  --test-all           Run all available tests\n";
    echo "  --output=FILE        Save output to file\n";
    echo "  --format=FORMAT      Output format (text|json, default: text)\n";
    echo "  --help               Show this help message\n\n";
    echo "Examples:\n";
    echo "  php inspector.php --server=../server/simple-server.php\n";
    echo "  php inspector.php --server=../server/weather-server.php --interactive\n";
    echo "  php inspector.php --server=../server/sqlite-server.php --report --output=report.json --format=json\n";
    echo "  php inspector.php --server=../server/oauth-server.php --test-all\n\n";
}
