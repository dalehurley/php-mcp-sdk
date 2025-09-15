#!/usr/bin/env php
<?php

/**
 * Weather Client Example.
 *
 * Demonstrates how to create a client that connects to external APIs
 * and provides weather information through MCP tools.
 *
 * This example shows:
 * - HTTP API integration
 * - Error handling with external services
 * - Data transformation and presentation
 * - Configuration management
 *
 * Note: This is a mock example that simulates weather API calls.
 * In production, you'd integrate with real weather services like OpenWeatherMap.
 *
 * Usage:
 *   php weather-client.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;

// Create weather server (acting as a client to external APIs)
$server = new McpServer(
    new Implementation(
        name: 'weather-client',
        version: '1.0.0',
        description: 'A weather information server that demonstrates API integration'
    ),
    new StdioServerTransport()
);

// Mock weather data (in production, this would come from a real API)
$mockWeatherData = [
    'london' => [
        'city' => 'London',
        'country' => 'UK',
        'temperature' => 15,
        'condition' => 'Cloudy',
        'humidity' => 78,
        'wind_speed' => 12,
        'pressure' => 1013,
    ],
    'paris' => [
        'city' => 'Paris',
        'country' => 'France',
        'temperature' => 18,
        'condition' => 'Sunny',
        'humidity' => 65,
        'wind_speed' => 8,
        'pressure' => 1020,
    ],
    'tokyo' => [
        'city' => 'Tokyo',
        'country' => 'Japan',
        'temperature' => 22,
        'condition' => 'Partly Cloudy',
        'humidity' => 70,
        'wind_speed' => 15,
        'pressure' => 1018,
    ],
    'new york' => [
        'city' => 'New York',
        'country' => 'USA',
        'temperature' => 20,
        'condition' => 'Rainy',
        'humidity' => 85,
        'wind_speed' => 18,
        'pressure' => 1008,
    ],
];

// Tool: Get current weather
$server->addTool(
    name: 'get_weather',
    description: 'Get current weather information for a city',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'city' => [
                'type' => 'string',
                'description' => 'Name of the city to get weather for',
            ],
            'units' => [
                'type' => 'string',
                'enum' => ['celsius', 'fahrenheit'],
                'description' => 'Temperature units (default: celsius)',
                'default' => 'celsius',
            ],
        ],
        'required' => ['city'],
    ],
    handler: function (array $args) use ($mockWeatherData): array {
        $city = strtolower(trim($args['city']));
        $units = $args['units'] ?? 'celsius';

        // Simulate API call delay
        usleep(500000); // 0.5 second delay

        if (!isset($mockWeatherData[$city])) {
            throw new McpError(
                code: -32602,
                message: "Weather data not available for city: {$args['city']}. Try: London, Paris, Tokyo, or New York"
            );
        }

        $weather = $mockWeatherData[$city];

        // Convert temperature if needed
        $temperature = $weather['temperature'];
        $tempUnit = '°C';

        if ($units === 'fahrenheit') {
            $temperature = ($temperature * 9 / 5) + 32;
            $tempUnit = '°F';
        }

        $weatherReport = "🌤️ Weather Report for {$weather['city']}, {$weather['country']}\n\n";
        $weatherReport .= "🌡️ Temperature: {$temperature}{$tempUnit}\n";
        $weatherReport .= "☁️ Condition: {$weather['condition']}\n";
        $weatherReport .= "💧 Humidity: {$weather['humidity']}%\n";
        $weatherReport .= "💨 Wind Speed: {$weather['wind_speed']} km/h\n";
        $weatherReport .= "📊 Pressure: {$weather['pressure']} hPa\n";

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $weatherReport,
                ],
            ],
        ];
    }
);

// Tool: Get weather forecast (mock 5-day forecast)
$server->addTool(
    name: 'get_forecast',
    description: 'Get 5-day weather forecast for a city',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'city' => [
                'type' => 'string',
                'description' => 'Name of the city to get forecast for',
            ],
        ],
        'required' => ['city'],
    ],
    handler: function (array $args) use ($mockWeatherData): array {
        $city = strtolower(trim($args['city']));

        if (!isset($mockWeatherData[$city])) {
            throw new McpError(
                code: -32602,
                message: "Forecast data not available for city: {$args['city']}. Try: London, Paris, Tokyo, or New York"
            );
        }

        $weather = $mockWeatherData[$city];
        $baseTemp = $weather['temperature'];

        $forecast = "📅 5-Day Weather Forecast for {$weather['city']}, {$weather['country']}\n\n";

        $conditions = ['Sunny', 'Partly Cloudy', 'Cloudy', 'Rainy', 'Clear'];

        for ($i = 1; $i <= 5; $i++) {
            $date = date('M j', strtotime("+{$i} days"));
            $temp = $baseTemp + rand(-5, 5); // Random variation
            $condition = $conditions[array_rand($conditions)];

            $forecast .= "Day {$i} ({$date}): {$temp}°C - {$condition}\n";
        }

        $forecast .= "\n📝 Note: This is simulated forecast data for demonstration purposes.";

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $forecast,
                ],
            ],
        ];
    }
);

// Tool: Compare weather between cities
$server->addTool(
    name: 'compare_weather',
    description: 'Compare weather between two cities',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'city1' => [
                'type' => 'string',
                'description' => 'First city to compare',
            ],
            'city2' => [
                'type' => 'string',
                'description' => 'Second city to compare',
            ],
        ],
        'required' => ['city1', 'city2'],
    ],
    handler: function (array $args) use ($mockWeatherData): array {
        $city1 = strtolower(trim($args['city1']));
        $city2 = strtolower(trim($args['city2']));

        if (!isset($mockWeatherData[$city1])) {
            throw new McpError(
                code: -32602,
                message: "Weather data not available for {$args['city1']}"
            );
        }

        if (!isset($mockWeatherData[$city2])) {
            throw new McpError(
                code: -32602,
                message: "Weather data not available for {$args['city2']}"
            );
        }

        $weather1 = $mockWeatherData[$city1];
        $weather2 = $mockWeatherData[$city2];

        $comparison = "🏙️ Weather Comparison\n\n";

        $comparison .= "📍 {$weather1['city']}, {$weather1['country']}:\n";
        $comparison .= "   🌡️ {$weather1['temperature']}°C - {$weather1['condition']}\n";
        $comparison .= "   💧 {$weather1['humidity']}% humidity\n\n";

        $comparison .= "📍 {$weather2['city']}, {$weather2['country']}:\n";
        $comparison .= "   🌡️ {$weather2['temperature']}°C - {$weather2['condition']}\n";
        $comparison .= "   💧 {$weather2['humidity']}% humidity\n\n";

        $tempDiff = $weather1['temperature'] - $weather2['temperature'];
        $comparison .= "🔍 Analysis:\n";

        if ($tempDiff > 0) {
            $comparison .= "   • {$weather1['city']} is {$tempDiff}°C warmer\n";
        } elseif ($tempDiff < 0) {
            $comparison .= "   • {$weather2['city']} is " . abs($tempDiff) . "°C warmer\n";
        } else {
            $comparison .= "   • Both cities have the same temperature\n";
        }

        $humidityDiff = $weather1['humidity'] - $weather2['humidity'];
        if ($humidityDiff > 0) {
            $comparison .= "   • {$weather1['city']} is {$humidityDiff}% more humid\n";
        } elseif ($humidityDiff < 0) {
            $comparison .= "   • {$weather2['city']} is " . abs($humidityDiff) . "% more humid\n";
        } else {
            $comparison .= "   • Both cities have the same humidity\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $comparison,
                ],
            ],
        ];
    }
);

// Resource: Available cities
$server->addResource(
    uri: 'weather://cities',
    name: 'Available Cities',
    description: 'List of cities with weather data available',
    mimeType: 'application/json',
    handler: function () use ($mockWeatherData): string {
        $cities = [];
        foreach ($mockWeatherData as $key => $data) {
            $cities[] = [
                'key' => $key,
                'name' => $data['city'],
                'country' => $data['country'],
            ];
        }

        return json_encode([
            'available_cities' => $cities,
            'note' => 'This is mock weather data for demonstration purposes',
        ], JSON_PRETTY_PRINT);
    }
);

// Resource: API information
$server->addResource(
    uri: 'weather://api-info',
    name: 'Weather API Information',
    description: 'Information about the weather API integration',
    mimeType: 'text/plain',
    handler: function (): string {
        return "Weather API Integration Demo\n" .
            "============================\n\n" .
            "This server demonstrates how to integrate external APIs into MCP servers.\n\n" .
            "Features:\n" .
            "- Current weather data\n" .
            "- 5-day forecasts\n" .
            "- City comparisons\n" .
            "- Error handling\n" .
            "- Data transformation\n\n" .
            "In production, you would:\n" .
            "1. Register with a weather API service (OpenWeatherMap, etc.)\n" .
            "2. Store API keys securely\n" .
            "3. Handle rate limiting\n" .
            "4. Cache responses appropriately\n" .
            "5. Handle various error conditions\n\n" .
            "Available cities: London, Paris, Tokyo, New York\n";
    }
);

// Prompt: Weather help
$server->addPrompt(
    name: 'weather_help',
    description: 'Get help using the weather service',
    handler: function (): array {
        return [
            'description' => 'Weather Service Help',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I get weather information?',
                        ],
                    ],
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "The Weather Service provides comprehensive weather information:\n\n" .
                                "**Available Tools:**\n" .
                                "• **get_weather** - Current weather for a city\n" .
                                "  Example: get_weather({\"city\": \"London\", \"units\": \"celsius\"})\n\n" .
                                "• **get_forecast** - 5-day weather forecast\n" .
                                "  Example: get_forecast({\"city\": \"Paris\"})\n\n" .
                                "• **compare_weather** - Compare weather between cities\n" .
                                "  Example: compare_weather({\"city1\": \"Tokyo\", \"city2\": \"New York\"})\n\n" .
                                "**Available Cities:**\n" .
                                "London, Paris, Tokyo, New York\n\n" .
                                "**Temperature Units:**\n" .
                                "• celsius (default)\n" .
                                "• fahrenheit\n\n" .
                                "Try: 'What's the weather like in London?'",
                        ],
                    ],
                ],
            ],
        ];
    }
);

// Start the server
echo "🌤️ Weather Client MCP Server starting...\n";
echo "Available cities: London, Paris, Tokyo, New York\n";
echo "Available operations: get_weather, get_forecast, compare_weather\n" . PHP_EOL;
$server->run();
