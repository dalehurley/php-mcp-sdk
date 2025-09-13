<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Model Context Protocol server instance.
    | This defines how your Laravel application exposes MCP capabilities.
    |
    */

    'server' => [
        'name' => env('MCP_SERVER_NAME', 'laravel-mcp-server'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'transport' => env('MCP_SERVER_TRANSPORT', 'http'),

        // Auto-discovery settings for tools, resources, and prompts
        'auto_discover' => [
            'enabled' => env('MCP_AUTO_DISCOVER', true),
            
            // Namespaces to scan for MCP components
            'namespaces' => [
                'App\\Mcp\\Tools' => 'tools',
                'App\\Mcp\\Resources' => 'resources',
                'App\\Mcp\\Prompts' => 'prompts',
            ],

            // Additional directories to scan (relative to app_path)
            'directories' => [
                // 'Services/Mcp' => 'mixed',
            ],
        ],

        // Built-in Laravel tools to register
        'builtin_tools' => [
            'cache' => env('MCP_ENABLE_CACHE_TOOL', true),
            'database' => env('MCP_ENABLE_DATABASE_TOOL', true),
            'artisan' => env('MCP_ENABLE_ARTISAN_TOOL', true),
            'storage' => env('MCP_ENABLE_STORAGE_TOOL', true),
            'queue' => env('MCP_ENABLE_QUEUE_TOOL', true),
            'log' => env('MCP_ENABLE_LOG_TOOL', true),
        ],

        // Server capabilities
        'capabilities' => [
            'experimental' => [],
            'sampling' => [],
            'roots' => [
                'listChanged' => true,
            ],
            'logging' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MCP client instances when your Laravel app
    | needs to connect to other MCP servers.
    |
    */

    'client' => [
        'name' => env('MCP_CLIENT_NAME', 'laravel-mcp-client'),
        'version' => env('MCP_CLIENT_VERSION', '1.0.0'),
        
        // Default client options
        'options' => [
            'capabilities' => [
                'experimental' => [],
                'sampling' => [],
                'roots' => ['listChanged' => true],
            ],
        ],

        // Connection timeout in milliseconds
        'timeout' => env('MCP_CLIENT_TIMEOUT', 10000),

        // Retry configuration
        'retry' => [
            'enabled' => true,
            'max_attempts' => 3,
            'delay_ms' => 1000,
            'backoff_multiplier' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP routes exposed by the MCP server.
    |
    */

    'routes' => [
        'enabled' => env('MCP_ROUTES_ENABLED', true),
        'prefix' => env('MCP_ROUTE_PREFIX', 'mcp'),
        'domain' => env('MCP_ROUTE_DOMAIN', null),
        
        // Middleware applied to all MCP routes
        'middleware' => ['api'],
        
        // Middleware for authenticated routes
        'auth_middleware' => ['mcp.auth'],
        
        // Rate limiting
        'rate_limit' => env('MCP_RATE_LIMIT', '60,1'), // 60 requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | OAuth 2.1 and authentication settings for MCP endpoints.
    |
    */

    'auth' => [
        'enabled' => env('MCP_AUTH_ENABLED', false),
        'guard' => env('MCP_AUTH_GUARD', 'api'),
        
        // OAuth provider configuration
        'provider_class' => null, // Custom provider class
        
        // OAuth endpoints
        'endpoints' => [
            'authorize' => '/oauth/authorize',
            'token' => '/oauth/token',
            'revoke' => '/oauth/revoke',
            'metadata' => '/.well-known/oauth-authorization-server',
        ],

        // Token settings
        'tokens' => [
            'access_lifetime' => 3600, // 1 hour
            'refresh_lifetime' => 86400 * 30, // 30 days
            'storage_driver' => env('MCP_TOKEN_STORAGE', 'cache'), // cache, database, redis
        ],

        // PKCE settings
        'pkce' => [
            'enabled' => true,
            'required' => env('MCP_PKCE_REQUIRED', false),
            'code_length' => 128,
        ],

        // Scopes
        'scopes' => [
            'mcp:tools' => 'Access to MCP tools',
            'mcp:resources' => 'Access to MCP resources',
            'mcp:prompts' => 'Access to MCP prompts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transport Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different MCP transport layers.
    |
    */

    'transports' => [
        'stdio' => [
            'enabled' => env('MCP_STDIO_ENABLED', true),
            'buffer_size' => 8192,
            'timeout' => 30,
        ],

        'http' => [
            'enabled' => env('MCP_HTTP_ENABLED', true),
            'port' => env('MCP_HTTP_PORT', 3000),
            
            // Session management
            'session' => [
                'enabled' => true,
                'driver' => env('MCP_SESSION_DRIVER', 'cache'), // cache, database, redis
                'lifetime' => env('MCP_SESSION_LIFETIME', 3600), // 1 hour
                'cleanup_probability' => 0.02, // 2% chance to run cleanup
            ],

            // Security settings
            'security' => [
                'dns_rebinding_protection' => env('MCP_DNS_PROTECTION', true),
                'allowed_hosts' => array_filter(explode(',', env('MCP_ALLOWED_HOSTS', 'localhost,127.0.0.1'))),
                'max_request_size' => env('MCP_MAX_REQUEST_SIZE', 10 * 1024 * 1024), // 10MB
                'request_timeout' => env('MCP_REQUEST_TIMEOUT', 30), // seconds
            ],

            // SSE (Server-Sent Events) configuration
            'sse' => [
                'enabled' => env('MCP_SSE_ENABLED', true),
                'keepalive_interval' => 30, // seconds
                'max_connections' => env('MCP_SSE_MAX_CONNECTIONS', 100),
                'buffer_size' => 8192,
            ],
        ],

        'websocket' => [
            'enabled' => env('MCP_WEBSOCKET_ENABLED', false),
            'port' => env('MCP_WEBSOCKET_PORT', 3001),
            'heartbeat_interval' => 30,
            'max_connections' => env('MCP_WEBSOCKET_MAX_CONNECTIONS', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Caching settings for MCP operations to improve performance.
    |
    */

    'cache' => [
        'enabled' => env('MCP_CACHE_ENABLED', true),
        'store' => env('MCP_CACHE_STORE', config('cache.default')),
        'prefix' => env('MCP_CACHE_PREFIX', 'mcp:'),
        'ttl' => [
            'tools' => env('MCP_CACHE_TOOLS_TTL', 300), // 5 minutes
            'resources' => env('MCP_CACHE_RESOURCES_TTL', 60), // 1 minute
            'prompts' => env('MCP_CACHE_PROMPTS_TTL', 300), // 5 minutes
            'schemas' => env('MCP_CACHE_SCHEMAS_TTL', 3600), // 1 hour
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Logging settings for MCP operations and debugging.
    |
    */

    'logging' => [
        'enabled' => env('MCP_LOGGING_ENABLED', true),
        'channel' => env('MCP_LOG_CHANNEL', config('logging.default')),
        'level' => env('MCP_LOG_LEVEL', 'info'),
        
        // Log specific events
        'log_requests' => env('MCP_LOG_REQUESTS', false),
        'log_responses' => env('MCP_LOG_RESPONSES', false),
        'log_errors' => env('MCP_LOG_ERRORS', true),
        'log_performance' => env('MCP_LOG_PERFORMANCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for asynchronous MCP operations.
    |
    */

    'queue' => [
        'enabled' => env('MCP_QUEUE_ENABLED', true),
        'connection' => env('MCP_QUEUE_CONNECTION', config('queue.default')),
        'queue' => env('MCP_QUEUE_NAME', 'mcp'),
        
        // Background processing
        'background_tools' => env('MCP_BACKGROUND_TOOLS', false),
        'timeout' => env('MCP_QUEUE_TIMEOUT', 300), // 5 minutes
        'retry_after' => env('MCP_QUEUE_RETRY_AFTER', 90), // 1.5 minutes
        'max_tries' => env('MCP_QUEUE_MAX_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for development and debugging.
    |
    */

    'development' => [
        'debug' => env('MCP_DEBUG', config('app.debug')),
        'hot_reload' => env('MCP_HOT_RELOAD', false),
        'profiling' => env('MCP_PROFILING', false),
        
        // Mock services for testing
        'mock_external_apis' => env('MCP_MOCK_APIS', false),
        'fake_delays' => env('MCP_FAKE_DELAYS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for web-based MCP interfaces and components.
    |
    */

    'ui' => [
        'enabled' => env('MCP_UI_ENABLED', true),
        'theme' => env('MCP_UI_THEME', 'light'), // light, dark, auto
        'components' => [
            'dashboard' => env('MCP_UI_DASHBOARD', true),
            'inspector' => env('MCP_UI_INSPECTOR', true),
            'monitor' => env('MCP_UI_MONITOR', true),
            'tester' => env('MCP_UI_TESTER', true),
        ],
        
        // Real-time updates
        'realtime' => [
            'enabled' => env('MCP_UI_REALTIME', true),
            'driver' => env('MCP_UI_REALTIME_DRIVER', 'sse'), // sse, websocket, polling
            'interval' => env('MCP_UI_POLL_INTERVAL', 2000), // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for optimizing MCP performance in production.
    |
    */

    'performance' => [
        'async_enabled' => env('MCP_ASYNC_ENABLED', true),
        'concurrent_requests' => env('MCP_CONCURRENT_REQUESTS', 10),
        'memory_limit' => env('MCP_MEMORY_LIMIT', '256M'),
        'execution_time_limit' => env('MCP_EXECUTION_TIME_LIMIT', 60), // seconds
        
        // Connection pooling
        'connection_pool' => [
            'enabled' => env('MCP_CONNECTION_POOL', true),
            'max_connections' => env('MCP_POOL_MAX_CONNECTIONS', 50),
            'idle_timeout' => env('MCP_POOL_IDLE_TIMEOUT', 300), // seconds
        ],
    ],
];