<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MCP\Server\McpServer;
use MCP\Types\Implementation;
use MCP\Types\Tool;

/**
 * Example Laravel controller showing how to integrate the core PHP MCP SDK
 * with a Laravel application.
 *
 * This demonstrates:
 * - Creating an MCP server in Laravel
 * - Registering tools that use Laravel models
 * - Handling MCP requests through HTTP endpoints
 * - Using Laravel's dependency injection
 */
class McpController extends Controller
{
    private McpServer $mcpServer;

    public function __construct()
    {
        $this->mcpServer = new McpServer(
            new Implementation(
                name: config('app.name', 'Laravel MCP Server'),
                version: config('app.version', '1.0.0')
            )
        );

        $this->registerTools();
        $this->registerResources();
    }

    /**
     * Register MCP tools that use Laravel models and services.
     */
    private function registerTools(): void
    {
        // User search tool
        $this->mcpServer->registerTool(
            'search-users',
            new Tool(
                name: 'search-users',
                description: 'Search for users by name or email',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query for user name or email',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results',
                            'default' => 10,
                        ],
                    ],
                    'required' => ['query'],
                ]
            ),
            function (array $params) {
                $query = $params['query'];
                $limit = $params['limit'] ?? 10;

                $users = User::where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->limit($limit)
                    ->get(['id', 'name', 'email', 'created_at']);

                return [
                    'users' => $users->toArray(),
                    'count' => $users->count(),
                    'query' => $query,
                ];
            }
        );

        // Create post tool
        $this->mcpServer->registerTool(
            'create-post',
            new Tool(
                name: 'create-post',
                description: 'Create a new blog post',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Post title',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Post content',
                        ],
                        'user_id' => [
                            'type' => 'integer',
                            'description' => 'Author user ID',
                        ],
                    ],
                    'required' => ['title', 'content', 'user_id'],
                ]
            ),
            function (array $params) {
                $post = Post::create([
                    'title' => $params['title'],
                    'content' => $params['content'],
                    'user_id' => $params['user_id'],
                ]);

                return [
                    'success' => true,
                    'post' => $post->toArray(),
                    'message' => 'Post created successfully',
                ];
            }
        );

        // Laravel cache tool
        $this->mcpServer->registerTool(
            'cache-operation',
            new Tool(
                name: 'cache-operation',
                description: 'Perform cache operations (get, put, forget)',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['get', 'put', 'forget', 'flush'],
                            'description' => 'Cache operation to perform',
                        ],
                        'key' => [
                            'type' => 'string',
                            'description' => 'Cache key',
                        ],
                        'value' => [
                            'description' => 'Value to cache (for put operation)',
                        ],
                        'ttl' => [
                            'type' => 'integer',
                            'description' => 'Time to live in seconds (for put operation)',
                            'default' => 3600,
                        ],
                    ],
                    'required' => ['operation'],
                ]
            ),
            function (array $params) {
                $operation = $params['operation'];
                $key = $params['key'] ?? null;

                switch ($operation) {
                    case 'get':
                        if (!$key) {
                            throw new \InvalidArgumentException('Key is required for get operation');
                        }

                        return [
                            'operation' => 'get',
                            'key' => $key,
                            'value' => cache($key),
                            'exists' => cache()->has($key),
                        ];

                    case 'put':
                        if (!$key) {
                            throw new \InvalidArgumentException('Key is required for put operation');
                        }
                        $value = $params['value'] ?? null;
                        $ttl = $params['ttl'] ?? 3600;

                        cache([$key => $value], $ttl);

                        return [
                            'operation' => 'put',
                            'key' => $key,
                            'value' => $value,
                            'ttl' => $ttl,
                            'success' => true,
                        ];

                    case 'forget':
                        if (!$key) {
                            throw new \InvalidArgumentException('Key is required for forget operation');
                        }
                        $success = cache()->forget($key);

                        return [
                            'operation' => 'forget',
                            'key' => $key,
                            'success' => $success,
                        ];

                    case 'flush':
                        cache()->flush();

                        return [
                            'operation' => 'flush',
                            'success' => true,
                            'message' => 'All cache cleared',
                        ];

                    default:
                        throw new \InvalidArgumentException("Unknown operation: {$operation}");
                }
            }
        );
    }

    /**
     * Register MCP resources.
     */
    private function registerResources(): void
    {
        $this->mcpServer->registerResource(
            'users',
            'List of all users',
            'application/json',
            function () {
                return User::all(['id', 'name', 'email'])->toArray();
            }
        );

        $this->mcpServer->registerResource(
            'posts',
            'List of all blog posts',
            'application/json',
            function () {
                return Post::with('user:id,name')
                    ->get(['id', 'title', 'content', 'user_id', 'created_at'])
                    ->toArray();
            }
        );
    }

    /**
     * Handle MCP requests via HTTP.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // This would typically use an HTTP transport adapter
            // For this example, we'll simulate processing the request

            $method = $request->input('method');
            $params = $request->input('params', []);

            switch ($method) {
                case 'tools/list':
                    return response()->json([
                        'tools' => $this->mcpServer->getTools(),
                    ]);

                case 'tools/call':
                    $toolName = $params['name'] ?? '';
                    $arguments = $params['arguments'] ?? [];

                    $result = $this->mcpServer->callTool($toolName, $arguments);

                    return response()->json([
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => json_encode($result, JSON_PRETTY_PRINT),
                            ],
                        ],
                    ]);

                case 'resources/list':
                    return response()->json([
                        'resources' => $this->mcpServer->getResources(),
                    ]);

                default:
                    return response()->json([
                        'error' => [
                            'code' => -32601,
                            'message' => 'Method not found',
                            'data' => ['method' => $method],
                        ],
                    ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => ['message' => $e->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * Get server information.
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'tools_count' => count($this->mcpServer->getTools()),
            'resources_count' => count($this->mcpServer->getResources()),
            'mcp_version' => '2024-11-05',
        ]);
    }
}
