# Troubleshooting Guide

This comprehensive guide helps you resolve common issues when working with the PHP MCP SDK. Whether you're just getting started or building advanced applications, you'll find solutions to the most frequent problems here.

## 🚨 Quick Fixes

### Most Common Issues

1. **"Class not found" errors** → Run `composer install`
2. **"Permission denied"** → Make PHP files executable: `chmod +x server.php`
3. **"Connection refused"** → Check server is running and ports are available
4. **"Invalid JSON"** → Validate your JSON configuration files
5. **"PHP version"** → Ensure PHP 8.1+ is installed

## 📋 Installation Issues

### Composer Problems

**Error: `composer: command not found`**

```bash
# Install Composer globally
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Or use Homebrew on macOS
brew install composer
```

**Error: `Your requirements could not be resolved`**

```bash
# Clear composer cache
composer clear-cache

# Update composer itself
composer self-update

# Try with --ignore-platform-reqs (if PHP version issues)
composer install --ignore-platform-reqs
```

**Error: `Package not found`**

```bash
# Ensure you have the correct package name
composer require dalehurley/php-mcp-sdk

# Check if you need to add a repository
composer config repositories.mcp vcs https://github.com/dalehurley/php-mcp-sdk
```

### PHP Version Issues

**Error: `PHP Fatal error: This package requires PHP >=8.1`**

```bash
# Check current PHP version
php --version

# Install PHP 8.1+ (Ubuntu/Debian)
sudo apt update
sudo apt install php8.1 php8.1-cli php8.1-mbstring php8.1-xml

# Install PHP 8.1+ (macOS with Homebrew)
brew install php@8.1
brew link php@8.1

# Install PHP 8.1+ (Windows)
# Download from https://windows.php.net/download/
```

**Error: `Call to undefined function`**

```bash
# Install required PHP extensions
sudo apt install php8.1-mbstring php8.1-xml php8.1-curl php8.1-json

# Or on macOS
brew install php@8.1

# Check loaded extensions
php -m
```

## 🔌 Connection Issues

### Server Connection Problems

**Error: `Connection refused` or `Connection timeout`**

```php
// Check if server is running
// Terminal 1:
php your-server.php

// Terminal 2: Test connection
php your-client.php
```

**Debugging Steps:**

1. **Verify Server Status:**

   ```bash
   # Check if server process is running
   ps aux | grep php

   # Check network connections
   netstat -tulpn | grep php
   ```

2. **Test with Simple Example:**

   ```bash
   # Use hello-world examples first
   php examples/getting-started/hello-world-server.php
   php examples/getting-started/hello-world-client.php
   ```

3. **Check Transport Configuration:**
   ```php
   // Ensure transport is properly configured
   $transport = new StdioServerTransport();
   // or
   $transport = new StdioClientTransport(['php', 'server.php']);
   ```

### STDIO Transport Issues

**Error: `Process terminated unexpectedly`**

```php
// Add error handling to your server
try {
    $server->run();
} catch (Exception $e) {
    error_log("Server error: " . $e->getMessage());
    exit(1);
}
```

**Error: `Broken pipe` or `Connection reset`**

```php
// Add proper cleanup
register_shutdown_function(function() {
    // Cleanup resources
});

// Handle SIGTERM/SIGINT
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() { exit(0); });
    pcntl_signal(SIGINT, function() { exit(0); });
}
```

## 🛠️ Server Issues

### Tool Registration Problems

**Error: `Tool 'name' already registered`**

```php
// Check for duplicate tool names
$server = new McpServer(/* ... */);

// Use unique names
$server->addTool('calculator_add', /* ... */);
$server->addTool('calculator_subtract', /* ... */);

// Or check if already registered
if (!$server->hasToolRegistered('my_tool')) {
    $server->addTool('my_tool', /* ... */);
}
```

**Error: `Invalid tool schema`**

```php
// Ensure proper JSON schema format
$server->addTool(
    name: 'my_tool',
    description: 'Tool description',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'param1' => [
                'type' => 'string',
                'description' => 'Parameter description'
            ]
        ],
        'required' => ['param1'] // Must be array, not string
    ],
    handler: function (array $args): array {
        // Must return array with 'content' key
        return [
            'content' => [
                ['type' => 'text', 'text' => 'Result']
            ]
        ];
    }
);
```

### Resource Issues

**Error: `Resource URI must be absolute`**

```php
// Correct URI format
$server->addResource(
    uri: 'myapp://resource/path',  // ✅ Absolute URI
    name: 'Resource Name',
    // ...
);

// Incorrect
$server->addResource(
    uri: '/resource/path',  // ❌ Relative path
    // ...
);
```

**Error: `Resource handler must return string`**

```php
// Correct resource handler
$server->addResource(
    uri: 'myapp://data',
    handler: function(): string {
        return json_encode(['data' => 'value']); // ✅ Returns string
    }
);

// Incorrect
$server->addResource(
    uri: 'myapp://data',
    handler: function(): array {
        return ['data' => 'value']; // ❌ Returns array
    }
);
```

### Prompt Issues

**Error: `Invalid prompt format`**

```php
// Correct prompt structure
$server->addPrompt(
    name: 'help',
    description: 'Get help',
    handler: function(): array {
        return [
            'description' => 'Help prompt',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'How do I use this?']
                    ]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'text', 'text' => 'Here is how...']
                    ]
                ]
            ]
        ];
    }
);
```

## 👤 Client Issues

### Connection Problems

**Error: `Client initialization failed`**

```php
// Ensure proper initialization sequence
$client = new Client(new Implementation(/* ... */));

// Connect first
await $client->connect($transport);

// Then initialize
await $client->initialize();

// Then use client methods
$tools = await $client->listTools();
```

**Error: `Tool call failed`**

```php
// Check tool exists before calling
$tools = await $client->listTools();
$toolExists = false;
foreach ($tools['tools'] as $tool) {
    if ($tool['name'] === 'my_tool') {
        $toolExists = true;
        break;
    }
}

if ($toolExists) {
    $result = await $client->callTool('my_tool', ['param' => 'value']);
} else {
    throw new Exception("Tool 'my_tool' not available");
}
```

### Async/Await Issues

**Error: `await can only be used in async functions`**

```php
use function Amp\async;

// Wrap your client code in async
async(function() {
    $client = new Client(/* ... */);
    await $client->connect($transport);
    // ... rest of client code
});
```

**Error: `Promise rejection unhandled`**

```php
// Add proper error handling
async(function() {
    try {
        $client = new Client(/* ... */);
        await $client->connect($transport);
        $result = await $client->callTool('tool', []);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
});
```

## 🔒 Security Issues

### File Access Problems

**Error: `Permission denied` when accessing files**

```bash
# Check file permissions
ls -la your-file.php

# Make executable
chmod +x your-file.php

# Check directory permissions
chmod 755 your-directory/
```

**Error: `Path traversal detected`**

```php
// Use proper path validation
function isSafePath(string $path, string $basePath): bool {
    $realPath = realpath($path);
    $realBasePath = realpath($basePath);

    return $realPath !== false &&
           $realBasePath !== false &&
           strpos($realPath, $realBasePath) === 0;
}

// Check before file operations
if (!isSafePath($userPath, $safeDirectory)) {
    throw new McpError(-32602, 'Access denied');
}
```

### Authentication Issues

**Error: `Invalid bearer token`**

```php
// Check token format and validity
$config = [
    'bearer_token' => 'your-actual-token-here', // Not 'YOUR_TOKEN'
    'timeout' => 30,
];

// Verify token is properly set
if (empty($config['bearer_token']) || $config['bearer_token'] === 'YOUR_TOKEN') {
    throw new Exception('Bearer token not configured');
}
```

## 🐛 Debugging Techniques

### Enable Debug Logging

```php
// Add to your server/client
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('mcp');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

// Use in your code
$logger->debug('Server starting...');
$logger->info('Tool called: ' . $toolName);
$logger->error('Error occurred: ' . $e->getMessage());
```

### Verbose Output

```php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add verbose output to your applications
if (isset($_ENV['MCP_VERBOSE']) || in_array('--verbose', $argv)) {
    echo "Debug: Connection established\n";
    echo "Debug: Tools available: " . count($tools) . "\n";
}
```

### JSON Validation

```php
// Validate JSON before sending
function validateJson($data): bool {
    json_encode($data);
    return json_last_error() === JSON_ERROR_NONE;
}

// Use before sending responses
$response = ['content' => [/* ... */]];
if (!validateJson($response)) {
    throw new Exception('Invalid JSON response: ' . json_last_error_msg());
}
```

### Network Debugging

```bash
# Monitor network traffic (Linux/macOS)
sudo tcpdump -i lo port 8080

# Check process connections
lsof -p $(pgrep -f "php.*server")

# Test with netcat
nc -l 8080  # Listen on port
nc localhost 8080  # Connect to port
```

## 🔧 Environment Issues

### Environment Variables

**Error: `Environment variable not set`**

```php
// Check environment variables
$requiredVars = ['API_KEY', 'SERVER_PORT', 'LOG_LEVEL'];
foreach ($requiredVars as $var) {
    if (!getenv($var)) {
        throw new Exception("Required environment variable not set: {$var}");
    }
}

// Use with defaults
$apiKey = getenv('API_KEY') ?: 'default-key';
$port = (int)(getenv('SERVER_PORT') ?: 8080);
```

### Configuration Files

**Error: `Configuration file not found`**

```php
// Check multiple locations
$configPaths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config/config.php',
    '/etc/mcp/config.php',
];

$config = null;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        $config = require $path;
        break;
    }
}

if (!$config) {
    throw new Exception('Configuration file not found in: ' . implode(', ', $configPaths));
}
```

## 🧪 Testing Issues

### Unit Test Problems

**Error: `Class 'PHPUnit\Framework\TestCase' not found`**

```bash
# Install PHPUnit
composer require --dev phpunit/phpunit

# Run tests
./vendor/bin/phpunit tests/
```

**Error: `Mock objects not working`**

```php
// Use proper mocking
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class MyTest extends TestCase
{
    public function testServer(): void
    {
        /** @var MockObject|Transport $transport */
        $transport = $this->createMock(Transport::class);
        $transport->expects($this->once())
                 ->method('send')
                 ->with($this->anything());

        $server = new McpServer(/* ... */, $transport);
        // ... test code
    }
}
```

### Integration Test Issues

**Error: `Server not responding in tests`**

```php
// Add timeouts and retries
async(function() {
    $maxAttempts = 5;
    $attempt = 0;

    while ($attempt < $maxAttempts) {
        try {
            await $client->connect($transport);
            break;
        } catch (Exception $e) {
            $attempt++;
            if ($attempt >= $maxAttempts) {
                throw $e;
            }
            await delay(1000); // Wait 1 second
        }
    }
});
```

## 📚 Documentation Testing

### Code Example Failures

**Error: `Documentation example failed to run`**

```bash
# Test specific documentation
php /path/to/fullcxexamplemcp/test-documentation.php /path/to/docs

# Test with verbose output
php /path/to/fullcxexamplemcp/test-documentation.php /path/to/docs --verbose
```

**Error: `Code block extraction failed`**

````php
// Ensure proper markdown formatting
// ✅ Correct:
// ```php
// <?php
// echo "Hello World";
// ```

// ❌ Incorrect:
// ```
// <?php
// echo "Hello World";
// ```
````

## 🚀 Performance Issues

### Memory Problems

**Error: `Fatal error: Allowed memory size exhausted`**

```php
// Increase memory limit
ini_set('memory_limit', '256M');

// Monitor memory usage
echo "Memory usage: " . memory_get_usage(true) / 1024 / 1024 . " MB\n";

// Clean up resources
unset($largeArray);
gc_collect_cycles();
```

### Timeout Issues

**Error: `Request timeout`**

```php
// Increase timeout for long-running operations
$client = new Client(/* ... */);
$client->setTimeout(60); // 60 seconds

// Or set per-request timeout
$result = await $client->callTool('slow_tool', [], ['timeout' => 120]);
```

## 🆘 Getting Help

### Before Asking for Help

1. **Check the logs** - Enable debug logging
2. **Minimal reproduction** - Create smallest possible example
3. **Environment info** - PHP version, OS, dependencies
4. **Error messages** - Full error messages and stack traces

### Information to Include

```bash
# System information
php --version
composer --version
uname -a

# PHP extensions
php -m

# Composer dependencies
composer show

# Error logs
tail -f /var/log/php_errors.log
```

### Community Resources

- 📖 **Documentation:** [PHP MCP SDK Docs](../README.md)
- 🐛 **Issues:** [GitHub Issues](https://github.com/dalehurley/php-mcp-sdk/issues)
- 💬 **Discussions:** [GitHub Discussions](https://github.com/dalehurley/php-mcp-sdk/discussions)
- 📧 **Email:** For private issues or security concerns

### Creating Good Bug Reports

````markdown
## Bug Report

**Environment:**

- PHP Version: 8.1.0
- OS: Ubuntu 20.04
- SDK Version: 1.0.0

**Expected Behavior:**
Server should start and accept connections.

**Actual Behavior:**
Server crashes with "Connection refused" error.

**Steps to Reproduce:**

1. Run `php server.php`
2. Run `php client.php`
3. Error occurs

**Code Sample:**

```php
// Minimal code that reproduces the issue
$server = new McpServer(/* ... */);
$server->run();
```
````

**Error Messages:**

```
Fatal error: Connection refused in ...
```

**Additional Context:**

- Works on development machine
- Fails on production server
- Started after upgrading PHP version

```

```

## 🎯 Prevention Tips

### Best Practices

1. **Always use error handling**
2. **Validate inputs and outputs**
3. **Test with minimal examples first**
4. **Keep dependencies updated**
5. **Use proper logging**
6. **Follow security guidelines**

### Common Mistakes to Avoid

- ❌ Not checking if tools exist before calling
- ❌ Forgetting to await async operations
- ❌ Not validating JSON schemas
- ❌ Ignoring error return codes
- ❌ Using relative paths for resources
- ❌ Not handling connection failures
- ❌ Skipping input validation
- ❌ Not cleaning up resources

Remember: Most issues have simple solutions. Start with the basics, check your configuration, and don't hesitate to ask for help! 🤝
