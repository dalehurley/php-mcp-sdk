#!/usr/bin/env php
<?php

/**
 * Weather Widget MCP Server Example
 *
 * Demonstrates how to create an MCP server that returns interactive
 * UI widgets using the UIResource helper.
 *
 * Run with: php examples/ui/weather-widget-server.php
 * Test with: npx @modelcontextprotocol/inspector php examples/ui/weather-widget-server.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\UI\UIResource;

use function Amp\async;

// Create the MCP server
$server = new McpServer(
    new Implementation(
        'weather-ui-server',
        '1.0.0'
    )
);

// Weather conditions with emojis
$conditions = [
    'Sunny' => ['emoji' => '☀️', 'gradient' => '#f5af19, #f12711'],
    'Cloudy' => ['emoji' => '☁️', 'gradient' => '#bdc3c7, #2c3e50'],
    'Rainy' => ['emoji' => '🌧️', 'gradient' => '#4b79a1, #283e51'],
    'Partly Cloudy' => ['emoji' => '⛅', 'gradient' => '#56ccf2, #2f80ed'],
    'Stormy' => ['emoji' => '⛈️', 'gradient' => '#373b44, #4286f4'],
    'Snowy' => ['emoji' => '❄️', 'gradient' => '#e6dada, #274046'],
];

/**
 * Get Weather Widget Tool
 *
 * Returns an interactive weather card with current conditions
 * and a refresh button that triggers a new tool call.
 */
$server->tool(
    'get_weather_widget',
    'Get an interactive weather widget for a specified city',
    [
        'type' => 'object',
        'properties' => [
            'city' => [
                'type' => 'string',
                'description' => 'City name to get weather for'
            ],
            'units' => [
                'type' => 'string',
                'enum' => ['celsius', 'fahrenheit'],
                'description' => 'Temperature units (default: celsius)'
            ]
        ],
        'required' => ['city']
    ],
    function (array $args) use ($conditions): array {
        $city = $args['city'];
        $units = $args['units'] ?? 'celsius';

        // Mock weather data (replace with real API in production)
        $conditionKeys = array_keys($conditions);
        $condition = $conditionKeys[array_rand($conditionKeys)];
        $conditionData = $conditions[$condition];

        $tempC = rand(5, 35);
        $temp = $units === 'fahrenheit' ? round($tempC * 9 / 5 + 32) : $tempC;
        $unit = $units === 'fahrenheit' ? '°F' : '°C';

        $humidity = rand(30, 90);
        $wind = rand(5, 30);
        $feelsLike = $temp + rand(-3, 3);

        $emoji = $conditionData['emoji'];
        $gradient = $conditionData['gradient'];

        // Escape for JS
        $cityJs = addslashes($city);
        $unitsJs = addslashes($units);

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, {$gradient});
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .weather-card {
                    background: rgba(255, 255, 255, 0.95);
                    border-radius: 24px;
                    padding: 35px;
                    max-width: 340px;
                    width: 100%;
                    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
                    text-align: center;
                }
                .city-name {
                    font-size: 28px;
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 5px;
                }
                .condition {
                    font-size: 16px;
                    color: #888;
                    margin-bottom: 20px;
                }
                .weather-icon {
                    font-size: 80px;
                    margin: 15px 0;
                    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.1));
                }
                .temperature {
                    font-size: 64px;
                    font-weight: 200;
                    color: #333;
                    margin-bottom: 5px;
                }
                .feels-like {
                    font-size: 14px;
                    color: #888;
                    margin-bottom: 25px;
                }
                .details {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                    margin-bottom: 25px;
                }
                .detail-item {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 14px;
                }
                .detail-icon {
                    font-size: 20px;
                    margin-bottom: 5px;
                }
                .detail-value {
                    font-size: 20px;
                    font-weight: 600;
                    color: #333;
                }
                .detail-label {
                    font-size: 12px;
                    color: #888;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .actions {
                    display: flex;
                    gap: 10px;
                }
                .btn {
                    flex: 1;
                    padding: 14px;
                    border: none;
                    border-radius: 12px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .btn-primary {
                    background: linear-gradient(135deg, {$gradient});
                    color: white;
                }
                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
                }
                .btn-secondary {
                    background: #f0f0f0;
                    color: #555;
                }
                .btn-secondary:hover {
                    background: #e5e5e5;
                }
            </style>
        </head>
        <body>
            <div class="weather-card">
                <div class="city-name">{$city}</div>
                <div class="condition">{$condition}</div>
                <div class="weather-icon">{$emoji}</div>
                <div class="temperature">{$temp}{$unit}</div>
                <div class="feels-like">Feels like {$feelsLike}{$unit}</div>
                <div class="details">
                    <div class="detail-item">
                        <div class="detail-icon">💧</div>
                        <div class="detail-value">{$humidity}%</div>
                        <div class="detail-label">Humidity</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">💨</div>
                        <div class="detail-value">{$wind} km/h</div>
                        <div class="detail-label">Wind</div>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn btn-primary" onclick="refresh()">🔄 Refresh</button>
                    <button class="btn btn-secondary" onclick="toggleUnits()">📐 Units</button>
                </div>
            </div>
            <script>
                function refresh() {
                    window.parent.postMessage({
                        type: 'tool',
                        payload: {
                            toolName: 'get_weather_widget',
                            params: { city: '{$cityJs}', units: '{$unitsJs}' }
                        }
                    }, '*');
                }

                function toggleUnits() {
                    const newUnits = '{$units}' === 'celsius' ? 'fahrenheit' : 'celsius';
                    window.parent.postMessage({
                        type: 'tool',
                        payload: {
                            toolName: 'get_weather_widget',
                            params: { city: '{$cityJs}', units: newUnits }
                        }
                    }, '*');
                }
            </script>
        </body>
        </html>
        HTML;

        $uri = 'ui://weather/' . strtolower(preg_replace('/\s+/', '-', $city));

        return [
            'content' => [
                // Text response for non-UI clients
                [
                    'type' => 'text',
                    'text' => "Weather for {$city}: {$temp}{$unit}, {$condition} (Humidity: {$humidity}%, Wind: {$wind} km/h)"
                ],
                // UI resource for MCP-UI compatible hosts
                UIResource::html($uri, $html)
            ]
        ];
    }
);

/**
 * Get Multi-City Comparison
 *
 * Returns weather widgets for multiple cities in a comparison view.
 */
$server->tool(
    'compare_weather',
    'Compare weather across multiple cities',
    [
        'type' => 'object',
        'properties' => [
            'cities' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'List of cities to compare (max 4)'
            ]
        ],
        'required' => ['cities']
    ],
    function (array $args) use ($conditions): array {
        $cities = array_slice($args['cities'], 0, 4);

        $cardsHtml = '';
        $summaries = [];

        foreach ($cities as $city) {
            $conditionKeys = array_keys($conditions);
            $condition = $conditionKeys[array_rand($conditionKeys)];
            $temp = rand(5, 35);
            $emoji = $conditions[$condition]['emoji'];

            $cityHtml = htmlspecialchars($city);
            $summaries[] = "{$city}: {$temp}°C, {$condition}";

            $cardsHtml .= <<<HTML
            <div class="city-card">
                <div class="city-emoji">{$emoji}</div>
                <div class="city-name">{$cityHtml}</div>
                <div class="city-temp">{$temp}°C</div>
                <div class="city-condition">{$condition}</div>
            </div>
            HTML;
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    min-height: 100vh;
                    padding: 20px;
                }
                .title {
                    color: white;
                    text-align: center;
                    margin-bottom: 25px;
                    font-size: 24px;
                }
                .grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 20px;
                    max-width: 700px;
                    margin: 0 auto;
                }
                .city-card {
                    background: white;
                    border-radius: 16px;
                    padding: 25px 20px;
                    text-align: center;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                }
                .city-emoji { font-size: 48px; margin-bottom: 10px; }
                .city-name { font-size: 18px; font-weight: 600; color: #333; }
                .city-temp { font-size: 32px; font-weight: 300; color: #333; margin: 10px 0; }
                .city-condition { font-size: 14px; color: #888; }
            </style>
        </head>
        <body>
            <h1 class="title">🌍 Weather Comparison</h1>
            <div class="grid">{$cardsHtml}</div>
        </body>
        </html>
        HTML;

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Weather comparison:\n" . implode("\n", $summaries)
                ],
                UIResource::html('ui://weather/comparison', $html)
            ]
        ];
    }
);

// Start the server
async(function () use ($server) {
    fwrite(STDERR, "🌤️ Weather UI Server starting...\n");
    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();

