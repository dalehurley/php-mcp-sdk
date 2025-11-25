# MCP-UI Examples

This directory contains examples demonstrating how to use MCP-UI features
in the PHP MCP SDK.

## Overview

MCP-UI enables MCP servers to return interactive HTML widgets alongside
text responses. Compatible hosts (Goose, LibreChat, etc.) render these
widgets in sandboxed iframes, providing rich user experiences.

## Examples

### 1. Weather Widget Server

**File:** `weather-widget-server.php`

A server that provides weather information as interactive widgets.

**Tools:**
- `get_weather_widget` - Returns a beautiful weather card with refresh functionality
- `compare_weather` - Compares weather across multiple cities

**Run:**
```bash
php examples/ui/weather-widget-server.php
```

**Test with MCP Inspector:**
```bash
npx @modelcontextprotocol/inspector php examples/ui/weather-widget-server.php
```

### 2. Dashboard Server

**File:** `dashboard-server.php`

A server demonstrating UITemplate usage for common UI patterns.

**Tools:**
- `get_stats_dashboard` - Metrics dashboard with key statistics
- `get_users_table` - Interactive data table
- `get_contact_form` - Form with submission handling
- `submit_contact` - Handles form submissions
- `get_info_card` - Customizable info cards

**Run:**
```bash
php examples/ui/dashboard-server.php
```

### 3. UI-Aware Client

**File:** `ui-aware-client.php`

A client demonstrating how to consume and handle UI resources.

**Features:**
- Parsing UI resources from tool responses
- Rendering widgets as HTML
- Generating frontend-ready JSON
- Text-only extraction for fallback

**Run:**
```bash
# Start the weather server first
php examples/ui/weather-widget-server.php &

# Then run the client
php examples/ui/ui-aware-client.php
```

## Key Concepts

### Server Side: Creating UI Resources

```php
use MCP\UI\UIResource;
use MCP\UI\UITemplate;

// Method 1: Raw HTML
$html = '<html><body><h1>Hello!</h1></body></html>';
$resource = UIResource::html('ui://greeting/1', $html);

// Method 2: Using templates
$html = UITemplate::card([
    'title' => 'Welcome',
    'content' => '<p>Hello, World!</p>',
    'actions' => [
        ['label' => 'Click Me', 'onclick' => "mcpNotify('clicked')"]
    ]
]);
$resource = UIResource::html('ui://welcome/card', $html);

// Return in tool response
return [
    'content' => [
        ['type' => 'text', 'text' => 'Text fallback'],
        $resource
    ]
];
```

### Client Side: Handling UI Resources

```php
use MCP\UI\UIResourceClient;
use MCP\UI\UIResourceParser;

// Wrap your MCP client
$uiClient = new UIResourceClient($client);

// Get parsed response
$result = $uiClient->callToolWithUI('my_tool', ['arg' => 'value']);
echo $result['text'];        // Text content
foreach ($result['ui'] as $resource) {
    echo $resource->uri;     // UI resource URI
    echo $resource->content; // HTML/URL content
}

// Get JSON for API responses
$json = $uiClient->callToolForFrontend('my_tool', ['arg' => 'value']);
return response()->json($json);
```

### Web Rendering

```php
use MCP\UI\UIResourceRenderer;

// Render as iframe
echo UIResourceRenderer::renderIframe($resource, [
    'width' => '100%',
    'height' => '400px'
]);

// Add action handler script (once per page)
echo UIResourceRenderer::actionHandlerScript([
    'endpoint' => '/api/mcp/action'
]);

// Add styles (once per page)
echo UIResourceRenderer::styles();
```

## UI Actions

Widgets communicate with the host via `postMessage`. Common actions:

```javascript
// Trigger a tool call
mcpToolCall('tool_name', { arg: 'value' });

// Send a notification
mcpNotify('Something happened!');

// Send a prompt to the conversation
mcpPrompt('User wants to know about X');

// Open a URL
mcpLink('https://example.com');
```

## Supported Hosts

MCP-UI resources are rendered by:

- [Goose](https://github.com/block/goose) - Open source AI agent
- [LibreChat](https://github.com/danny-avila/LibreChat) - ChatGPT clone
- [ui-inspector](https://github.com/idosal/ui-inspector) - Local testing tool
- [MCP-UI Chat](https://github.com/idosal/scira-mcp-ui-chat) - Demo chat app

## Further Reading

- [UI Resources Guide](../../docs/guides/server-development/ui-resources-guide.md)
- [MCP-UI GitHub](https://github.com/idosal/mcp-ui)
- [MCP Apps Extension (SEP-1865)](https://blog.modelcontextprotocol.io/posts/2025-11-21-mcp-apps/)

