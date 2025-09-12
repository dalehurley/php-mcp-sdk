<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use MCP\Server\McpServer;
use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Transport\StdioServerParameters;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Client\ClientOptions;

/**
 * MCP Demo Controller with Inertia.js Integration
 * 
 * This controller demonstrates how to integrate MCP with Laravel and Inertia.js:
 * - Serve MCP client interface through Inertia pages
 * - Handle MCP server operations via API endpoints
 * - Provide real-time updates using WebSockets/SSE
 * - Manage MCP connections and sessions
 */
class McpDemoController extends Controller
{
    protected McpServer $mcpServer;

    public function __construct(McpServer $mcpServer)
    {
        $this->mcpServer = $mcpServer;
    }

    /**
     * Show the main MCP demo interface
     */
    public function index(): Response
    {
        return Inertia::render('Mcp/Demo', [
            'serverInfo' => [
                'name' => 'Laravel MCP Demo',
                'version' => '1.0.0',
                'status' => 'running'
            ],
            'availableServers' => $this->getAvailableServers(),
            'recentActivity' => $this->getRecentActivity()
        ]);
    }

    /**
     * Show the MCP client interface
     */
    public function client(): Response
    {
        return Inertia::render('Mcp/Client', [
            'servers' => $this->getAvailableServers(),
            'connectionHistory' => $this->getConnectionHistory()
        ]);
    }

    /**
     * Show the MCP server management interface
     */
    public function server(): Response
    {
        return Inertia::render('Mcp/Server', [
            'serverStatus' => $this->getServerStatus(),
            'tools' => $this->getRegisteredTools(),
            'resources' => $this->getRegisteredResources(),
            'prompts' => $this->getRegisteredPrompts(),
            'metrics' => $this->getServerMetrics()
        ]);
    }

    /**
     * Connect to an MCP server
     */
    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'server_type' => 'required|string|in:stdio,http,websocket',
            'server_config' => 'required|array',
            'client_name' => 'string|max:255'
        ]);

        try {
            $serverType = $request->input('server_type');
            $serverConfig = $request->input('server_config');
            $clientName = $request->input('client_name', 'Laravel MCP Client');

            // Create client
            $client = new Client(
                new Implementation($clientName, '1.0.0', 'Laravel Inertia MCP Client'),
                new ClientOptions(capabilities: new ClientCapabilities())
            );

            // Create transport based on type
            $transport = $this->createTransport($serverType, $serverConfig);

            // Connect
            $client->connect($transport)->await();

            // Store connection in session
            session(['mcp_connection' => [
                'client_id' => uniqid('mcp_client_'),
                'server_type' => $serverType,
                'server_config' => $serverConfig,
                'connected_at' => now(),
                'server_info' => $client->getServerVersion()
            ]]);

            return response()->json([
                'success' => true,
                'message' => 'Connected to MCP server successfully',
                'server_info' => $client->getServerVersion(),
                'connection_id' => session('mcp_connection.client_id')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to MCP server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect from MCP server
     */
    public function disconnect(): JsonResponse
    {
        try {
            // In a real implementation, you would properly close the connection
            session()->forget('mcp_connection');

            return response()->json([
                'success' => true,
                'message' => 'Disconnected from MCP server'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error disconnecting from server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List tools available on connected server
     */
    public function listTools(): JsonResponse
    {
        if (!session('mcp_connection')) {
            return response()->json([
                'success' => false,
                'message' => 'No active MCP connection'
            ], 400);
        }

        try {
            // In a real implementation, you would use the stored client connection
            $tools = $this->getMockTools();

            return response()->json([
                'success' => true,
                'tools' => $tools
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list tools',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Call a tool on the connected server
     */
    public function callTool(Request $request): JsonResponse
    {
        $request->validate([
            'tool_name' => 'required|string',
            'parameters' => 'array'
        ]);

        if (!session('mcp_connection')) {
            return response()->json([
                'success' => false,
                'message' => 'No active MCP connection'
            ], 400);
        }

        try {
            $toolName = $request->input('tool_name');
            $parameters = $request->input('parameters', []);

            // In a real implementation, you would call the actual tool
            $result = $this->simulateToolCall($toolName, $parameters);

            // Log the activity
            $this->logActivity('tool_call', [
                'tool' => $toolName,
                'parameters' => $parameters,
                'success' => true
            ]);

            return response()->json([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            $this->logActivity('tool_call', [
                'tool' => $request->input('tool_name'),
                'parameters' => $request->input('parameters', []),
                'success' => false,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tool call failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List resources available on connected server
     */
    public function listResources(): JsonResponse
    {
        if (!session('mcp_connection')) {
            return response()->json([
                'success' => false,
                'message' => 'No active MCP connection'
            ], 400);
        }

        try {
            $resources = $this->getMockResources();

            return response()->json([
                'success' => true,
                'resources' => $resources
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list resources',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Read a resource from the connected server
     */
    public function readResource(Request $request): JsonResponse
    {
        $request->validate([
            'uri' => 'required|string'
        ]);

        if (!session('mcp_connection')) {
            return response()->json([
                'success' => false,
                'message' => 'No active MCP connection'
            ], 400);
        }

        try {
            $uri = $request->input('uri');

            // In a real implementation, you would read the actual resource
            $content = $this->simulateResourceRead($uri);

            return response()->json([
                'success' => true,
                'content' => $content
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to read resource',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get server status and metrics
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'server_status' => $this->getServerStatus(),
            'connection_status' => session('mcp_connection') ? 'connected' : 'disconnected',
            'metrics' => $this->getServerMetrics(),
            'recent_activity' => $this->getRecentActivity()
        ]);
    }

    /**
     * Start server-sent events stream for real-time updates
     */
    public function events(Request $request)
    {
        return response()->stream(function () {
            // Set up SSE headers
            echo "data: " . json_encode([
                'type' => 'connected',
                'timestamp' => now()->toISOString(),
                'message' => 'Connected to MCP event stream'
            ]) . "\n\n";

            ob_flush();
            flush();

            // Simulate periodic updates
            $counter = 0;
            while ($counter < 10) { // Limit for demo
                sleep(2);

                echo "data: " . json_encode([
                    'type' => 'heartbeat',
                    'timestamp' => now()->toISOString(),
                    'counter' => ++$counter,
                    'server_status' => 'running'
                ]) . "\n\n";

                ob_flush();
                flush();
            }

            echo "data: " . json_encode([
                'type' => 'disconnected',
                'timestamp' => now()->toISOString(),
                'message' => 'Event stream ended'
            ]) . "\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true'
        ]);
    }

    /**
     * WebSocket endpoint for real-time communication
     */
    public function websocket(Request $request): JsonResponse
    {
        // This would integrate with a WebSocket server like Laravel WebSockets
        return response()->json([
            'websocket_url' => config('websockets.endpoint', 'ws://localhost:6001'),
            'channel' => 'mcp-updates',
            'auth' => [
                'key' => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster')
            ]
        ]);
    }

    /**
     * Create transport based on type and configuration
     */
    protected function createTransport(string $type, array $config)
    {
        switch ($type) {
            case 'stdio':
                return new StdioClientTransport(
                    new StdioServerParameters(
                        command: $config['command'] ?? 'php',
                        args: $config['args'] ?? [],
                        cwd: $config['cwd'] ?? base_path()
                    )
                );

            case 'http':
                // Return HTTP transport when implemented
                throw new \Exception('HTTP transport not yet implemented');

            case 'websocket':
                // Return WebSocket transport when implemented
                throw new \Exception('WebSocket transport not yet implemented');

            default:
                throw new \Exception("Unsupported transport type: $type");
        }
    }

    /**
     * Get available MCP servers
     */
    protected function getAvailableServers(): array
    {
        return [
            [
                'id' => 'calculator',
                'name' => 'Calculator Server',
                'description' => 'Mathematical calculations and operations',
                'type' => 'stdio',
                'status' => 'available',
                'config' => [
                    'command' => 'php',
                    'args' => [base_path('examples/server/simple-server.php')]
                ]
            ],
            [
                'id' => 'weather',
                'name' => 'Weather Server',
                'description' => 'Weather information and forecasts',
                'type' => 'stdio',
                'status' => 'available',
                'config' => [
                    'command' => 'php',
                    'args' => [base_path('examples/server/weather-server.php')]
                ]
            ],
            [
                'id' => 'database',
                'name' => 'Database Server',
                'description' => 'Database queries and management',
                'type' => 'stdio',
                'status' => 'available',
                'config' => [
                    'command' => 'php',
                    'args' => [base_path('examples/server/sqlite-server.php')]
                ]
            ]
        ];
    }

    /**
     * Get server status information
     */
    protected function getServerStatus(): array
    {
        return [
            'status' => 'running',
            'uptime' => '2h 15m',
            'version' => '1.0.0',
            'connections' => 1,
            'requests_handled' => 42,
            'last_activity' => now()->subMinutes(2)->toISOString()
        ];
    }

    /**
     * Get registered tools (mock data)
     */
    protected function getRegisteredTools(): array
    {
        return [
            [
                'name' => 'list-users',
                'description' => 'List users from the database',
                'parameters' => ['limit', 'search', 'role']
            ],
            [
                'name' => 'database-query',
                'description' => 'Execute a safe database query',
                'parameters' => ['query', 'bindings']
            ],
            [
                'name' => 'cache-get',
                'description' => 'Retrieve a value from Laravel cache',
                'parameters' => ['key']
            ]
        ];
    }

    /**
     * Get registered resources (mock data)
     */
    protected function getRegisteredResources(): array
    {
        return [
            [
                'name' => 'app-config',
                'uri' => 'laravel://config',
                'description' => 'Application configuration',
                'mimeType' => 'application/json'
            ],
            [
                'name' => 'routes',
                'uri' => 'laravel://routes',
                'description' => 'Application routes',
                'mimeType' => 'application/json'
            ],
            [
                'name' => 'logs',
                'uri' => 'laravel://logs/latest',
                'description' => 'Recent log entries',
                'mimeType' => 'text/plain'
            ]
        ];
    }

    /**
     * Get registered prompts (mock data)
     */
    protected function getRegisteredPrompts(): array
    {
        return [
            [
                'name' => 'laravel-model',
                'description' => 'Generate Laravel model code',
                'parameters' => ['model_name', 'table_name', 'fillable', 'relationships']
            ],
            [
                'name' => 'laravel-migration',
                'description' => 'Generate Laravel migration code',
                'parameters' => ['migration_name', 'table_name', 'columns']
            ]
        ];
    }

    /**
     * Get server metrics
     */
    protected function getServerMetrics(): array
    {
        return [
            'requests_per_minute' => 12,
            'average_response_time' => 150,
            'error_rate' => 0.02,
            'memory_usage' => '45 MB',
            'cpu_usage' => '12%'
        ];
    }

    /**
     * Get recent activity log
     */
    protected function getRecentActivity(): array
    {
        return [
            [
                'type' => 'tool_call',
                'description' => 'Called list-users tool',
                'timestamp' => now()->subMinutes(2)->toISOString(),
                'status' => 'success'
            ],
            [
                'type' => 'resource_read',
                'description' => 'Read app-config resource',
                'timestamp' => now()->subMinutes(5)->toISOString(),
                'status' => 'success'
            ],
            [
                'type' => 'connection',
                'description' => 'Client connected',
                'timestamp' => now()->subMinutes(10)->toISOString(),
                'status' => 'success'
            ]
        ];
    }

    /**
     * Get connection history
     */
    protected function getConnectionHistory(): array
    {
        return [
            [
                'server' => 'Calculator Server',
                'connected_at' => now()->subHour()->toISOString(),
                'disconnected_at' => now()->subMinutes(30)->toISOString(),
                'duration' => '30 minutes',
                'requests' => 15
            ],
            [
                'server' => 'Weather Server',
                'connected_at' => now()->subHours(2)->toISOString(),
                'disconnected_at' => now()->subHour()->toISOString(),
                'duration' => '1 hour',
                'requests' => 8
            ]
        ];
    }

    /**
     * Get mock tools for demo
     */
    protected function getMockTools(): array
    {
        return [
            [
                'name' => 'echo',
                'description' => 'Echo back the input message',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string']
                    ]
                ]
            ],
            [
                'name' => 'calculate',
                'description' => 'Perform mathematical calculations',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => ['type' => 'string']
                    ]
                ]
            ]
        ];
    }

    /**
     * Get mock resources for demo
     */
    protected function getMockResources(): array
    {
        return [
            [
                'uri' => 'config://server.json',
                'name' => 'server-config',
                'description' => 'Server configuration',
                'mimeType' => 'application/json'
            ],
            [
                'uri' => 'docs://README.md',
                'name' => 'documentation',
                'description' => 'Server documentation',
                'mimeType' => 'text/markdown'
            ]
        ];
    }

    /**
     * Simulate a tool call
     */
    protected function simulateToolCall(string $toolName, array $parameters): array
    {
        switch ($toolName) {
            case 'echo':
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Echo: ' . ($parameters['message'] ?? 'No message')
                        ]
                    ]
                ];

            case 'calculate':
                $expression = $parameters['expression'] ?? '1 + 1';
                // Simple calculation simulation
                try {
                    $result = eval("return $expression;");
                    return [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => "Result: $result"
                            ]
                        ]
                    ];
                } catch (\Exception $e) {
                    return [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Error: Invalid expression'
                            ]
                        ],
                        'isError' => true
                    ];
                }

            default:
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Tool '$toolName' called with parameters: " . json_encode($parameters)
                        ]
                    ]
                ];
        }
    }

    /**
     * Simulate reading a resource
     */
    protected function simulateResourceRead(string $uri): array
    {
        return [
            'uri' => $uri,
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Content of resource: $uri\n\nThis is simulated content for demonstration purposes."
                ]
            ]
        ];
    }

    /**
     * Log activity for tracking
     */
    protected function logActivity(string $type, array $data): void
    {
        // In a real implementation, you might store this in a database or cache
        \Log::info("MCP Activity: $type", $data);
    }
}
