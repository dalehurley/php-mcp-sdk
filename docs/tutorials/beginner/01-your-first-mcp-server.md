# Tutorial 1: Your First MCP Server

Welcome to your MCP journey! In this interactive tutorial, you'll build your very first MCP server from scratch. By the end, you'll have a working server that can perform calculations and understand the core concepts of MCP.

**⏱️ Estimated Time:** 15 minutes  
**📋 Prerequisites:** PHP 8.1+, Composer, basic PHP knowledge  
**🎯 Goal:** Build and test a functional MCP server

## 🎯 What You'll Build

A **Calculator MCP Server** that provides:

- ✅ Addition and subtraction tools
- ✅ A help resource
- ✅ Proper error handling
- ✅ Integration with Claude Desktop

## 📚 Learning Objectives

By completing this tutorial, you'll understand:

- How to create an MCP server
- How to add tools with input schemas
- How to handle tool execution
- How to test your server
- How MCP enables AI-tool integration

## 🚀 Step 1: Environment Setup (3 minutes)

### Create Your Project

```bash
# Create a new directory for your first MCP server
mkdir my-first-mcp-server
cd my-first-mcp-server

# Initialize a new Composer project
composer init --name="my-company/mcp-calculator" --no-interaction

# Install the PHP MCP SDK
composer require dalehurley/php-mcp-sdk
```

### Verify Installation

```bash
# Check that everything is installed correctly
composer show dalehurley/php-mcp-sdk
```

You should see the MCP SDK package information. If not, check the [troubleshooting guide](../../getting-started/troubleshooting.md).

✅ **Checkpoint:** You have a new project with the MCP SDK installed.

## 🔧 Step 2: Create Your First Server (5 minutes)

Create a file called `calculator-server.php`:

```php
#!/usr/bin/env php
<?php

/**
 * My First MCP Server - Calculator
 *
 * This server provides basic mathematical operations.
 * Perfect for learning MCP server development!
 */

require_once __DIR__ . '/vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use function Amp\async;

// Step 1: Create the server
$server = new McpServer(
    new Implementation(
        'my-calculator-server',  // Server name
        '1.0.0'                 // Version
    )
);

echo "🧮 Calculator MCP Server created!\n";
```

### Test the Basic Server

```bash
# Make the file executable
chmod +x calculator-server.php

# Test that it runs (it won't do much yet!)
php calculator-server.php
```

You should see the message "Calculator MCP Server created!" and then the script will end. This is expected - we haven't added the transport layer yet.

✅ **Checkpoint:** You have a basic MCP server that instantiates correctly.

## 🔧 Step 3: Add Your First Tool (4 minutes)

Now let's add a tool that can perform addition. Add this code to your `calculator-server.php` file, after creating the server:

```php
// Step 2: Add an addition tool
$server->tool(
    'add',                    // Tool name
    'Add two numbers together', // Description
    [                         // Input schema (JSON Schema format)
        'type' => 'object',
        'properties' => [
            'a' => [
                'type' => 'number',
                'description' => 'First number'
            ],
            'b' => [
                'type' => 'number',
                'description' => 'Second number'
            ]
        ],
        'required' => ['a', 'b']
    ],
    function (array $args): array {  // Tool handler function
        // Extract the numbers from arguments
        $a = $args['a'];
        $b = $args['b'];

        // Perform the calculation
        $result = $a + $b;

        // Return the result in MCP format
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$a} + {$b} = {$result}"
                ]
            ]
        ];
    }
);

echo "➕ Addition tool added!\n";
```

### Understanding the Tool Structure

Let's break down what each part does:

- **Tool Name** (`'add'`): How clients will call this tool
- **Description**: Human-readable explanation of what the tool does
- **Input Schema**: Defines what parameters the tool expects (JSON Schema format)
- **Handler Function**: The actual code that executes when the tool is called

✅ **Checkpoint:** Your server has a working addition tool.

## 🔧 Step 4: Add the Transport Layer (2 minutes)

Now let's make your server actually listen for connections. Add this at the end of your file:

```php
// Step 3: Start the server with transport
async(function () use ($server) {
    echo "🚀 Starting calculator server...\n";

    // Create STDIO transport (for command-line communication)
    $transport = new StdioServerTransport();

    // Connect and start listening
    $server->connect($transport)->await();
})->await();
```

### Complete Server Code

Your complete `calculator-server.php` should now look like this:

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use function Amp\async;

// Step 1: Create the server
$server = new McpServer(
    new Implementation('my-calculator-server', '1.0.0')
);

// Step 2: Add an addition tool
$server->tool(
    'add',
    'Add two numbers together',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number']
        ],
        'required' => ['a', 'b']
    ],
    function (array $args): array {
        $a = $args['a'];
        $b = $args['b'];
        $result = $a + $b;

        return [
            'content' => [
                ['type' => 'text', 'text' => "{$a} + {$b} = {$result}"]
            ]
        ];
    }
);

// Step 3: Start the server
async(function () use ($server) {
    echo "🚀 Calculator MCP Server starting...\n";

    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
```

✅ **Checkpoint:** Your server is complete and ready to accept connections!

## 🧪 Step 5: Test Your Server (1 minute)

### Test with Claude Desktop

Add your server to Claude Desktop's configuration:

```json
{
  "mcpServers": {
    "my-calculator": {
      "command": "php",
      "args": ["/full/path/to/your/calculator-server.php"]
    }
  }
}
```

### Test Manually

```bash
# Run your server (it will wait for connections)
php calculator-server.php
```

In another terminal, you can test it with the MCP Inspector:

```bash
# Install MCP Inspector (requires Node.js)
npx @modelcontextprotocol/inspector /path/to/calculator-server.php
```

## 🎉 Congratulations!

You've successfully built your first MCP server! Here's what you accomplished:

- ✅ Created an MCP server with proper initialization
- ✅ Added a functional tool with input validation
- ✅ Implemented proper error handling
- ✅ Set up transport layer for communication
- ✅ Tested the server with real clients

## 🧠 Key Concepts Learned

### 1. MCP Server Structure

```php
$server = new McpServer(new Implementation($name, $version));
$server->tool($name, $description, $schema, $handler);
$server->connect($transport)->await();
```

### 2. Tool Handler Pattern

```php
function (array $args): array {
    // 1. Extract and validate inputs
    // 2. Perform the operation
    // 3. Return formatted result
    return ['content' => [['type' => 'text', 'text' => $result]]];
}
```

### 3. JSON Schema Validation

```php
[
    'type' => 'object',
    'properties' => [
        'param' => ['type' => 'number', 'description' => 'Parameter description']
    ],
    'required' => ['param']
]
```

## 🔮 What's Next?

Now that you have a working MCP server, you're ready for:

1. **[Tutorial 2: Adding Tools and Resources](02-adding-tools.md)** - Expand your server's capabilities
2. **[Calculator Example](../../../examples/getting-started/basic-calculator.php)** - See a more complete calculator
3. **[Server Development Guide](../../guides/server-development/creating-servers.md)** - Deep dive into server development

## 💡 Challenge Exercise

Before moving to the next tutorial, try adding a subtraction tool to your server:

```php
$server->tool(
    'subtract',
    'Subtract second number from first number',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number']
        ],
        'required' => ['a', 'b']
    ],
    function (array $args): array {
        $result = $args['a'] - $args['b'];
        return [
            'content' => [
                ['type' => 'text', 'text' => "{$args['a']} - {$args['b']} = {$result}"]
            ]
        ];
    }
);
```

Test it by asking Claude: "Use the subtract tool to calculate 10 - 3"

## 🐛 Troubleshooting

### Common Issues

**"Class not found" errors:**

- Run `composer install` to ensure dependencies are installed
- Check that the autoloader is included: `require_once __DIR__ . '/vendor/autoload.php';`

**"Permission denied" errors:**

- Make your PHP file executable: `chmod +x calculator-server.php`
- Check file permissions and ownership

**Server doesn't respond:**

- Ensure the server is running before connecting clients
- Check that no other process is using the same resources
- Verify the file path in your Claude Desktop configuration

**Need more help?** Check the [troubleshooting guide](../../getting-started/troubleshooting.md).

---

🎉 **Congratulations on building your first MCP server!** You're now ready to explore the amazing world of MCP development. The next tutorial will show you how to add more sophisticated tools and resources to create even more powerful servers.

**Ready for more?** → [Tutorial 2: Adding Tools and Resources](02-adding-tools.md)
