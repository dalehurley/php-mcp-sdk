# UI Resources Guide

This guide covers how to create and consume interactive UI resources in the PHP MCP SDK.

## Overview

MCP-UI extends the Model Context Protocol to support interactive user interfaces. Instead of returning only text, tools can return rich HTML widgets that compatible hosts render in sandboxed iframes.

### Benefits

- **Rich Experiences**: Charts, forms, dashboards instead of plain text
- **Interactivity**: Buttons, inputs, and real-time updates
- **Bidirectional Communication**: Widgets can trigger tool calls and send notifications
- **Progressive Enhancement**: Text fallback for non-UI hosts

### Supported Hosts

- [Goose](https://github.com/block/goose) - Block's open source AI agent
- [LibreChat](https://github.com/danny-avila/LibreChat) - Enhanced ChatGPT clone
- [ui-inspector](https://github.com/idosal/ui-inspector) - Local testing tool
- [MCP-UI Chat](https://github.com/idosal/scira-mcp-ui-chat) - Demo application

## Server Development

### Creating UI Resources

The `UIResource` class provides static methods for creating UI resource blocks:

```php
use MCP\UI\UIResource;

// Inline HTML (most common)
$resource = UIResource::html('ui://myapp/widget', '<html>...</html>');

// External URL (embedded in iframe)
$resource = UIResource::url('ui://myapp/external', 'https://example.com/embed');

// Remote DOM (advanced - uses Shopify's remote-dom)
$resource = UIResource::remoteDom('ui://myapp/dynamic', $script, 'react');
```

### URI Format

URIs must start with `ui://` and should be unique within your application:

```
ui://[namespace]/[resource-type]/[id]

Examples:
ui://weather/city/sydney
ui://dashboard/stats/monthly
ui://form/contact/123
```

### Returning UI Resources

Include UI resources in your tool's content array alongside text:

```php
$server->tool(
    'get_widget',
    'Get an interactive widget',
    ['type' => 'object', 'properties' => []],
    function (array $args): array {
        $html = '<html><body><h1>Hello!</h1></body></html>';
        
        return [
            'content' => [
                // Text fallback for non-UI hosts
                ['type' => 'text', 'text' => 'Hello!'],
                // UI resource for compatible hosts
                UIResource::html('ui://myapp/greeting', $html)
            ]
        ];
    }
);
```

### Using Templates

The `UITemplate` class provides pre-built templates for common patterns:

#### Card Template

```php
use MCP\UI\UITemplate;

$html = UITemplate::card([
    'title' => 'User Profile',
    'icon' => '👤',
    'content' => '<p>John Doe</p><p>john@example.com</p>',
    'gradient' => UITemplate::GRADIENT_BLUE,
    'actions' => [
        ['label' => 'Edit', 'onclick' => "mcpToolCall('edit_user', {id: 123})"],
        ['label' => 'Delete', 'onclick' => "mcpToolCall('delete_user', {id: 123})", 'class' => 'btn btn-secondary']
    ],
    'footer' => 'Last updated: Today'
]);
```

#### Table Template

```php
$html = UITemplate::table(
    'Users',                              // Title
    ['ID', 'Name', 'Email', 'Role'],     // Headers
    [                                      // Rows
        [1, 'Alice', 'alice@example.com', 'Admin'],
        [2, 'Bob', 'bob@example.com', 'User'],
    ],
    [
        'gradient' => UITemplate::GRADIENT_GREEN,
        'striped' => true,
        'hoverable' => true
    ]
);
```

#### Stats Dashboard Template

```php
$html = UITemplate::stats([
    ['label' => 'Revenue', 'value' => '$12,345', 'icon' => '💰', 'color' => '#27ae60'],
    ['label' => 'Users', 'value' => '1,234', 'icon' => '👥', 'color' => '#3498db'],
    ['label' => 'Orders', 'value' => '567', 'icon' => '📦', 'color' => '#9b59b6'],
], [
    'title' => 'Monthly Dashboard',
    'columns' => 3
]);
```

#### Form Template

```php
$html = UITemplate::form([
    ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
    ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => [
        'user' => 'User',
        'admin' => 'Admin'
    ]],
    ['name' => 'message', 'label' => 'Message', 'type' => 'textarea'],
], [
    'title' => 'Create User',
    'submitLabel' => 'Create',
    'submitTool' => 'create_user'  // Tool to call on submit
]);
```

### Custom HTML

For full control, create your own HTML:

```php
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: system-ui; padding: 20px; }
        .btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Custom Widget</h1>
    <button class="btn" onclick="handleClick()">Click Me</button>
    
    <script>
        function handleClick() {
            // Notify the host
            window.parent.postMessage({
                type: 'notify',
                payload: { message: 'Button was clicked!' }
            }, '*');
        }
    </script>
</body>
</html>
HTML;

return UIResource::html('ui://myapp/custom', $html);
```

### UI Actions JavaScript API

Include these helper functions in your HTML for easy host communication:

```php
// Get the helper script
$script = UIResource::actionScript();

// Include in your HTML
$html = "<html><head><script>{$script}</script></head>...</html>";
```

Available functions:

```javascript
// Trigger a tool call
mcpToolCall('tool_name', { arg1: 'value1' });

// Trigger a tool call with async response handling
mcpToolCall('tool_name', { arg1: 'value1' }, 'request-123');
mcpOnResponse('request-123', function(result) {
    console.log('Got response:', result);
});

// Send a notification to the host
mcpNotify('Something happened!');

// Add a prompt to the conversation
mcpPrompt('Tell me more about...');

// Request the host to open a URL
mcpLink('https://example.com');

// Send a custom intent
mcpIntent('custom_action', { data: 'value' });
```

## Client Development

### Using UIResourceClient

Wrap your MCP client for convenient UI resource handling:

```php
use MCP\Client\Client;
use MCP\UI\UIResourceClient;

$client = new Client(new Implementation('my-app', '1.0.0'));
$uiClient = new UIResourceClient($client);
```

#### Get Parsed Response

```php
$result = $uiClient->callToolWithUI('get_weather', ['city' => 'Sydney']);

echo $result['text'];  // "Weather for Sydney: 25°C, Sunny"

foreach ($result['ui'] as $resource) {
    echo $resource->uri;           // "ui://weather/sydney"
    echo $resource->type;          // "html"
    echo $resource->content;       // Full HTML content
    echo $resource->getContentLength(); // Content size in bytes
}
```

#### Get JSON for API Responses

```php
$json = $uiClient->callToolForFrontend('get_weather', ['city' => 'Sydney']);

// Returns:
// {
//     "text": "Weather for Sydney: 25°C",
//     "ui": [
//         {
//             "uri": "ui://weather/sydney",
//             "type": "html",
//             "mimeType": "text/html",
//             "content": "<html>...",
//             "encoding": "text"
//         }
//     ],
//     "hasUI": true
// }

return response()->json($json);
```

#### Get Rendered HTML

```php
$result = $uiClient->callToolWithRenderedUI('get_weather', ['city' => 'Sydney'], [
    'width' => '100%',
    'height' => '400px'
]);

echo $result['html'];  // <iframe srcdoc="..."></iframe>
```

#### Text-Only Fallback

```php
$text = $uiClient->callToolTextOnly('get_weather', ['city' => 'Sydney']);
// "Weather for Sydney: 25°C, Sunny"
```

### Using UIResourceParser

For lower-level parsing:

```php
use MCP\UI\UIResourceParser;

$response = $client->callTool('get_weather', ['city' => 'Sydney']);

// Check for UI resources
if (UIResourceParser::hasUIResources($response)) {
    $count = UIResourceParser::countUIResources($response);
    
    // Parse all content
    $parsed = UIResourceParser::parse($response);
    // $parsed['text'] - array of text blocks
    // $parsed['ui'] - array of UIResourceData objects
    
    // Get specific resource by URI
    $resource = UIResourceParser::findByUri($response, 'ui://weather/sydney');
    
    // Filter by type
    $htmlResources = UIResourceParser::filterByType($response, 'html');
}
```

## Web Rendering

### Rendering Iframes

```php
use MCP\UI\UIResourceRenderer;

// Single resource
echo UIResourceRenderer::renderIframe($resource, [
    'width' => '100%',
    'height' => '400px',
    'sandbox' => 'allow-scripts allow-forms',
    'class' => 'my-widget',
    'title' => 'Weather Widget'
]);

// Multiple resources
echo UIResourceRenderer::renderAll($resources, [
    'height' => '300px'
]);

// Grid layout
echo UIResourceRenderer::renderGrid($resources, [
    'height' => '300px'
], [
    'columns' => 2,
    'gap' => '20px'
]);
```

### Action Handler Script

Add once per page to handle UI actions:

```php
echo UIResourceRenderer::actionHandlerScript([
    'endpoint' => '/api/mcp/ui-action',  // Your backend endpoint
    'debug' => true,                       // Enable console logging
    'onAction' => 'handleMcpAction'        // Custom callback function
]);
```

Then create your backend endpoint:

```php
// Laravel example
Route::post('/api/mcp/ui-action', function (Request $request) {
    $type = $request->input('type');
    $payload = $request->input('payload');
    $messageId = $request->input('messageId');
    
    $result = match($type) {
        'tool' => $this->handleToolCall($payload),
        'notify' => $this->handleNotification($payload),
        'prompt' => $this->handlePrompt($payload),
        'link' => ['url' => $payload['url']],
        default => ['error' => 'Unknown action'],
    };
    
    return response()->json([
        'messageId' => $messageId,
        'result' => $result
    ]);
});
```

### CSS Styles

Add default styles for UI resources:

```php
echo UIResourceRenderer::styles([
    'prefix' => 'mcp-ui'  // CSS class prefix
]);
```

## Security Considerations

### Iframe Sandboxing

All UI content renders in sandboxed iframes with restricted permissions:

```php
// Default: allow-scripts allow-forms
UIResourceRenderer::renderIframe($resource);

// Strict: no scripts
UIResourceRenderer::renderIframe($resource, [
    'sandbox' => UIResourceRenderer::STRICT_SANDBOX
]);

// Permissive: more features
UIResourceRenderer::renderIframe($resource, [
    'sandbox' => UIResourceRenderer::PERMISSIVE_SANDBOX
]);
```

### Content Validation

- Always sanitize user input before including in HTML
- Use `htmlspecialchars()` for text content
- Validate URIs match expected patterns
- Consider Content Security Policy headers

### Action Validation

- Validate all incoming actions from widgets
- Authenticate/authorize tool calls
- Rate limit action endpoints
- Log all actions for auditing

## Best Practices

1. **Always provide text fallback** - Not all hosts support UI resources
2. **Keep widgets self-contained** - Don't rely on external resources
3. **Use semantic URIs** - Make URIs descriptive and consistent
4. **Handle errors gracefully** - Show user-friendly error states
5. **Test across hosts** - Behavior may vary between hosts
6. **Optimize content size** - Use base64 encoding for large content
7. **Consider mobile** - Widgets should be responsive

## Examples

See the [examples/ui](../../examples/ui) directory for complete working examples:

- `weather-widget-server.php` - Interactive weather widgets
- `dashboard-server.php` - Dashboard with templates
- `ui-aware-client.php` - Client consuming UI resources

## Related Resources

- [MCP-UI GitHub](https://github.com/idosal/mcp-ui)
- [MCP Apps Extension (SEP-1865)](https://blog.modelcontextprotocol.io/posts/2025-11-21-mcp-apps/)
- [Examples README](../../examples/ui/README.md)

