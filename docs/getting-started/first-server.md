# Build Your First MCP Server in 10 Minutes

Welcome to MCP! In this tutorial, you'll build a working MCP server from scratch in just 10 minutes. By the end, you'll have a server that provides useful tools and understand the core concepts.

## 🎯 What You'll Build

A **Personal Assistant Server** that provides:

- ✅ **Calculator tool** - Perform mathematical calculations
- ✅ **Note-taking tool** - Save and retrieve notes
- ✅ **System info resource** - Get system information
- ✅ **Help prompt** - Guide users on available features

## 📋 Prerequisites

- PHP 8.1+ installed
- Composer installed
- 10 minutes of your time
- Basic PHP knowledge (arrays, functions, classes)

## 🚀 Let's Build!

### Step 1: Create Project Structure (1 minute)

```bash
# Create a new directory for your server
mkdir my-first-mcp-server
cd my-first-mcp-server

# Initialize composer project
composer init --name="my-company/mcp-server" --no-interaction

# Install PHP MCP SDK
composer require dalehurley/php-mcp-sdk
```

### Step 2: Create the Server File (3 minutes)

Create `personal-assistant-server.php`:

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;
use MCP\Types\ErrorCode;
use function Amp\async;

// Create server with implementation info
$server = new McpServer(
    new Implementation(
        'personal-assistant',           // Server name
        '1.0.0',                       // Version
        'Personal Assistant MCP Server' // Description
    )
);

// In-memory storage for notes (in production, use a database)
$notes = [];

// 🧮 TOOL 1: Calculator
$server->registerTool(
    'calculate',
    [
        'description' => 'Perform mathematical calculations',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Mathematical expression to evaluate (e.g., "2 + 2", "10 * 5")'
                ]
            ],
            'required' => ['expression']
        ]
    ],
    function (array $params): array {
        $expression = $params['expression'];

        // Basic security: only allow safe mathematical operations
        if (!preg_match('/^[0-9+\-*\/\(\)\.\s]+$/', $expression)) {
            throw new McpError(
                ErrorCode::InvalidParams,
                'Expression contains invalid characters. Only numbers and basic operators (+, -, *, /, parentheses) are allowed.'
            );
        }

        try {
            // Evaluate the expression safely
            $result = eval("return {$expression};");

            return [
                'content' => [[
                    'type' => 'text',
                    'text' => "Calculation: {$expression} = {$result}"
                ]]
            ];
        } catch (\ParseError $e) {
            throw new McpError(
                ErrorCode::InvalidParams,
                "Invalid mathematical expression: {$expression}"
            );
        }
    }
);

// 📝 TOOL 2: Note Management
$server->registerTool(
    'save-note',
    [
        'description' => 'Save a note with a title and content',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Title of the note'
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Content of the note'
                ]
            ],
            'required' => ['title', 'content']
        ]
    ],
    function (array $params) use (&$notes): array {
        $title = $params['title'];
        $content = $params['content'];
        $timestamp = date('Y-m-d H:i:s');

        $notes[$title] = [
            'content' => $content,
            'created' => $timestamp,
            'updated' => $timestamp
        ];

        return [
            'content' => [[
                'type' => 'text',
                'text' => "Note '{$title}' saved successfully at {$timestamp}"
            ]]
        ];
    }
);

$server->registerTool(
    'get-note',
    [
        'description' => 'Retrieve a saved note by title',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Title of the note to retrieve'
                ]
            ],
            'required' => ['title']
        ]
    ],
    function (array $params) use (&$notes): array {
        $title = $params['title'];

        if (!isset($notes[$title])) {
            throw new McpError(
                ErrorCode::InvalidParams,
                "Note '{$title}' not found"
            );
        }

        $note = $notes[$title];

        return [
            'content' => [[
                'type' => 'text',
                'text' => "Note: {$title}\nCreated: {$note['created']}\nUpdated: {$note['updated']}\n\nContent:\n{$note['content']}"
            ]]
        ];
    }
);

$server->registerTool(
    'list-notes',
    [
        'description' => 'List all saved notes',
        'inputSchema' => [
            'type' => 'object',
            'properties' => []
        ]
    ],
    function (array $params) use (&$notes): array {
        if (empty($notes)) {
            return [
                'content' => [[
                    'type' => 'text',
                    'text' => 'No notes saved yet. Use save-note to create your first note!'
                ]]
            ];
        }

        $notesList = "Saved Notes:\n\n";
        foreach ($notes as $title => $note) {
            $notesList .= "📝 {$title}\n";
            $notesList .= "   Created: {$note['created']}\n";
            $notesList .= "   Preview: " . substr($note['content'], 0, 50) . "...\n\n";
        }

        return [
            'content' => [[
                'type' => 'text',
                'text' => $notesList
            ]]
        ];
    }
);

// 💻 RESOURCE: System Information
$server->registerResource(
    'system-info',
    'system://info',
    [
        'title' => 'System Information',
        'description' => 'Current system information and server status',
        'mimeType' => 'application/json'
    ],
    function (string $uri): array {
        $systemInfo = [
            'server_name' => 'Personal Assistant MCP Server',
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'uptime' => time() - $_SERVER['REQUEST_TIME'],
            'timestamp' => date('c'),
            'tools_count' => 4, // We have 4 tools
            'resources_count' => 1 // We have 1 resource
        ];

        return [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => 'application/json',
                'text' => json_encode($systemInfo, JSON_PRETTY_PRINT)
            ]]
        ];
    }
);

// 💡 PROMPT: Help and Usage Guide
$server->registerPrompt(
    'help',
    [
        'name' => 'Personal Assistant Help',
        'description' => 'Get help on how to use the personal assistant',
        'arguments' => [
            [
                'name' => 'topic',
                'description' => 'Specific topic to get help with (optional)',
                'required' => false
            ]
        ]
    ],
    function (array $arguments): array {
        $topic = $arguments['topic'] ?? 'general';

        $helpContent = match ($topic) {
            'calculator' => "🧮 Calculator Help:\n\nUse the 'calculate' tool to perform mathematical calculations.\n\nExamples:\n- calculate('2 + 2')\n- calculate('10 * 5 + 3')\n- calculate('(100 - 20) / 4')\n\nSupported operations: +, -, *, /, parentheses",

            'notes' => "📝 Notes Help:\n\nManage your notes with these tools:\n\n- save-note(title, content) - Save a new note\n- get-note(title) - Retrieve a note by title\n- list-notes() - See all your notes\n\nNotes are stored in memory and will be lost when the server restarts.",

            'system' => "💻 System Help:\n\nAccess system information:\n\n- Read resource 'system://info' to get server status and system information\n\nThis includes PHP version, memory usage, uptime, and more.",

            default => "🤖 Personal Assistant Help:\n\nWelcome! I'm your personal assistant MCP server. Here's what I can help you with:\n\n🧮 **Calculator**: Perform mathematical calculations\n📝 **Notes**: Save, retrieve, and list personal notes\n💻 **System Info**: Get server and system information\n\n**Available Tools:**\n- calculate - Mathematical calculations\n- save-note - Save a note\n- get-note - Retrieve a note\n- list-notes - List all notes\n\n**Available Resources:**\n- system://info - System information\n\n**Getting Help:**\nUse this prompt with different topics:\n- help(topic='calculator') - Calculator help\n- help(topic='notes') - Notes help\n- help(topic='system') - System help\n\nHappy assisting! 🚀"
        };

        return [
            'description' => "Help for {$topic}",
            'messages' => [[
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => $helpContent
                ]
            ]]
        ];
    }
);

// 🚀 Start the server
echo "🤖 Starting Personal Assistant MCP Server...\n";
echo "Available capabilities:\n";
echo "  🧮 Calculator tool\n";
echo "  📝 Note management tools\n";
echo "  💻 System information resource\n";
echo "  💡 Interactive help prompt\n";
echo "\n✅ Server ready for connections!\n";

$transport = new StdioServerTransport();

async(function() use ($server, $transport) {
    try {
        yield $server->connect($transport);
    } catch (\Exception $e) {
        error_log("❌ Server error: " . $e->getMessage());
        exit(1);
    }
})->await();
```

### Step 3: Make It Executable (30 seconds)

```bash
chmod +x personal-assistant-server.php
```

### Step 4: Test Your Server (2 minutes)

#### Option A: Test with MCP Inspector (Recommended)

```bash
# Install MCP Inspector (requires Node.js)
npm install -g @modelcontextprotocol/inspector

# Test your server
mcp-inspector ./personal-assistant-server.php
```

This opens a web interface where you can:

- ✅ View all available tools and resources
- ✅ Test tool calls with different parameters
- ✅ Inspect JSON-RPC messages
- ✅ Debug any issues

#### Option B: Test with a Simple Client

Create `test-client.php`:

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use function Amp\async;

echo "🧪 Testing Personal Assistant Server\n";
echo "===================================\n\n";

$client = new Client(new Implementation('test-client', '1.0.0'));

$transport = new StdioClientTransport([
    'command' => 'php',
    'args' => [__DIR__ . '/personal-assistant-server.php']
]);

async(function() use ($client, $transport) {
    try {
        // Connect to server
        echo "🔌 Connecting to server...\n";
        $initResult = yield $client->connect($transport);
        echo "✅ Connected to: {$initResult->serverInfo->name}\n\n";

        // Test calculator tool
        echo "🧮 Testing calculator...\n";
        $calcResult = yield $client->callTool('calculate', ['expression' => '15 + 27']);
        echo "Result: {$calcResult->content[0]->text}\n\n";

        // Test note saving
        echo "📝 Testing note saving...\n";
        $saveResult = yield $client->callTool('save-note', [
            'title' => 'My First Note',
            'content' => 'This is my first note created with MCP!'
        ]);
        echo "Result: {$saveResult->content[0]->text}\n\n";

        // Test note retrieval
        echo "📖 Testing note retrieval...\n";
        $getResult = yield $client->callTool('get-note', ['title' => 'My First Note']);
        echo "Result:\n{$getResult->content[0]->text}\n\n";

        // Test system resource
        echo "💻 Testing system resource...\n";
        $sysResult = yield $client->readResource('system://info');
        echo "System Info:\n{$sysResult->contents[0]->text}\n\n";

        // Test help prompt
        echo "💡 Testing help prompt...\n";
        $helpResult = yield $client->getPrompt('help');
        echo "Help Content:\n{$helpResult->messages[0]->content[0]->text}\n\n";

        // Clean shutdown
        echo "🔌 Disconnecting...\n";
        yield $client->close();
        echo "✅ Test completed successfully!\n";

    } catch (\Exception $e) {
        echo "❌ Test failed: {$e->getMessage()}\n";
    }
})->await();
```

```bash
chmod +x test-client.php
php test-client.php
```

### Step 5: Understanding Your Server (3 minutes)

Let's break down what you just built:

#### 🏗️ Server Structure

```php
// 1. Server Creation
$server = new McpServer(
    new Implementation('name', 'version', 'description')
);

// 2. Tool Registration
$server->registerTool($name, $config, $handler);

// 3. Resource Registration
$server->registerResource($uriTemplate, $config, $handler);

// 4. Prompt Registration
$server->registerPrompt($name, $config, $handler);

// 5. Start Server
yield $server->connect($transport);
```

#### 🔧 Tool Components

Every tool has three parts:

1. **Name** - Unique identifier (`'calculate'`)
2. **Configuration** - Schema and metadata
3. **Handler** - Function that does the work

```php
$server->registerTool(
    'tool-name',                    // 1. Name
    [                               // 2. Configuration
        'description' => 'What it does',
        'inputSchema' => [/* JSON Schema */]
    ],
    function (array $params): array { // 3. Handler
        // Your logic here
        return ['content' => [/* results */]];
    }
);
```

#### 📦 Resource Components

Resources provide data through URI templates:

```php
$server->registerResource(
    'system-info',                 // Resource name
    'system://info',               // URI template
    [                              // Metadata
        'title' => 'System Information',
        'description' => 'System information and status',
        'mimeType' => 'application/json'
    ],
    function (string $uri): array { // Content provider
        return ['contents' => [/* data */]];
    }
);
```

#### 💭 Prompt Components

Prompts help LLMs understand how to use your server:

```php
$server->registerPrompt(
    'help',                        // Prompt name
    [                              // Configuration
        'description' => 'Get help',
        'arguments' => [/* argument specs */]
    ],
    function (array $args): array { // Content generator
        return [
            'description' => 'Help content',
            'messages' => [/* chat messages */]
        ];
    }
);
```

## 🎉 Congratulations!

You've just built your first MCP server! Here's what you accomplished:

✅ **Created a working MCP server** with multiple capabilities  
✅ **Implemented tools** that perform useful functions  
✅ **Added resources** that provide system information  
✅ **Created prompts** that guide user interaction  
✅ **Tested everything** with a client

## 🚀 Next Steps

Now that you have a working server, here are some ideas to explore:

### Immediate Enhancements (10 minutes each)

1. **Add More Tools**:

   ```php
   // Weather tool
   $server->registerTool('get-weather', $config, $weatherHandler);

   // File operations
   $server->registerTool('read-file', $config, $fileHandler);

   // Database queries
   $server->registerTool('query-db', $config, $dbHandler);
   ```

2. **Add More Resources**:

   ```php
   // Configuration files
   $server->registerResource('config://{env}', $config, $configHandler);

   // Log files
   $server->registerResource('logs://{date}', $config, $logHandler);
   ```

3. **Persist Notes** (use SQLite or files):
   ```php
   // Replace in-memory storage with SQLite
   $pdo = new PDO('sqlite:notes.db');
   $pdo->exec('CREATE TABLE IF NOT EXISTS notes (title TEXT PRIMARY KEY, content TEXT, created TEXT)');
   ```

### Learning Path

1. **📖 Learn Core Concepts**: [Understanding MCP](understanding-mcp.md)
2. **🔧 Advanced Tools**: [Tools Development Guide](../guides/server-development/tools-guide.md)
3. **🔐 Add Security**: [Authentication Guide](../guides/security/authentication.md)
4. **🌐 Web Deployment**: [HTTP Transport Guide](../guides/transport/http-transport.md)
5. **🏗️ Framework Integration**: [Laravel Integration](../guides/integrations/laravel-integration.md)

### Real-World Examples

- [📊 Analytics Server](../examples/real-world/analytics-server/) - Data analysis tools
- [📝 Blog CMS](../examples/real-world/blog-cms/) - Content management
- [🤖 AI Assistant](../examples/integrations/openai-assistant/) - AI-powered tools
- [📱 API Gateway](../examples/real-world/api-gateway/) - Service orchestration

## 🆘 Need Help?

### Common Issues

**Server won't start**:

```bash
# Check PHP version
php --version  # Should be 8.1+

# Check syntax
php -l personal-assistant-server.php

# Run with debug
DEBUG=1 php personal-assistant-server.php
```

**Client connection fails**:

```bash
# Check if server is executable
ls -la personal-assistant-server.php

# Test server directly
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0.0"}}}' | php personal-assistant-server.php
```

**Tool errors**:

- Check parameter names match the schema
- Verify required parameters are provided
- Look at error messages for clues

### Getting Help

- 📖 [Complete Documentation](../../README.md)
- 🐛 [Report Issues](https://github.com/dalehurley/php-mcp-sdk/issues)
- 💬 [Community Discussions](https://github.com/dalehurley/php-mcp-sdk/discussions)
- 📧 [Direct Support](mailto:support@example.com)

## 🎯 What's Next?

You're now ready to build more sophisticated MCP servers! Here are some paths to explore:

- **🔧 Advanced Server Development**: Build production-ready servers with databases, APIs, and complex logic
- **📱 Client Development**: Create clients that connect to multiple servers
- **🌐 Web Integration**: Deploy your servers with HTTP transport for web access
- **🤖 AI Integration**: Connect your servers to OpenAI, Claude, or other LLMs
- **🏗️ Framework Integration**: Integrate with Laravel, Symfony, or other PHP frameworks

**Ready for the next challenge?** → [Build Your First Client](first-client.md)

---

_🎉 You've taken your first step into the MCP ecosystem. Welcome aboard!_
