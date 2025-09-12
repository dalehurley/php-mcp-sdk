#!/usr/bin/env php
<?php

/**
 * Weather Server Example
 * 
 * This example demonstrates how to create an MCP server that:
 * - Calls external APIs (weather service)
 * - Implements caching for API responses
 * - Handles errors gracefully
 * - Provides tools for weather data retrieval
 * - Manages rate limiting
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load required files to ensure all classes are available
require_once __DIR__ . '/../../src/Shared/Protocol.php';
require_once __DIR__ . '/../../src/Server/RegisteredItems.php';
require_once __DIR__ . '/../../src/Server/ResourceTemplate.php';
require_once __DIR__ . '/../../src/Server/Server.php';

use MCP\Server\McpServer;
use MCP\Server\ServerOptions;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Content\TextContent;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use function Amp\async;

// Create the server
$server = new McpServer(
    new Implementation('weather-server', '1.0.0', 'Weather Information Server'),
    new ServerOptions(
        capabilities: new ServerCapabilities(
            tools: ['listChanged' => true]
        ),
        instructions: "This server provides weather information using external APIs with caching and rate limiting."
    )
);

// Configuration
$config = [
    'api_key' => $_ENV['OPENWEATHER_API_KEY'] ?? 'demo_key',
    'base_url' => 'https://api.openweathermap.org/data/2.5',
    'cache_ttl' => 600, // 10 minutes
    'rate_limit' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000
    ]
];

// In-memory cache and rate limiting
$cache = [];
$rateLimitTracker = [
    'minute' => ['count' => 0, 'reset' => time() + 60],
    'hour' => ['count' => 0, 'reset' => time() + 3600]
];

// HTTP client for API calls
$httpClient = new HttpClient([
    'timeout' => 10,
    'headers' => [
        'User-Agent' => 'PHP-MCP-Weather-Server/1.0.0'
    ]
]);

/**
 * Check rate limits
 */
function checkRateLimit(array &$rateLimitTracker, array $config): bool
{
    $now = time();

    // Reset counters if time windows have passed
    if ($now >= $rateLimitTracker['minute']['reset']) {
        $rateLimitTracker['minute'] = ['count' => 0, 'reset' => $now + 60];
    }
    if ($now >= $rateLimitTracker['hour']['reset']) {
        $rateLimitTracker['hour'] = ['count' => 0, 'reset' => $now + 3600];
    }

    // Check limits
    if ($rateLimitTracker['minute']['count'] >= $config['rate_limit']['requests_per_minute']) {
        return false;
    }
    if ($rateLimitTracker['hour']['count'] >= $config['rate_limit']['requests_per_hour']) {
        return false;
    }

    return true;
}

/**
 * Increment rate limit counters
 */
function incrementRateLimit(array &$rateLimitTracker): void
{
    $rateLimitTracker['minute']['count']++;
    $rateLimitTracker['hour']['count']++;
}

/**
 * Get cached data or null if expired/missing
 */
function getCached(array &$cache, string $key, int $ttl): ?array
{
    if (!isset($cache[$key])) {
        return null;
    }

    $item = $cache[$key];
    if (time() - $item['timestamp'] > $ttl) {
        unset($cache[$key]);
        return null;
    }

    return $item['data'];
}

/**
 * Store data in cache
 */
function setCache(array &$cache, string $key, array $data): void
{
    $cache[$key] = [
        'data' => $data,
        'timestamp' => time()
    ];
}

/**
 * Make API request with error handling
 */
function makeWeatherApiRequest(HttpClient $httpClient, string $url): array
{
    try {
        $response = $httpClient->get($url);
        $data = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from weather API');
        }

        // Check for API error response
        if (isset($data['cod']) && $data['cod'] !== 200) {
            throw new \Exception($data['message'] ?? 'Weather API error');
        }

        return $data;
    } catch (RequestException $e) {
        if ($e->hasResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();
            throw new \Exception("Weather API request failed with status $statusCode: " . $e->getMessage());
        } else {
            throw new \Exception("Weather API request failed: " . $e->getMessage());
        }
    }
}

// Register current weather tool
$server->tool(
    'current-weather',
    'Get current weather for a location',
    [
        'location' => [
            'type' => 'string',
            'description' => 'City name, state/country code (e.g., "London,UK" or "New York,NY,US")'
        ],
        'units' => [
            'type' => 'string',
            'description' => 'Temperature units',
            'enum' => ['metric', 'imperial', 'kelvin'],
            'default' => 'metric'
        ]
    ],
    function (array $args) use ($httpClient, $config, &$cache, &$rateLimitTracker) {
        $location = $args['location'] ?? '';
        $units = $args['units'] ?? 'metric';

        if (empty($location)) {
            return new CallToolResult(
                content: [new TextContent('Location is required')],
                isError: true
            );
        }

        // Check rate limits
        if (!checkRateLimit($rateLimitTracker, $config)) {
            return new CallToolResult(
                content: [new TextContent('Rate limit exceeded. Please try again later.')],
                isError: true
            );
        }

        // Check cache first
        $cacheKey = "current:$location:$units";
        $cachedData = getCached($cache, $cacheKey, $config['cache_ttl']);

        if ($cachedData !== null) {
            $weatherInfo = formatWeatherData($cachedData, $units, true);
            return new CallToolResult(
                content: [new TextContent($weatherInfo)]
            );
        }

        try {
            // Make API request
            $url = $config['base_url'] . '/weather?' . http_build_query([
                'q' => $location,
                'appid' => $config['api_key'],
                'units' => $units
            ]);

            $data = makeWeatherApiRequest($httpClient, $url);
            incrementRateLimit($rateLimitTracker);

            // Cache the response
            setCache($cache, $cacheKey, $data);

            $weatherInfo = formatWeatherData($data, $units, false);
            return new CallToolResult(
                content: [new TextContent($weatherInfo)]
            );
        } catch (\Exception $e) {
            return new CallToolResult(
                content: [new TextContent('Weather data unavailable: ' . $e->getMessage())],
                isError: true
            );
        }
    }
);

// Register weather forecast tool
$server->tool(
    'weather-forecast',
    'Get 5-day weather forecast for a location',
    [
        'location' => [
            'type' => 'string',
            'description' => 'City name, state/country code'
        ],
        'units' => [
            'type' => 'string',
            'description' => 'Temperature units',
            'enum' => ['metric', 'imperial', 'kelvin'],
            'default' => 'metric'
        ],
        'days' => [
            'type' => 'integer',
            'description' => 'Number of days (1-5)',
            'minimum' => 1,
            'maximum' => 5,
            'default' => 3
        ]
    ],
    function (array $args) use ($httpClient, $config, &$cache, &$rateLimitTracker) {
        $location = $args['location'] ?? '';
        $units = $args['units'] ?? 'metric';
        $days = min(5, max(1, $args['days'] ?? 3));

        if (empty($location)) {
            return new CallToolResult(
                content: [new TextContent('Location is required')],
                isError: true
            );
        }

        // Check rate limits
        if (!checkRateLimit($rateLimitTracker, $config)) {
            return new CallToolResult(
                content: [new TextContent('Rate limit exceeded. Please try again later.')],
                isError: true
            );
        }

        // Check cache
        $cacheKey = "forecast:$location:$units:$days";
        $cachedData = getCached($cache, $cacheKey, $config['cache_ttl']);

        if ($cachedData !== null) {
            $forecastInfo = formatForecastData($cachedData, $units, $days, true);
            return new CallToolResult(
                content: [new TextContent($forecastInfo)]
            );
        }

        try {
            $url = $config['base_url'] . '/forecast?' . http_build_query([
                'q' => $location,
                'appid' => $config['api_key'],
                'units' => $units,
                'cnt' => $days * 8 // API returns 3-hour intervals, so 8 per day
            ]);

            $data = makeWeatherApiRequest($httpClient, $url);
            incrementRateLimit($rateLimitTracker);

            setCache($cache, $cacheKey, $data);

            $forecastInfo = formatForecastData($data, $units, $days, false);
            return new CallToolResult(
                content: [new TextContent($forecastInfo)]
            );
        } catch (\Exception $e) {
            return new CallToolResult(
                content: [new TextContent('Forecast data unavailable: ' . $e->getMessage())],
                isError: true
            );
        }
    }
);

// Register weather alerts tool
$server->tool(
    'weather-alerts',
    'Get weather alerts for a location (if available)',
    [
        'location' => [
            'type' => 'string',
            'description' => 'City name, state/country code'
        ]
    ],
    function (array $args) use ($httpClient, $config, &$cache, &$rateLimitTracker) {
        $location = $args['location'] ?? '';

        if (empty($location)) {
            return new CallToolResult(
                content: [new TextContent('Location is required')],
                isError: true
            );
        }

        // Check rate limits
        if (!checkRateLimit($rateLimitTracker, $config)) {
            return new CallToolResult(
                content: [new TextContent('Rate limit exceeded. Please try again later.')],
                isError: true
            );
        }

        // For demo purposes, return mock alert data
        // In a real implementation, this would call a weather alerts API
        $alerts = [
            [
                'title' => 'Heat Advisory',
                'description' => 'Excessive heat warning in effect until 8 PM',
                'severity' => 'moderate',
                'start' => date('c'),
                'end' => date('c', time() + 8 * 3600)
            ]
        ];

        if (empty($alerts)) {
            return new CallToolResult(
                content: [new TextContent("No weather alerts currently active for $location")]
            );
        }

        $alertText = "Weather Alerts for $location:\n\n";
        foreach ($alerts as $alert) {
            $alertText .= "🚨 {$alert['title']} ({$alert['severity']})\n";
            $alertText .= "   {$alert['description']}\n";
            $alertText .= "   Active: " . date('M j, Y g:i A', strtotime($alert['start']));
            $alertText .= " - " . date('M j, Y g:i A', strtotime($alert['end'])) . "\n\n";
        }

        return new CallToolResult(
            content: [new TextContent($alertText)]
        );
    }
);

// Register cache status tool
$server->tool(
    'cache-status',
    'Get information about the weather data cache',
    [],
    function (array $args) use (&$cache, &$rateLimitTracker, $config) {
        $now = time();
        $cacheInfo = [
            'total_entries' => count($cache),
            'entries' => []
        ];

        foreach ($cache as $key => $item) {
            $age = $now - $item['timestamp'];
            $ttl = $config['cache_ttl'];
            $cacheInfo['entries'][] = [
                'key' => $key,
                'age_seconds' => $age,
                'expires_in_seconds' => max(0, $ttl - $age),
                'is_expired' => $age > $ttl
            ];
        }

        $rateLimitInfo = [
            'minute' => [
                'used' => $rateLimitTracker['minute']['count'],
                'limit' => $config['rate_limit']['requests_per_minute'],
                'resets_in' => max(0, $rateLimitTracker['minute']['reset'] - $now)
            ],
            'hour' => [
                'used' => $rateLimitTracker['hour']['count'],
                'limit' => $config['rate_limit']['requests_per_hour'],
                'resets_in' => max(0, $rateLimitTracker['hour']['reset'] - $now)
            ]
        ];

        $status = [
            'cache' => $cacheInfo,
            'rate_limits' => $rateLimitInfo,
            'config' => [
                'cache_ttl_seconds' => $config['cache_ttl'],
                'api_configured' => !empty($config['api_key']) && $config['api_key'] !== 'demo_key'
            ]
        ];

        return new CallToolResult(
            content: [new TextContent("Cache Status:\n" . json_encode($status, JSON_PRETTY_PRINT))]
        );
    }
);

/**
 * Format current weather data for display
 */
function formatWeatherData(array $data, string $units, bool $fromCache): string
{
    $location = $data['name'] . ', ' . $data['sys']['country'];
    $temp = round($data['main']['temp']);
    $feelsLike = round($data['main']['feels_like']);
    $humidity = $data['main']['humidity'];
    $description = ucfirst($data['weather'][0]['description']);

    $unitSymbol = match ($units) {
        'imperial' => '°F',
        'kelvin' => 'K',
        default => '°C'
    };

    $cacheNote = $fromCache ? " (cached)" : "";

    $weather = "🌤️ Current Weather for $location$cacheNote\n\n";
    $weather .= "Temperature: $temp$unitSymbol (feels like $feelsLike$unitSymbol)\n";
    $weather .= "Conditions: $description\n";
    $weather .= "Humidity: $humidity%\n";

    if (isset($data['wind']['speed'])) {
        $windSpeed = $data['wind']['speed'];
        $windUnit = $units === 'imperial' ? 'mph' : 'm/s';
        $weather .= "Wind: $windSpeed $windUnit";

        if (isset($data['wind']['deg'])) {
            $direction = getWindDirection($data['wind']['deg']);
            $weather .= " $direction";
        }
        $weather .= "\n";
    }

    if (isset($data['visibility'])) {
        $visibility = $data['visibility'] / 1000; // Convert to km
        $weather .= "Visibility: " . round($visibility, 1) . " km\n";
    }

    $weather .= "\nLast updated: " . date('M j, Y g:i A', $data['dt']);

    return $weather;
}

/**
 * Format forecast data for display
 */
function formatForecastData(array $data, string $units, int $days, bool $fromCache): string
{
    $location = $data['city']['name'] . ', ' . $data['city']['country'];
    $unitSymbol = match ($units) {
        'imperial' => '°F',
        'kelvin' => 'K',
        default => '°C'
    };

    $cacheNote = $fromCache ? " (cached)" : "";

    $forecast = "📅 {$days}-Day Weather Forecast for $location$cacheNote\n\n";

    // Group forecasts by day
    $dailyForecasts = [];
    foreach ($data['list'] as $item) {
        $date = date('Y-m-d', $item['dt']);
        if (!isset($dailyForecasts[$date])) {
            $dailyForecasts[$date] = [];
        }
        $dailyForecasts[$date][] = $item;
    }

    $dayCount = 0;
    foreach ($dailyForecasts as $date => $dayData) {
        if ($dayCount >= $days) break;

        $dayName = date('l, M j', strtotime($date));
        $minTemp = min(array_column($dayData, 'main.temp_min'));
        $maxTemp = max(array_column($dayData, 'main.temp_max'));

        // Get the most common weather condition for the day
        $conditions = array_column($dayData, 'weather.0.description');
        $mainCondition = array_count_values($conditions);
        arsort($mainCondition);
        $condition = ucfirst(key($mainCondition));

        $forecast .= "🗓️ $dayName\n";
        $forecast .= "   " . round($minTemp) . "$unitSymbol - " . round($maxTemp) . "$unitSymbol\n";
        $forecast .= "   $condition\n\n";

        $dayCount++;
    }

    return $forecast;
}

/**
 * Convert wind degree to direction
 */
function getWindDirection(int $degrees): string
{
    $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
    $index = round($degrees / 22.5) % 16;
    return $directions[$index];
}

// Set up the transport and start the server
async(function () use ($server, $config) {
    try {
        $transport = new StdioServerTransport();

        echo "Starting Weather Server on stdio...\n";

        if ($config['api_key'] === 'demo_key') {
            echo "⚠️  Warning: Using demo API key. Set OPENWEATHER_API_KEY environment variable for real data.\n";
        } else {
            echo "✅ Weather API configured\n";
        }

        echo "Cache TTL: {$config['cache_ttl']} seconds\n";
        echo "Rate limits: {$config['rate_limit']['requests_per_minute']}/min, {$config['rate_limit']['requests_per_hour']}/hour\n\n";

        $server->connect($transport)->await();
    } catch (\Throwable $e) {
        error_log("Server error: " . $e->getMessage());
        exit(1);
    }
})->await();
