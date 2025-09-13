<?php

declare(strict_types=1);

namespace MCP\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use MCP\Server\McpServer;
use MCP\Server\Transport\StreamableHttpServerTransport;
use MCP\Server\Transport\StreamableHttpServerTransportOptions;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpController extends Controller
{
    public function __construct(
        private McpServer $server
    ) {}

    /**
     * Handle MCP JSON-RPC requests.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Validate request
            $this->validateRequest($request);

            // Get transport options
            $options = $this->getTransportOptions();
            
            // Create transport
            $transport = new StreamableHttpServerTransport($options);
            
            // Connect server to transport if not already connected
            if (!$this->server->isConnected()) {
                $this->server->connect($transport);
            }

            // Handle the request
            $jsonRpcData = $request->json()->all();
            $result = $transport->handleHttpRequest($jsonRpcData);

            // Log request if enabled
            $this->logRequest($request, $result);

            return response()->json($result);
        } catch (\Throwable $e) {
            $this->logError($request, $e);
            
            return $this->createErrorResponse($e, $request);
        }
    }

    /**
     * Handle Server-Sent Events (SSE) connections.
     */
    public function sse(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {
            try {
                // Set headers for SSE
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no'); // Disable Nginx buffering

                // Get transport options
                $options = $this->getTransportOptions();
                
                // Create transport
                $transport = new StreamableHttpServerTransport($options);
                
                // Connect server to transport
                if (!$this->server->isConnected()) {
                    $this->server->connect($transport);
                }

                // Handle SSE connection
                $transport->handleSseConnection();
                
            } catch (\Throwable $e) {
                $this->logError($request, $e);
                
                // Send error event
                echo "event: error\n";
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Get server information.
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'server' => [
                'name' => config('mcp.server.name'),
                'version' => config('mcp.server.version'),
                'capabilities' => config('mcp.server.capabilities'),
                'transport' => 'http',
            ],
            'laravel' => [
                'version' => app()->version(),
                'environment' => config('app.env'),
            ],
        ]);
    }

    /**
     * Health check endpoint.
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [],
        ];

        // Check server connection
        $health['checks']['server'] = $this->server->isConnected() ? 'connected' : 'disconnected';

        // Check cache if enabled
        if (config('mcp.cache.enabled')) {
            try {
                cache()->put('mcp_health_check', true, 1);
                cache()->forget('mcp_health_check');
                $health['checks']['cache'] = 'ok';
            } catch (\Throwable) {
                $health['checks']['cache'] = 'error';
                $health['status'] = 'degraded';
            }
        }

        // Check database if using database sessions/tokens
        $sessionDriver = config('mcp.transports.http.session.driver');
        $tokenDriver = config('mcp.auth.tokens.storage_driver');
        
        if ($sessionDriver === 'database' || $tokenDriver === 'database') {
            try {
                \DB::connection()->getPdo();
                $health['checks']['database'] = 'ok';
            } catch (\Throwable) {
                $health['checks']['database'] = 'error';
                $health['status'] = 'unhealthy';
            }
        }

        $statusCode = match($health['status']) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
            default => 200,
        };

        return response()->json($health, $statusCode);
    }

    /**
     * List registered tools.
     */
    public function listTools(): JsonResponse
    {
        try {
            // This is a simplified version - in the full implementation,
            // this would use the server's listTools method
            return response()->json([
                'tools' => [
                    // Mock data for now - would be populated by server
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->createErrorResponse($e);
        }
    }

    /**
     * List registered resources.
     */
    public function listResources(): JsonResponse
    {
        try {
            // This is a simplified version - in the full implementation,
            // this would use the server's listResources method
            return response()->json([
                'resources' => [
                    // Mock data for now - would be populated by server
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->createErrorResponse($e);
        }
    }

    /**
     * Validate incoming request.
     */
    protected function validateRequest(Request $request): void
    {
        if (!$request->isJson()) {
            throw new \InvalidArgumentException('Request must be JSON');
        }

        $data = $request->json()->all();
        
        if (!isset($data['jsonrpc'])) {
            throw new \InvalidArgumentException('Missing jsonrpc field');
        }

        if ($data['jsonrpc'] !== '2.0') {
            throw new \InvalidArgumentException('Invalid jsonrpc version');
        }

        // Check request size
        $maxSize = config('mcp.transports.http.security.max_request_size', 10 * 1024 * 1024);
        if (strlen($request->getContent()) > $maxSize) {
            throw new \InvalidArgumentException('Request too large');
        }

        // Check allowed hosts
        $allowedHosts = config('mcp.transports.http.security.allowed_hosts', []);
        if (!empty($allowedHosts) && !in_array($request->getHost(), $allowedHosts)) {
            throw new \InvalidArgumentException('Host not allowed');
        }
    }

    /**
     * Get transport options from configuration.
     */
    protected function getTransportOptions(): StreamableHttpServerTransportOptions
    {
        return new StreamableHttpServerTransportOptions(
            sessionManagement: [
                'driver' => config('mcp.transports.http.session.driver', 'cache'),
                'lifetime' => config('mcp.transports.http.session.lifetime', 3600),
            ],
            security: [
                'allowedHosts' => config('mcp.transports.http.security.allowed_hosts', []),
                'maxRequestSize' => config('mcp.transports.http.security.max_request_size', 10 * 1024 * 1024),
                'dnsRebindingProtection' => config('mcp.transports.http.security.dns_rebinding_protection', true),
            ],
            sse: [
                'enabled' => config('mcp.transports.http.sse.enabled', true),
                'keepaliveInterval' => config('mcp.transports.http.sse.keepalive_interval', 30),
                'maxConnections' => config('mcp.transports.http.sse.max_connections', 100),
            ]
        );
    }

    /**
     * Create error response.
     */
    protected function createErrorResponse(\Throwable $e, ?Request $request = null): JsonResponse
    {
        $error = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $this->getErrorCode($e),
                'message' => $e->getMessage(),
            ],
            'id' => null,
        ];

        // Add request ID if available
        if ($request && $request->has('id')) {
            $error['id'] = $request->input('id');
        }

        // Add debug info in development
        if (config('app.debug') && config('mcp.development.debug')) {
            $error['error']['data'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        }

        return response()->json($error, $this->getHttpStatusCode($e));
    }

    /**
     * Get JSON-RPC error code for exception.
     */
    protected function getErrorCode(\Throwable $e): int
    {
        return match(true) {
            $e instanceof \InvalidArgumentException => -32602, // Invalid params
            $e instanceof \BadMethodCallException => -32601, // Method not found
            $e instanceof \ParseError => -32700, // Parse error
            default => -32603, // Internal error
        };
    }

    /**
     * Get HTTP status code for exception.
     */
    protected function getHttpStatusCode(\Throwable $e): int
    {
        return match(true) {
            $e instanceof \InvalidArgumentException => 400,
            $e instanceof \BadMethodCallException => 404,
            $e instanceof \UnauthorizedHttpException => 401,
            $e instanceof \AccessDeniedHttpException => 403,
            default => 500,
        };
    }

    /**
     * Log request if logging is enabled.
     */
    protected function logRequest(Request $request, array $result): void
    {
        if (!config('mcp.logging.log_requests', false)) {
            return;
        }

        Log::channel(config('mcp.logging.channel'))
            ->info('MCP HTTP Request', [
                'method' => $request->json('method'),
                'id' => $request->json('id'),
                'params_count' => count($request->json('params', [])),
                'response_size' => strlen(json_encode($result)),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
    }

    /**
     * Log error.
     */
    protected function logError(Request $request, \Throwable $e): void
    {
        if (!config('mcp.logging.log_errors', true)) {
            return;
        }

        Log::channel(config('mcp.logging.channel'))
            ->error('MCP HTTP Error', [
                'error' => $e->getMessage(),
                'method' => $request->json('method'),
                'id' => $request->json('id'),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
    }
}