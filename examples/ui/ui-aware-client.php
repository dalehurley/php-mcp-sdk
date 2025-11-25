#!/usr/bin/env php
<?php

/**
 * UI-Aware MCP Client Example
 *
 * Demonstrates how to use UIResourceClient to call tools and
 * handle UI resource responses.
 *
 * Run the weather server first:
 *   php examples/ui/weather-widget-server.php
 *
 * Then in another terminal:
 *   php examples/ui/ui-aware-client.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use MCP\UI\UIResourceClient;
use MCP\UI\UIResourceParser;
use MCP\UI\UIResourceRenderer;

use function Amp\async;

// Create the client
$client = new Client(
    new Implementation('ui-aware-client', '1.0.0')
);

// Connect to the weather server
$transport = new StdioClientTransport([
    'command' => 'php',
    'args' => [__DIR__ . '/weather-widget-server.php']
]);

async(function () use ($client, $transport) {
    try {
        // Connect to server
        yield $client->connect($transport);

        fwrite(STDERR, "✅ Connected to weather server\n\n");

        // Create UI-aware client wrapper
        $uiClient = new UIResourceClient($client);

        // =========================================================
        // Example 1: Call tool and get parsed response
        // =========================================================
        fwrite(STDERR, "📍 Example 1: Calling get_weather_widget...\n");

        $result = $uiClient->callToolWithUI('get_weather_widget', [
            'city' => 'Sydney',
            'units' => 'celsius'
        ]);

        fwrite(STDERR, "📝 Text Response:\n");
        fwrite(STDERR, "   " . $result['text'] . "\n\n");

        fwrite(STDERR, "🎨 UI Resources: " . count($result['ui']) . " found\n");
        foreach ($result['ui'] as $resource) {
            fwrite(STDERR, "   - URI: {$resource->uri}\n");
            fwrite(STDERR, "   - Type: {$resource->type}\n");
            fwrite(STDERR, "   - Size: {$resource->getContentLength()} bytes\n");
        }
        fwrite(STDERR, "\n");

        // =========================================================
        // Example 2: Get JSON for frontend consumption
        // =========================================================
        fwrite(STDERR, "📍 Example 2: Getting response for frontend...\n");

        $frontendData = $uiClient->callToolForFrontend('get_weather_widget', [
            'city' => 'Melbourne'
        ]);

        fwrite(STDERR, "📦 JSON Response:\n");
        fwrite(STDERR, json_encode($frontendData, JSON_PRETTY_PRINT) . "\n\n");

        // =========================================================
        // Example 3: Render UI as HTML
        // =========================================================
        fwrite(STDERR, "📍 Example 3: Rendering UI as HTML...\n");

        $rendered = $uiClient->callToolWithRenderedUI('get_weather_widget', [
            'city' => 'Brisbane'
        ], [
            'width' => '100%',
            'height' => '500px'
        ]);

        fwrite(STDERR, "🖼️ Rendered HTML length: " . strlen($rendered['html']) . " bytes\n");
        fwrite(STDERR, "   (Use this HTML to embed the widget in your web page)\n\n");

        // =========================================================
        // Example 4: Check if tool has UI without full parsing
        // =========================================================
        fwrite(STDERR, "📍 Example 4: Checking for UI resources...\n");

        $hasUI = $uiClient->toolHasUI('get_weather_widget', ['city' => 'Perth']);
        fwrite(STDERR, "   Tool has UI: " . ($hasUI ? 'Yes' : 'No') . "\n\n");

        // =========================================================
        // Example 5: Get only text (ignore UI)
        // =========================================================
        fwrite(STDERR, "📍 Example 5: Getting text-only response...\n");

        $textOnly = $uiClient->callToolTextOnly('get_weather_widget', [
            'city' => 'Adelaide'
        ]);

        fwrite(STDERR, "📝 Text only: {$textOnly}\n\n");

        // =========================================================
        // Example 6: Multi-city comparison
        // =========================================================
        fwrite(STDERR, "📍 Example 6: Comparing multiple cities...\n");

        $comparison = $uiClient->callToolWithUI('compare_weather', [
            'cities' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth']
        ]);

        fwrite(STDERR, "📝 Comparison text:\n{$comparison['text']}\n\n");
        fwrite(STDERR, "🎨 UI Resources: " . count($comparison['ui']) . "\n\n");

        // =========================================================
        // Example 7: Using raw parser directly
        // =========================================================
        fwrite(STDERR, "📍 Example 7: Using UIResourceParser directly...\n");

        $rawResponse = yield $client->callTool('get_weather_widget', ['city' => 'Darwin']);

        $count = UIResourceParser::countUIResources($rawResponse);
        fwrite(STDERR, "   UI resource count: {$count}\n");

        $textContent = UIResourceParser::getTextOnly($rawResponse);
        fwrite(STDERR, "   Text content: {$textContent}\n\n");

        // =========================================================
        // Example 8: Generate complete HTML page with action handler
        // =========================================================
        fwrite(STDERR, "📍 Example 8: Generating complete HTML page...\n");

        $fullResult = $uiClient->callToolWithRenderedUI('get_weather_widget', [
            'city' => 'Hobart'
        ]);

        // Get styles and action handler
        $styles = UIResourceRenderer::styles();
        $actionHandler = UIResourceRenderer::actionHandlerScript([
            'endpoint' => '/api/mcp/action',
            'debug' => true
        ]);

        $completePage = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <title>Weather Widget Demo</title>
            {$styles}
        </head>
        <body>
            <h1>Weather Widget</h1>
            {$fullResult['html']}
            {$actionHandler}
        </body>
        </html>
        HTML;

        fwrite(STDERR, "   Complete page ready for serving\n");
        fwrite(STDERR, "   Styles: " . strlen($styles) . " bytes\n");
        fwrite(STDERR, "   Action handler: " . strlen($actionHandler) . " bytes\n\n");

        fwrite(STDERR, "✅ All examples completed!\n");

        yield $client->close();

    } catch (\Exception $error) {
        fwrite(STDERR, "❌ Error: " . $error->getMessage() . "\n");
        fwrite(STDERR, $error->getTraceAsString() . "\n");
    }
})->await();

