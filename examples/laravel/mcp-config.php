<?php

declare(strict_types=1);

/**
 * MCP (Model Context Protocol) Configuration for Laravel
 *
 * This configuration file shows how to configure the core PHP MCP SDK
 * when using it directly in a Laravel application.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Server Information
    |--------------------------------------------------------------------------
    |
    | Basic information about your MCP server implementation.
    |
    */
    'server' => [
        'name' => env('MCP_SERVER_NAME', config('app.name', 'Laravel MCP Server')),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'description' => env('MCP_SERVER_DESCRIPTION', 'Laravel application with MCP integration'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transport Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how your MCP server communicates. The core SDK supports
    | STDIO and HTTP transports.
    |
    */
    'transport' => [
        'default' => env('MCP_TRANSPORT', 'http'),

        'stdio' => [
            'enabled' => env('MCP_STDIO_ENABLED', true),
        ],

        'http' => [
            'enabled' => env('MCP_HTTP_ENABLED', true),
            'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
            'port' => env('MCP_HTTP_PORT', 3000),
            'path' => env('MCP_HTTP_PATH', '/mcp'),
            'cors' => [
                'enabled' => env('MCP_HTTP_CORS_ENABLED', true),
                'origins' => env('MCP_HTTP_CORS_ORIGINS', '*'),
                'methods' => ['GET', 'POST', 'OPTIONS'],
                'headers' => ['Content-Type', 'Authorization'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configure authentication for your MCP server when using HTTP transport.
    |
    */
    'auth' => [
        'enabled' => env('MCP_AUTH_ENABLED', false),
        'method' => env('MCP_AUTH_METHOD', 'bearer'), // bearer, basic, custom

        'bearer' => [
            'token' => env('MCP_AUTH_BEARER_TOKEN'),
        ],

        'basic' => [
            'username' => env('MCP_AUTH_BASIC_USERNAME'),
            'password' => env('MCP_AUTH_BASIC_PASSWORD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which tools are available in your MCP server.
    | This is just for reference - actual tool registration happens in code.
    |
    */
    'tools' => [
        'user_search' => [
            'enabled' => env('MCP_TOOL_USER_SEARCH_ENABLED', true),
            'description' => 'Search for users by name or email',
            'max_results' => env('MCP_TOOL_USER_SEARCH_MAX_RESULTS', 50),
        ],

        'cache_operations' => [
            'enabled' => env('MCP_TOOL_CACHE_ENABLED', true),
            'description' => 'Perform Laravel cache operations',
            'allowed_operations' => ['get', 'put', 'forget'], // exclude 'flush' for security
        ],

        'database_query' => [
            'enabled' => env('MCP_TOOL_DATABASE_ENABLED', false),
            'description' => 'Execute safe database queries',
            'allowed_tables' => ['users', 'posts'], // whitelist tables
            'max_results' => env('MCP_TOOL_DATABASE_MAX_RESULTS', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which resources are exposed by your MCP server.
    |
    */
    'resources' => [
        'users' => [
            'enabled' => env('MCP_RESOURCE_USERS_ENABLED', true),
            'description' => 'User data resource',
            'cache_ttl' => env('MCP_RESOURCE_USERS_CACHE_TTL', 300), // 5 minutes
        ],

        'posts' => [
            'enabled' => env('MCP_RESOURCE_POSTS_ENABLED', true),
            'description' => 'Blog posts resource',
            'cache_ttl' => env('MCP_RESOURCE_POSTS_CACHE_TTL', 600), // 10 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for MCP operations.
    |
    */
    'logging' => [
        'enabled' => env('MCP_LOGGING_ENABLED', true),
        'channel' => env('MCP_LOGGING_CHANNEL', 'default'),
        'level' => env('MCP_LOGGING_LEVEL', 'info'),
        'log_requests' => env('MCP_LOG_REQUESTS', true),
        'log_responses' => env('MCP_LOG_RESPONSES', false), // may contain sensitive data
        'log_errors' => env('MCP_LOG_ERRORS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings.
    |
    */
    'performance' => [
        'cache_enabled' => env('MCP_CACHE_ENABLED', true),
        'cache_ttl' => env('MCP_CACHE_TTL', 300), // 5 minutes default
        'rate_limiting' => [
            'enabled' => env('MCP_RATE_LIMITING_ENABLED', true),
            'max_requests' => env('MCP_RATE_LIMIT_REQUESTS', 100),
            'per_minutes' => env('MCP_RATE_LIMIT_MINUTES', 1),
        ],
        'timeout' => [
            'tool_execution' => env('MCP_TOOL_TIMEOUT', 30), // seconds
            'resource_fetch' => env('MCP_RESOURCE_TIMEOUT', 10), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security settings for your MCP server.
    |
    */
    'security' => [
        'validate_schemas' => env('MCP_VALIDATE_SCHEMAS', true),
        'sanitize_output' => env('MCP_SANITIZE_OUTPUT', true),
        'allowed_hosts' => env('MCP_ALLOWED_HOSTS', '*'), // comma-separated list or '*'
        'max_request_size' => env('MCP_MAX_REQUEST_SIZE', 1024 * 1024), // 1MB
        'require_https' => env('MCP_REQUIRE_HTTPS', false),
    ],
];
