<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the Model Context Protocol server integration
    | with Laravel applications.
    |
    */

    'enabled' => env('MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Transport Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for HTTP-based MCP transport when serving MCP over HTTP.
    |
    */
    'http' => [
        'enabled' => env('MCP_HTTP_ENABLED', false),
        'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
        'port' => env('MCP_HTTP_PORT', 3000),
        'prefix' => env('MCP_HTTP_PREFIX', 'mcp'),
        'cors' => env('MCP_HTTP_CORS', true),
        'middleware' => ['api', 'throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | WebSocket Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for WebSocket-based MCP transport.
    |
    */
    'websocket' => [
        'enabled' => env('MCP_WEBSOCKET_ENABLED', false),
        'host' => env('MCP_WEBSOCKET_HOST', '127.0.0.1'),
        'port' => env('MCP_WEBSOCKET_PORT', 6001),
        'path' => env('MCP_WEBSOCKET_PATH', '/mcp'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication & Authorization
    |--------------------------------------------------------------------------
    |
    | Configure authentication and authorization for MCP endpoints.
    |
    */
    'auth' => [
        'enabled' => env('MCP_AUTH_ENABLED', true),
        'guard' => env('MCP_AUTH_GUARD', 'api'),
        'middleware' => ['auth:api'],

        // OAuth configuration
        'oauth' => [
            'enabled' => env('MCP_OAUTH_ENABLED', false),
            'client_id' => env('MCP_OAUTH_CLIENT_ID'),
            'client_secret' => env('MCP_OAUTH_CLIENT_SECRET'),
            'issuer' => env('MCP_OAUTH_ISSUER'),
            'scopes' => [
                'read' => 'Read access to MCP resources',
                'write' => 'Write access to MCP resources',
                'admin' => 'Administrative access'
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for MCP requests.
    |
    */
    'rate_limiting' => [
        'enabled' => env('MCP_RATE_LIMITING_ENABLED', true),
        'requests_per_minute' => env('MCP_RATE_LIMIT_PER_MINUTE', 60),
        'requests_per_hour' => env('MCP_RATE_LIMIT_PER_HOUR', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for MCP responses and resources.
    |
    */
    'cache' => [
        'enabled' => env('MCP_CACHE_ENABLED', true),
        'store' => env('MCP_CACHE_STORE', 'redis'),
        'ttl' => env('MCP_CACHE_TTL', 300), // 5 minutes
        'prefix' => env('MCP_CACHE_PREFIX', 'mcp:'),

        // Cache specific types of responses
        'cache_tools' => env('MCP_CACHE_TOOLS', false),
        'cache_resources' => env('MCP_CACHE_RESOURCES', true),
        'cache_prompts' => env('MCP_CACHE_PROMPTS', true),
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
        'channel' => env('MCP_LOG_CHANNEL', 'stack'),
        'level' => env('MCP_LOG_LEVEL', 'info'),
        'log_requests' => env('MCP_LOG_REQUESTS', true),
        'log_responses' => env('MCP_LOG_RESPONSES', false),
        'log_errors' => env('MCP_LOG_ERRORS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for MCP operations.
    |
    */
    'security' => [
        // Allowed SQL operations for database tools
        'allowed_sql_operations' => ['SELECT'],

        // Maximum query execution time
        'max_query_time' => env('MCP_MAX_QUERY_TIME', 30),

        // Maximum result set size
        'max_result_size' => env('MCP_MAX_RESULT_SIZE', 1000),

        // Allowed file extensions for file operations
        'allowed_file_extensions' => [
            'txt',
            'md',
            'json',
            'xml',
            'csv',
            'log'
        ],

        // Restricted paths for file operations
        'restricted_paths' => [
            '/etc',
            '/var/log',
            storage_path('framework'),
            base_path('.env')
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which tools are enabled and their settings.
    |
    */
    'tools' => [
        'database' => [
            'enabled' => env('MCP_TOOL_DATABASE_ENABLED', true),
            'max_rows' => env('MCP_DATABASE_MAX_ROWS', 100),
            'timeout' => env('MCP_DATABASE_TIMEOUT', 30),
        ],

        'cache' => [
            'enabled' => env('MCP_TOOL_CACHE_ENABLED', true),
            'allowed_operations' => ['get', 'put', 'forget', 'flush'],
        ],

        'artisan' => [
            'enabled' => env('MCP_TOOL_ARTISAN_ENABLED', false),
            'allowed_commands' => [
                'route:list',
                'config:show',
                'about',
                'inspire'
            ],
        ],

        'filesystem' => [
            'enabled' => env('MCP_TOOL_FILESYSTEM_ENABLED', false),
            'allowed_disks' => ['local', 'public'],
            'max_file_size' => env('MCP_MAX_FILE_SIZE', 1024 * 1024), // 1MB
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which resources are available and their settings.
    |
    */
    'resources' => [
        'config' => [
            'enabled' => env('MCP_RESOURCE_CONFIG_ENABLED', true),
            'expose_sensitive' => env('MCP_EXPOSE_SENSITIVE_CONFIG', false),
        ],

        'routes' => [
            'enabled' => env('MCP_RESOURCE_ROUTES_ENABLED', true),
            'include_middleware' => env('MCP_ROUTES_INCLUDE_MIDDLEWARE', true),
        ],

        'logs' => [
            'enabled' => env('MCP_RESOURCE_LOGS_ENABLED', true),
            'max_lines' => env('MCP_LOGS_MAX_LINES', 100),
            'allowed_channels' => ['single', 'daily', 'slack'],
        ],

        'models' => [
            'enabled' => env('MCP_RESOURCE_MODELS_ENABLED', true),
            'allowed_models' => [
                'App\Models\User',
                'App\Models\Post',
                // Add your models here
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure real-time notifications for MCP events.
    |
    */
    'notifications' => [
        'enabled' => env('MCP_NOTIFICATIONS_ENABLED', true),
        'channels' => [
            'websocket' => env('MCP_WEBSOCKET_NOTIFICATIONS', false),
            'sse' => env('MCP_SSE_NOTIFICATIONS', true),
            'redis' => env('MCP_REDIS_NOTIFICATIONS', false),
        ],

        // Events to notify about
        'events' => [
            'tool_called' => true,
            'resource_accessed' => false,
            'prompt_generated' => false,
            'connection_opened' => true,
            'connection_closed' => true,
            'error_occurred' => true,
        ],
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
        'debug' => env('MCP_DEBUG', env('APP_DEBUG', false)),
        'mock_external_services' => env('MCP_MOCK_EXTERNAL', false),
        'enable_introspection' => env('MCP_ENABLE_INTROSPECTION', true),
        'log_performance' => env('MCP_LOG_PERFORMANCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MCP client operations.
    |
    */
    'client' => [
        'default_timeout' => env('MCP_CLIENT_TIMEOUT', 30),
        'retry_attempts' => env('MCP_CLIENT_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('MCP_CLIENT_RETRY_DELAY', 1000), // milliseconds
        'connection_pool_size' => env('MCP_CONNECTION_POOL_SIZE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Metrics
    |--------------------------------------------------------------------------
    |
    | Configure monitoring and metrics collection.
    |
    */
    'monitoring' => [
        'enabled' => env('MCP_MONITORING_ENABLED', true),
        'collect_metrics' => env('MCP_COLLECT_METRICS', true),
        'metrics_retention' => env('MCP_METRICS_RETENTION', 86400), // 24 hours

        'alerts' => [
            'enabled' => env('MCP_ALERTS_ENABLED', false),
            'error_threshold' => env('MCP_ERROR_THRESHOLD', 10), // errors per minute
            'response_time_threshold' => env('MCP_RESPONSE_TIME_THRESHOLD', 5000), // milliseconds
        ],
    ],
];
