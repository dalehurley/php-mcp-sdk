<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Server\Transport\StreamableHttpServerTransport;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\Resources\TextResourceContents;
use MCP\Types\Content\TextContent;
use MCP\Types\Prompts\PromptMessage;
use App\Models\User;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Laravel MCP Service Provider Example
 * 
 * This service provider demonstrates how to integrate MCP servers with Laravel:
 * - Register MCP servers as Laravel services
 * - Expose Laravel models and services through MCP tools
 * - Provide Laravel-specific resources and prompts
 * - Handle authentication and authorization
 * - Integrate with Laravel's caching, logging, and database systems
 */
class ExampleMcpServiceProvider extends ServiceProvider
{
    /**
     * Register MCP services
     */
    public function register(): void
    {
        // Register MCP server as singleton
        $this->app->singleton(McpServer::class, function ($app) {
            return new McpServer(
                new Implementation(
                    name: 'laravel-mcp-server',
                    version: '1.0.0',
                    description: 'Laravel MCP Integration Server'
                ),
                new \MCP\Server\ServerOptions(
                    capabilities: new ServerCapabilities(
                        tools: ['listChanged' => true],
                        resources: ['subscribe' => true, 'listChanged' => true],
                        prompts: ['listChanged' => true]
                    ),
                    instructions: "This MCP server provides access to Laravel application data and services."
                )
            );
        });

        // Register HTTP transport for web routes
        $this->app->singleton('mcp.http.transport', function ($app) {
            return new StreamableHttpServerTransport([
                'port' => config('mcp.http.port', 3000),
                'host' => config('mcp.http.host', '127.0.0.1'),
                'cors' => config('mcp.http.cors', true)
            ]);
        });
    }

    /**
     * Bootstrap MCP services
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');

        // Register MCP tools, resources, and prompts
        $this->registerMcpTools();
        $this->registerMcpResources();
        $this->registerMcpPrompts();

        // Register web routes for HTTP transport
        $this->registerMcpRoutes();

        // Register console commands
        $this->registerMcpCommands();
    }

    /**
     * Register MCP tools that expose Laravel functionality
     */
    protected function registerMcpTools(): void
    {
        $server = $this->app->make(McpServer::class);

        // User management tools
        $server->tool(
            'list-users',
            'List users from the database',
            [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of users to return',
                    'default' => 10,
                    'maximum' => 100
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search term for user names or emails'
                ],
                'role' => [
                    'type' => 'string',
                    'description' => 'Filter by user role'
                ]
            ],
            function (array $args) {
                $limit = min(100, max(1, $args['limit'] ?? 10));
                $search = $args['search'] ?? null;
                $role = $args['role'] ?? null;

                $query = User::query();

                if ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%");
                    });
                }

                if ($role) {
                    $query->where('role', $role);
                }

                $users = $query->limit($limit)->get();

                $userData = $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ?? 'user',
                        'created_at' => $user->created_at->toISOString(),
                        'posts_count' => $user->posts()->count()
                    ];
                });

                return new CallToolResult(
                    content: [
                        new TextContent(
                            "Found " . $users->count() . " users:\n" .
                                json_encode($userData, JSON_PRETTY_PRINT)
                        )
                    ]
                );
            }
        );

        // Database query tool
        $server->tool(
            'database-query',
            'Execute a safe database query',
            [
                'query' => [
                    'type' => 'string',
                    'description' => 'SQL SELECT query to execute'
                ],
                'bindings' => [
                    'type' => 'array',
                    'description' => 'Query parameter bindings',
                    'default' => []
                ]
            ],
            function (array $args) {
                $query = $args['query'] ?? '';
                $bindings = $args['bindings'] ?? [];

                // Security: Only allow SELECT statements
                if (!preg_match('/^\s*SELECT\s+/i', $query)) {
                    return new CallToolResult(
                        content: [new TextContent('Only SELECT queries are allowed')],
                        isError: true
                    );
                }

                // Check for dangerous keywords
                $forbidden = ['DELETE', 'UPDATE', 'INSERT', 'DROP', 'ALTER', 'CREATE'];
                foreach ($forbidden as $keyword) {
                    if (preg_match('/\b' . $keyword . '\b/i', $query)) {
                        return new CallToolResult(
                            content: [new TextContent("Forbidden keyword '$keyword' in query")],
                            isError: true
                        );
                    }
                }

                try {
                    $results = DB::select($query, $bindings);

                    return new CallToolResult(
                        content: [
                            new TextContent(
                                "Query Results (" . count($results) . " rows):\n" .
                                    json_encode($results, JSON_PRETTY_PRINT)
                            )
                        ]
                    );
                } catch (\Exception $e) {
                    Log::error('MCP Database Query Error', [
                        'query' => $query,
                        'error' => $e->getMessage()
                    ]);

                    return new CallToolResult(
                        content: [new TextContent('Query failed: ' . $e->getMessage())],
                        isError: true
                    );
                }
            }
        );

        // Cache management tool
        $server->tool(
            'cache-get',
            'Retrieve a value from Laravel cache',
            [
                'key' => [
                    'type' => 'string',
                    'description' => 'Cache key to retrieve'
                ]
            ],
            function (array $args) {
                $key = $args['key'] ?? '';

                if (empty($key)) {
                    return new CallToolResult(
                        content: [new TextContent('Cache key is required')],
                        isError: true
                    );
                }

                $value = Cache::get($key);

                if ($value === null) {
                    return new CallToolResult(
                        content: [new TextContent("Cache key '$key' not found or is null")]
                    );
                }

                return new CallToolResult(
                    content: [
                        new TextContent(
                            "Cache value for '$key':\n" .
                                (is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT))
                        )
                    ]
                );
            }
        );

        // Application metrics tool
        $server->tool(
            'app-metrics',
            'Get application metrics and statistics',
            [
                'include_database' => [
                    'type' => 'boolean',
                    'description' => 'Include database statistics',
                    'default' => true
                ]
            ],
            function (array $args) {
                $includeDatabaseStats = $args['include_database'] ?? true;

                $metrics = [
                    'app' => [
                        'name' => config('app.name'),
                        'env' => config('app.env'),
                        'debug' => config('app.debug'),
                        'url' => config('app.url'),
                        'timezone' => config('app.timezone'),
                        'locale' => config('app.locale')
                    ],
                    'laravel' => [
                        'version' => app()->version(),
                        'php_version' => PHP_VERSION
                    ],
                    'cache' => [
                        'driver' => config('cache.default'),
                        'prefix' => config('cache.prefix')
                    ]
                ];

                if ($includeDatabaseStats) {
                    try {
                        $metrics['database'] = [
                            'connection' => config('database.default'),
                            'users_count' => User::count(),
                            'posts_count' => class_exists(Post::class) ? Post::count() : 'N/A'
                        ];
                    } catch (\Exception $e) {
                        $metrics['database'] = [
                            'error' => 'Could not retrieve database stats: ' . $e->getMessage()
                        ];
                    }
                }

                return new CallToolResult(
                    content: [
                        new TextContent(
                            "Application Metrics:\n" .
                                json_encode($metrics, JSON_PRETTY_PRINT)
                        )
                    ]
                );
            }
        );

        // Artisan command execution tool
        $server->tool(
            'artisan-command',
            'Execute safe Artisan commands',
            [
                'command' => [
                    'type' => 'string',
                    'description' => 'Artisan command to execute'
                ],
                'arguments' => [
                    'type' => 'array',
                    'description' => 'Command arguments',
                    'default' => []
                ]
            ],
            function (array $args) {
                $command = $args['command'] ?? '';
                $arguments = $args['arguments'] ?? [];

                // Whitelist of safe commands
                $allowedCommands = [
                    'route:list',
                    'config:show',
                    'about',
                    'inspire',
                    'tinker --version',
                    'cache:table',
                    'queue:work --help'
                ];

                if (!in_array($command, $allowedCommands)) {
                    return new CallToolResult(
                        content: [new TextContent("Command '$command' is not in the allowed list")],
                        isError: true
                    );
                }

                try {
                    $exitCode = \Artisan::call($command, $arguments);
                    $output = \Artisan::output();

                    return new CallToolResult(
                        content: [
                            new TextContent(
                                "Command: php artisan $command\n" .
                                    "Exit Code: $exitCode\n" .
                                    "Output:\n$output"
                            )
                        ]
                    );
                } catch (\Exception $e) {
                    return new CallToolResult(
                        content: [new TextContent("Command failed: " . $e->getMessage())],
                        isError: true
                    );
                }
            }
        );
    }

    /**
     * Register MCP resources that expose Laravel data
     */
    protected function registerMcpResources(): void
    {
        $server = $this->app->make(McpServer::class);

        // Application configuration resource
        $server->resource(
            'app-config',
            'laravel://config',
            [
                'title' => 'Application Configuration',
                'description' => 'Laravel application configuration',
                'mimeType' => 'application/json'
            ],
            function ($uri, $extra) {
                $config = [
                    'app' => [
                        'name' => config('app.name'),
                        'env' => config('app.env'),
                        'debug' => config('app.debug'),
                        'url' => config('app.url'),
                        'timezone' => config('app.timezone')
                    ],
                    'database' => [
                        'default' => config('database.default'),
                        'connections' => array_keys(config('database.connections'))
                    ],
                    'cache' => [
                        'default' => config('cache.default'),
                        'stores' => array_keys(config('cache.stores'))
                    ],
                    'mail' => [
                        'default' => config('mail.default'),
                        'mailers' => array_keys(config('mail.mailers'))
                    ]
                ];

                return new ReadResourceResult(
                    contents: [
                        new TextResourceContents(
                            uri: $uri,
                            text: json_encode($config, JSON_PRETTY_PRINT),
                            mimeType: 'application/json'
                        )
                    ]
                );
            }
        );

        // Routes resource
        $server->resource(
            'routes',
            'laravel://routes',
            [
                'title' => 'Application Routes',
                'description' => 'List of registered routes',
                'mimeType' => 'application/json'
            ],
            function ($uri, $extra) {
                $routes = collect(Route::getRoutes())->map(function ($route) {
                    return [
                        'uri' => $route->uri(),
                        'methods' => $route->methods(),
                        'name' => $route->getName(),
                        'action' => $route->getActionName(),
                        'middleware' => $route->middleware()
                    ];
                })->values()->all();

                return new ReadResourceResult(
                    contents: [
                        new TextResourceContents(
                            uri: $uri,
                            text: json_encode($routes, JSON_PRETTY_PRINT),
                            mimeType: 'application/json'
                        )
                    ]
                );
            }
        );

        // Log files resource
        $server->resource(
            'logs',
            'laravel://logs/latest',
            [
                'title' => 'Application Logs',
                'description' => 'Recent application log entries',
                'mimeType' => 'text/plain'
            ],
            function ($uri, $extra) {
                $logPath = storage_path('logs/laravel.log');

                if (!file_exists($logPath)) {
                    return new ReadResourceResult(
                        contents: [
                            new TextResourceContents(
                                uri: $uri,
                                text: 'No log file found',
                                mimeType: 'text/plain'
                            )
                        ]
                    );
                }

                // Read last 50 lines of log file
                $lines = [];
                $file = new \SplFileObject($logPath);
                $file->seek(PHP_INT_MAX);
                $totalLines = $file->key();

                $startLine = max(0, $totalLines - 50);
                $file->seek($startLine);

                while (!$file->eof()) {
                    $line = trim($file->fgets());
                    if (!empty($line)) {
                        $lines[] = $line;
                    }
                }

                return new ReadResourceResult(
                    contents: [
                        new TextResourceContents(
                            uri: $uri,
                            text: "Recent Log Entries (last " . count($lines) . " lines):\n\n" . implode("\n", $lines),
                            mimeType: 'text/plain'
                        )
                    ]
                );
            }
        );
    }

    /**
     * Register MCP prompts for Laravel-specific tasks
     */
    protected function registerMcpPrompts(): void
    {
        $server = $this->app->make(McpServer::class);

        // Laravel code generation prompt
        $server->prompt(
            'laravel-model',
            'Generate Laravel model code',
            [
                'model_name' => [
                    'type' => 'string',
                    'description' => 'Name of the model to generate',
                    'required' => true
                ],
                'table_name' => [
                    'type' => 'string',
                    'description' => 'Database table name'
                ],
                'fillable' => [
                    'type' => 'array',
                    'description' => 'Fillable attributes',
                    'default' => []
                ],
                'relationships' => [
                    'type' => 'array',
                    'description' => 'Model relationships',
                    'default' => []
                ]
            ],
            function (array $args) {
                $modelName = $args['model_name'];
                $tableName = $args['table_name'] ?? \Str::snake(\Str::pluralStudly($modelName));
                $fillable = $args['fillable'] ?? [];
                $relationships = $args['relationships'] ?? [];

                $prompt = "Generate a Laravel Eloquent model with the following specifications:\n\n";
                $prompt .= "Model Name: $modelName\n";
                $prompt .= "Table Name: $tableName\n";

                if (!empty($fillable)) {
                    $prompt .= "Fillable Attributes: " . implode(', ', $fillable) . "\n";
                }

                if (!empty($relationships)) {
                    $prompt .= "Relationships:\n";
                    foreach ($relationships as $relationship) {
                        $prompt .= "  - {$relationship['type']}: {$relationship['model']}\n";
                    }
                }

                $prompt .= "\nPlease generate the complete PHP class code for this model, including:\n";
                $prompt .= "- Proper namespace and imports\n";
                $prompt .= "- Class declaration extending Model\n";
                $prompt .= "- Table name property (if different from convention)\n";
                $prompt .= "- Fillable array\n";
                $prompt .= "- Relationship methods\n";
                $prompt .= "- Any necessary casts or accessors/mutators\n";
                $prompt .= "- PHPDoc comments\n";

                return new GetPromptResult(
                    messages: [
                        new PromptMessage(
                            role: 'user',
                            content: new TextContent($prompt)
                        )
                    ]
                );
            }
        );

        // Database migration prompt
        $server->prompt(
            'laravel-migration',
            'Generate Laravel migration code',
            [
                'migration_name' => [
                    'type' => 'string',
                    'description' => 'Name of the migration',
                    'required' => true
                ],
                'table_name' => [
                    'type' => 'string',
                    'description' => 'Table name',
                    'required' => true
                ],
                'columns' => [
                    'type' => 'array',
                    'description' => 'Column definitions',
                    'required' => true
                ]
            ],
            function (array $args) {
                $migrationName = $args['migration_name'];
                $tableName = $args['table_name'];
                $columns = $args['columns'] ?? [];

                $prompt = "Generate a Laravel database migration with the following specifications:\n\n";
                $prompt .= "Migration Name: $migrationName\n";
                $prompt .= "Table Name: $tableName\n";
                $prompt .= "Columns:\n";

                foreach ($columns as $column) {
                    $prompt .= "  - {$column['name']} ({$column['type']})";
                    if (isset($column['nullable']) && $column['nullable']) {
                        $prompt .= " - nullable";
                    }
                    if (isset($column['default'])) {
                        $prompt .= " - default: {$column['default']}";
                    }
                    $prompt .= "\n";
                }

                $prompt .= "\nPlease generate the complete migration class code including:\n";
                $prompt .= "- Proper class name and extends Migration\n";
                $prompt .= "- up() method with Schema::create or Schema::table\n";
                $prompt .= "- down() method with appropriate rollback\n";
                $prompt .= "- All specified columns with correct data types\n";
                $prompt .= "- Indexes, foreign keys, and constraints as needed\n";
                $prompt .= "- Timestamps if appropriate\n";

                return new GetPromptResult(
                    messages: [
                        new PromptMessage(
                            role: 'user',
                            content: new TextContent($prompt)
                        )
                    ]
                );
            }
        );
    }

    /**
     * Register web routes for HTTP MCP transport
     */
    protected function registerMcpRoutes(): void
    {
        if (config('mcp.http.enabled', false)) {
            Route::group([
                'prefix' => config('mcp.http.prefix', 'mcp'),
                'middleware' => config('mcp.http.middleware', ['api'])
            ], function () {
                Route::post('/', [\App\Http\Controllers\McpController::class, 'handle']);
                Route::get('/sse', [\App\Http\Controllers\McpController::class, 'sse']);
            });
        }
    }

    /**
     * Register console commands
     */
    protected function registerMcpCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\McpServerCommand::class,
            ]);
        }
    }
}
