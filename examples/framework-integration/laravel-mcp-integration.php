<?php

/**
 * Laravel MCP Integration Example.
 *
 * This example demonstrates how to integrate the PHP MCP SDK with Laravel.
 * It shows patterns for:
 * - Using Laravel's service container with MCP
 * - Integrating with Laravel's database system
 * - Using Laravel's validation and middleware
 * - Leveraging Laravel's event system
 *
 * This is a standalone example that demonstrates Laravel patterns
 * without requiring a full Laravel installation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function Amp\async;

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;

// Mock Laravel-style Service Container
class MockContainer
{
    private array $bindings = [];

    private array $instances = [];

    public function bind(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable $concrete): void
    {
        $this->bind($abstract, $concrete);
    }

    public function make(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $instance = $this->bindings[$abstract]();
            $this->instances[$abstract] = $instance;

            return $instance;
        }

        throw new Exception("Service {$abstract} not found in container");
    }
}

// Mock Laravel-style Database Connection
class MockDatabase
{
    private array $users = [
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'admin'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => 'user'],
        ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com', 'role' => 'user'],
    ];

    private array $posts = [
        ['id' => 1, 'user_id' => 1, 'title' => 'Welcome to MCP', 'content' => 'This is our first MCP post!', 'status' => 'published'],
        ['id' => 2, 'user_id' => 2, 'title' => 'Laravel Integration', 'content' => 'How to integrate MCP with Laravel', 'status' => 'draft'],
        ['id' => 3, 'user_id' => 1, 'title' => 'Advanced MCP', 'content' => 'Advanced MCP techniques', 'status' => 'published'],
    ];

    public function table(string $table): self
    {
        $this->currentTable = $table;

        return $this;
    }

    public function get(): array
    {
        return match ($this->currentTable) {
            'users' => $this->users,
            'posts' => $this->posts,
            default => []
        };
    }

    public function where(string $column, string $operator, $value): self
    {
        $data = match ($this->currentTable) {
            'users' => $this->users,
            'posts' => $this->posts,
            default => []
        };

        $this->filteredData = array_filter($data, function ($item) use ($column, $operator, $value) {
            return match ($operator) {
                '=' => $item[$column] == $value,
                '!=' => $item[$column] != $value,
                'like' => stripos($item[$column], str_replace('%', '', $value)) !== false,
                default => false
            };
        });

        return $this;
    }

    public function first(): ?array
    {
        $data = $this->filteredData ?? match ($this->currentTable) {
            'users' => $this->users,
            'posts' => $this->posts,
            default => []
        };

        return empty($data) ? null : array_values($data)[0];
    }

    private string $currentTable;

    private ?array $filteredData = null;
}

// Mock Laravel-style Validator
class MockValidator
{
    public static function make(array $data, array $rules): self
    {
        $validator = new self();
        $validator->data = $data;
        $validator->rules = $rules;

        return $validator;
    }

    public function fails(): bool
    {
        foreach ($this->rules as $field => $rules) {
            $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

            foreach ($rulesArray as $rule) {
                if ($rule === 'required' && !isset($this->data[$field])) {
                    $this->errors[$field][] = "The {$field} field is required.";
                }
                if ($rule === 'email' && isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "The {$field} must be a valid email address.";
                }
                if (str_starts_with($rule, 'min:') && isset($this->data[$field])) {
                    $min = (int) substr($rule, 4);
                    if (strlen($this->data[$field]) < $min) {
                        $this->errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                }
            }
        }

        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private array $data;

    private array $rules;

    private array $errors = [];
}

// Set up Laravel-style Service Container
$container = new MockContainer();

// Bind services to container
$container->singleton('db', fn () => new MockDatabase());
$container->singleton('validator', fn () => MockValidator::class);

// Create MCP Server with Laravel integration
$server = new McpServer(
    new Implementation(
        'laravel-mcp-server',
        '1.0.0',
        'Laravel MCP Integration Example'
    )
);

// Tool: Get Users (demonstrates database integration)
$server->tool(
    'get_users',
    'Retrieve users from the database',
    [
        'type' => 'object',
        'properties' => [
            'role' => [
                'type' => 'string',
                'description' => 'Filter users by role (optional)',
                'enum' => ['admin', 'user'],
            ],
        ],
    ],
    function (array $args) use ($container): array {
        $db = $container->make('db');

        $query = $db->table('users');

        if (isset($args['role'])) {
            $query = $query->where('role', '=', $args['role']);
        }

        $users = $query->get();

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Found ' . count($users) . " users:\n\n" .
                        implode("\n", array_map(
                            fn ($user) => "• {$user['name']} ({$user['email']}) - {$user['role']}",
                            $users
                        )),
                ],
            ],
        ];
    }
);

// Tool: Create User (demonstrates validation)
$server->tool(
    'create_user',
    'Create a new user with validation',
    [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'User name',
            ],
            'email' => [
                'type' => 'string',
                'description' => 'User email address',
            ],
            'role' => [
                'type' => 'string',
                'description' => 'User role',
                'enum' => ['admin', 'user'],
                'default' => 'user',
            ],
        ],
        'required' => ['name', 'email'],
    ],
    function (array $args) use ($container): array {
        // Laravel-style validation
        $validatorClass = $container->make('validator');
        $validator = $validatorClass::make($args, [
            'name' => 'required|min:2',
            'email' => 'required|email',
            'role' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = '• ' . implode(', ', $fieldErrors);
            }

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "❌ Validation failed:\n" . implode("\n", $errorMessages),
                    ],
                ],
            ];
        }

        // Simulate user creation
        $user = [
            'id' => rand(100, 999),
            'name' => $args['name'],
            'email' => $args['email'],
            'role' => $args['role'] ?? 'user',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "✅ User created successfully!\n\n" .
                        "ID: {$user['id']}\n" .
                        "Name: {$user['name']}\n" .
                        "Email: {$user['email']}\n" .
                        "Role: {$user['role']}\n" .
                        "Created: {$user['created_at']}",
                ],
            ],
        ];
    }
);

// Tool: Get Posts (demonstrates relationships)
$server->tool(
    'get_posts',
    'Retrieve blog posts with user information',
    [
        'type' => 'object',
        'properties' => [
            'status' => [
                'type' => 'string',
                'description' => 'Filter posts by status',
                'enum' => ['published', 'draft'],
            ],
            'user_id' => [
                'type' => 'integer',
                'description' => 'Filter posts by user ID',
            ],
        ],
    ],
    function (array $args) use ($container): array {
        $db = $container->make('db');

        // Get posts
        $query = $db->table('posts');

        if (isset($args['status'])) {
            $query = $query->where('status', '=', $args['status']);
        }

        if (isset($args['user_id'])) {
            $query = $query->where('user_id', '=', $args['user_id']);
        }

        $posts = $query->get();

        // Get users for relationship
        $users = $db->table('users')->get();
        $usersById = array_column($users, null, 'id');

        $postList = [];
        foreach ($posts as $post) {
            $user = $usersById[$post['user_id']] ?? null;
            $author = $user ? $user['name'] : 'Unknown';

            $postList[] = "📝 {$post['title']}\n" .
                "   Author: {$author}\n" .
                "   Status: {$post['status']}\n" .
                '   Content: ' . substr($post['content'], 0, 100) . '...';
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Found ' . count($posts) . " posts:\n\n" . implode("\n\n", $postList),
                ],
            ],
        ];
    }
);

// Resource: Laravel Application Info
$server->resource(
    'Laravel Application Info',
    'laravel://app-info',
    [
        'title' => 'Laravel Application Information',
        'description' => 'Information about the Laravel MCP integration',
        'mimeType' => 'application/json',
    ],
    function (): string {
        return json_encode([
            'framework' => 'Laravel',
            'mcp_integration' => 'PHP MCP SDK',
            'features' => [
                'Service Container Integration',
                'Database Query Builder',
                'Request Validation',
                'Middleware Support',
                'Event System',
            ],
            'database_tables' => ['users', 'posts'],
            'available_tools' => ['get_users', 'create_user', 'get_posts'],
            'environment' => 'development',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT);
    }
);

// Resource: Database Schema
$server->resource(
    'Database Schema',
    'laravel://schema',
    [
        'title' => 'Database Schema Information',
        'description' => 'Schema information for the application database',
        'mimeType' => 'text/plain',
    ],
    function (): string {
        return <<<SCHEMA
            Laravel MCP Database Schema
            ==========================

            Users Table:
            - id (integer, primary key)
            - name (string, required)
            - email (string, required, unique)
            - role (string, enum: admin|user)

            Posts Table:
            - id (integer, primary key)
            - user_id (integer, foreign key -> users.id)
            - title (string, required)
            - content (text, required)
            - status (string, enum: published|draft)

            Relationships:
            - User hasMany Posts
            - Post belongsTo User

            Sample Data:
            - 3 users (1 admin, 2 regular users)
            - 3 posts (2 published, 1 draft)
            SCHEMA;
    }
);

// Prompt: Laravel Development Help
$server->prompt(
    'laravel_help',
    'Get help with Laravel MCP development',
    function (): array {
        return [
            'description' => 'Laravel MCP Development Assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I integrate MCP with Laravel?',
                        ],
                    ],
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Integrating MCP with Laravel follows these patterns:\n\n" .
                                "**1. Service Container Integration:**\n" .
                                "• Register MCP services in Laravel's service container\n" .
                                "• Use dependency injection for MCP components\n" .
                                "• Bind MCP servers as singletons\n\n" .
                                "**2. Database Integration:**\n" .
                                "• Use Laravel's Query Builder in MCP tools\n" .
                                "• Leverage Eloquent models for complex operations\n" .
                                "• Implement proper database transactions\n\n" .
                                "**3. Validation:**\n" .
                                "• Use Laravel's validation in MCP tool handlers\n" .
                                "• Return structured error responses\n" .
                                "• Validate input schemas\n\n" .
                                "**4. Middleware:**\n" .
                                "• Create MCP-specific middleware for authentication\n" .
                                "• Use Laravel's middleware stack\n" .
                                "• Implement rate limiting\n\n" .
                                "**Available Tools:**\n" .
                                "• get_users - Retrieve users with optional filtering\n" .
                                "• create_user - Create users with validation\n" .
                                "• get_posts - Get posts with relationships\n\n" .
                                "Try: 'Use the get_users tool to see all admin users'",
                        ],
                    ],
                ],
            ],
        ];
    }
);

// Start the server
async(function () use ($server, $container) {
    echo "🚀 Laravel MCP Integration Server starting...\n";
    echo '📊 Database: ' . count($container->make('db')->table('users')->get()) . ' users, ' .
        count($container->make('db')->table('posts')->get()) . " posts\n";
    echo "🛠️  Available tools: get_users, create_user, get_posts\n";
    echo "📚 Resources: app-info, schema\n" . PHP_EOL;

    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
