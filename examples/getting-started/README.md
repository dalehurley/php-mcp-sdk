# Getting Started Examples

Welcome to the PHP MCP SDK getting started examples! These examples are designed to help you understand MCP concepts through practical, working code.

## 📚 Examples Overview

### 1. Hello World Server & Client

**Files:** `hello-world-server.php`, `hello-world-client.php`

The absolute simplest MCP server and client pair. Perfect for understanding the basic concepts.

**Server Features:**

- Single `say_hello` tool
- Basic error handling
- Minimal configuration

**Client Features:**

- Connects to hello-world-server
- Discovers and calls tools
- Simple output formatting

**Usage:**

```bash
# Terminal 1: Start the server
php hello-world-server.php

# Terminal 2: Run the client
php hello-world-client.php
```

### 2. Basic Calculator

**File:** `basic-calculator.php`

A calculator server demonstrating multiple tools and error handling.

**Features:**

- Multiple math operations (add, subtract, multiply, divide, power, sqrt)
- Input validation and error handling
- Resources and prompts
- Practical tool design

**Usage:**

```bash
php basic-calculator.php
```

**Test with Claude Desktop:**

```json
{
  "mcpServers": {
    "calculator": {
      "command": "php",
      "args": ["/path/to/basic-calculator.php"]
    }
  }
}
```

### 3. File Reader Server

**File:** `file-reader-server.php`

Demonstrates resource management and file system integration.

**Features:**

- Safe file reading with security checks
- Directory listing
- File information
- Resource management
- Path validation

**Usage:**

```bash
php file-reader-server.php
```

**Security Features:**

- Restricts access to current directory and subdirectories
- Validates all file paths
- Checks permissions before operations

### 4. Weather Client

**File:** `weather-client.php`

Shows how to integrate external APIs (mock weather service).

**Features:**

- Current weather information
- 5-day forecasts
- City comparisons
- Error handling for external services
- Mock API integration

**Usage:**

```bash
php weather-client.php
```

**Available Cities:** London, Paris, Tokyo, New York

## 🚀 Quick Start

1. **Install Dependencies:**

   ```bash
   cd /path/to/php-mcp-sdk
   composer install
   ```

2. **Run Hello World:**

   ```bash
   php examples/getting-started/hello-world-server.php
   ```

3. **Test with Client:**
   ```bash
   # In another terminal
   php examples/getting-started/hello-world-client.php
   ```

## 🧪 Testing Examples

All examples are automatically tested as part of the documentation testing framework:

```bash
# From the fullcxexamplemcp directory
php test-documentation.php ../php-mcp-sdk/docs
```

## 📖 Learning Path

1. **Start Here:** `hello-world-server.php` and `hello-world-client.php`

   - Understand basic MCP concepts
   - See client-server communication
   - Learn tool calling

2. **Add Complexity:** `basic-calculator.php`

   - Multiple tools
   - Input validation
   - Error handling
   - Resources and prompts

3. **External Integration:** `file-reader-server.php`

   - File system integration
   - Security considerations
   - Resource management

4. **API Integration:** `weather-client.php`
   - External API patterns
   - Data transformation
   - Service integration

## 🔧 Configuration

### Claude Desktop Integration

Add any of these servers to your Claude Desktop configuration:

```json
{
  "mcpServers": {
    "hello-world": {
      "command": "php",
      "args": ["/path/to/examples/getting-started/hello-world-server.php"]
    },
    "calculator": {
      "command": "php",
      "args": ["/path/to/examples/getting-started/basic-calculator.php"]
    },
    "file-reader": {
      "command": "php",
      "args": ["/path/to/examples/getting-started/file-reader-server.php"]
    },
    "weather": {
      "command": "php",
      "args": ["/path/to/examples/getting-started/weather-client.php"]
    }
  }
}
```

### Environment Setup

These examples work out of the box with no additional configuration. For production use:

1. **Security:** Review file access permissions
2. **APIs:** Replace mock data with real API integrations
3. **Error Handling:** Add comprehensive logging
4. **Performance:** Add caching and optimization

## 🎯 Next Steps

After completing these examples:

1. **Read the Guides:** Check out `docs/guides/` for detailed explanations
2. **Advanced Examples:** Explore `examples/real-world/` for complex applications
3. **Framework Integration:** See `examples/integrations/` for Laravel, Symfony, etc.
4. **Build Your Own:** Use these as templates for your own servers

## 🐛 Troubleshooting

### Common Issues

**"Command not found" errors:**

- Ensure PHP 8.1+ is installed: `php --version`
- Check that composer dependencies are installed: `composer install`

**"Permission denied" errors:**

- Make files executable: `chmod +x *.php`
- Check file permissions in file-reader-server

**"Connection failed" errors:**

- Ensure server is running before starting client
- Check that ports aren't already in use

**"Class not found" errors:**

- Run `composer install` from the project root
- Check that autoloader is properly included

### Getting Help

- 📖 **Documentation:** Check `docs/getting-started/troubleshooting.md`
- 💬 **Issues:** Open an issue on GitHub
- 🔍 **Examples:** Look at working examples in `examples/`

## 📝 Notes

- All examples include comprehensive error handling
- Code is heavily commented for learning
- Examples are tested automatically
- Security best practices are demonstrated
- Real-world patterns are used throughout

Happy coding with MCP! 🎉
