<?php

declare(strict_types=1);

namespace MCP\Shared;

use MCP\Server\Transport\StreamableHttpServerTransport;
use MCP\Server\Transport\StreamableHttpServerTransportOptions;
use MCP\Server\McpServer;

/**
 * Laravel integration helpers for MCP SDK
 * Provides convenient methods for integrating MCP servers with Laravel applications
 * 
 * Note: This class provides helper methods and configurations for Laravel integration.
 * Actual Laravel types are not imported to keep the SDK framework-agnostic.
 * Users should install Laravel packages separately when using these helpers.
 */
class LaravelIntegration
{
    /**
     * Create a Laravel route handler configuration for MCP HTTP transport
     * 
     * @param array<string, mixed> $options Transport options
     * @return array<string, mixed> Configuration for Laravel route handler
     */
    public static function createHttpRouteHandlerConfig(array $options = []): array
    {
        return [
            'transport_options' => [
                'sessionIdGenerator' => $options['sessionIdGenerator'] ?? null,
                'onsessioninitialized' => $options['onsessioninitialized'] ?? null,
                'onsessionclosed' => $options['onsessionclosed'] ?? null,
                'enableJsonResponse' => $options['enableJsonResponse'] ?? false,
                'eventStore' => $options['eventStore'] ?? null,
                'allowedHosts' => $options['allowedHosts'] ?? ['localhost', '127.0.0.1'],
                'allowedOrigins' => $options['allowedOrigins'] ?? null,
                'enableDnsRebindingProtection' => $options['enableDnsRebindingProtection'] ?? true
            ],
            'example_usage' => [
                'route' => 'Route::post(\'/mcp\', [McpController::class, \'handle\']);',
                'controller_method' => 'public function handle(Request $request) { /* implementation */ }'
            ]
        ];
    }

    /**
     * Create a Laravel middleware configuration for MCP authentication
     * 
     * @param array<string, mixed> $config Authentication configuration
     * @return array<string, mixed> Laravel middleware configuration
     */
    public static function createAuthMiddlewareConfig(array $config = []): array
    {
        return [
            'middleware_class' => 'McpAuthMiddleware',
            'config' => $config,
            'example_implementation' => [
                'handle' => 'Extract auth info and add to request attributes',
                'attributes' => [
                    'mcp_auth_header' => 'Authorization header value',
                    'mcp_session_id' => 'X-MCP-Session-ID header value',
                    'mcp_auth_info' => 'Extracted authentication information'
                ]
            ]
        ];
    }

    /**
     * Create a Laravel service provider configuration for MCP
     * 
     * @return array<string, mixed> Service provider configuration
     */
    public static function createServiceProviderConfig(): array
    {
        return [
            'bindings' => [
                'McpServer' => 'Bind McpServer instance',
                'StreamableHttpServerTransport' => 'Bind transport with configuration'
            ],
            'config_file' => 'config/mcp.php',
            'config_structure' => [
                'allowed_hosts' => ['localhost', '127.0.0.1'],
                'allowed_origins' => null,
                'dns_rebinding_protection' => true,
                'enable_json_response' => false,
                'session_timeout' => 3600, // 1 hour
                'max_message_size' => 4 * 1024 * 1024, // 4MB
            ],
            'example_service_provider' => [
                'register_method' => 'Register MCP services in IoC container',
                'boot_method' => 'Set up routes and middleware'
            ]
        ];
    }

    /**
     * Extract authentication information from request-like data structure
     * 
     * @param array<string, mixed> $requestData Request data (headers, query params, etc.)
     * @param array<string, mixed> $config Authentication configuration
     * @return array<string, mixed> Authentication info
     */
    public static function extractAuthInfo(array $requestData, array $config = []): array
    {
        $authInfo = [];

        // Extract bearer token
        $authHeader = $requestData['headers']['Authorization'] ?? $requestData['headers']['authorization'] ?? null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $authInfo['bearer_token'] = substr($authHeader, 7);
        }

        // Extract API key
        $apiKey = $requestData['headers']['X-API-Key'] ??
            $requestData['headers']['x-api-key'] ??
            $requestData['query']['api_key'] ?? null;
        if ($apiKey) {
            $authInfo['api_key'] = $apiKey;
        }

        // Extract session ID
        $sessionId = $requestData['headers']['X-MCP-Session-ID'] ??
            $requestData['headers']['x-mcp-session-id'] ?? null;
        if ($sessionId) {
            $authInfo['session_id'] = $sessionId;
        }

        // Extract user information if available
        if (isset($requestData['user'])) {
            $authInfo['user'] = $requestData['user'];
        }

        // Extract custom auth fields from config
        foreach ($config['custom_fields'] ?? [] as $field => $header) {
            $value = $requestData['headers'][$header] ?? null;
            if ($value) {
                $authInfo[$field] = $value;
            }
        }

        return $authInfo;
    }

    /**
     * Create Laravel Artisan command configuration for MCP server
     * 
     * @param string $signature Command signature
     * @param string $description Command description
     * @return array<string, mixed> Command configuration
     */
    public static function createArtisanCommandConfig(
        string $signature = 'mcp:serve {--host=127.0.0.1} {--port=8080}',
        string $description = 'Start MCP server'
    ): array {
        return [
            'signature' => $signature,
            'description' => $description,
            'example_implementation' => [
                'handle_method' => 'Start MCP server with given host and port',
                'dependencies' => ['McpServer', 'StreamableHttpServerTransport'],
                'output' => 'Console output for server status'
            ]
        ];
    }

    /**
     * Create validation rules for MCP messages (Laravel validation format)
     * 
     * @return array<string, string|array<string>> Laravel validation rules
     */
    public static function getValidationRules(): array
    {
        return [
            'jsonrpc' => 'required|in:2.0',
            'method' => 'required_without:result,error|string',
            'params' => 'sometimes|array',
            'id' => 'sometimes|required_with:result,error',
            'result' => 'sometimes|required_without:error',
            'error' => 'sometimes|required_without:result|array',
            'error.code' => 'required_with:error|integer',
            'error.message' => 'required_with:error|string',
            'error.data' => 'sometimes|array'
        ];
    }

    /**
     * Create example Laravel controller methods for MCP integration
     * 
     * @return array<string, string> Example controller implementations
     */
    public static function getExampleControllerMethods(): array
    {
        return [
            'handle_mcp_request' => '
                public function handle(Request $request): JsonResponse 
                {
                    $mcpServer = app(McpServer::class);
                    $transport = app(StreamableHttpServerTransport::class);
                    
                    // Process MCP request
                    $result = $mcpServer->processRequest($request->all());
                    
                    return response()->json($result);
                }',

            'mcp_sse_endpoint' => '
                public function sse(Request $request): StreamedResponse
                {
                    return response()->stream(function () use ($request) {
                        // Set up SSE headers and stream MCP events
                        header("Content-Type: text/event-stream");
                        header("Cache-Control: no-cache");
                        
                        // Stream MCP server events
                        while (true) {
                            echo "data: " . json_encode([
                                "type" => "heartbeat", 
                                "timestamp" => time()
                            ]) . "\n\n";
                            
                            ob_flush();
                            flush();
                            sleep(30);
                        }
                    });
                }',

            'mcp_websocket_upgrade' => '
                public function websocket(Request $request): Response
                {
                    // WebSocket upgrade logic would go here
                    // This is a placeholder for future WebSocket support
                    return response()->json([
                        "error" => "WebSocket transport not yet implemented"
                    ], 501);
                }'
        ];
    }
}
