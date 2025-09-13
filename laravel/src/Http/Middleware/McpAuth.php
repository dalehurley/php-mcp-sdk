<?php

declare(strict_types=1);

namespace MCP\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MCP\Server\Auth\AuthInfo;
use MCP\Server\Auth\DefaultAuthInfo;

class McpAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$scopes)
    {
        try {
            // Skip auth if disabled
            if (!config('mcp.auth.enabled', false)) {
                return $next($request);
            }

            // Extract bearer token
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->unauthorizedResponse('Missing access token');
            }

            // Validate token
            $authInfo = $this->validateToken($token);
            if (!$authInfo) {
                return $this->unauthorizedResponse('Invalid access token');
            }

            // Check required scopes
            if (!empty($scopes) && !$this->hasRequiredScopes($authInfo, $scopes)) {
                return $this->forbiddenResponse('Insufficient scope');
            }

            // Add auth info to request
            $request->attributes->set('mcp.auth', $authInfo);
            $request->attributes->set('mcp.user_id', $authInfo->getUserId());
            $request->attributes->set('mcp.client_id', $authInfo->getClientId());
            $request->attributes->set('mcp.scopes', $authInfo->getScopes());

            return $next($request);

        } catch (\Throwable $e) {
            Log::channel(config('mcp.logging.channel'))->error('MCP auth middleware error', [
                'error' => $e->getMessage(),
                'request_path' => $request->path(),
            ]);

            return $this->unauthorizedResponse('Authentication error');
        }
    }

    /**
     * Extract bearer token from request.
     */
    protected function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // Also check query parameter for SSE connections
        if ($request->query('access_token')) {
            return $request->query('access_token');
        }

        return null;
    }

    /**
     * Validate access token and return auth info.
     */
    protected function validateToken(string $token): ?AuthInfo
    {
        $driver = config('mcp.auth.tokens.storage_driver', 'cache');
        
        $tokenData = match ($driver) {
            'cache' => Cache::get("mcp:auth:access:{$token}"),
            'database' => $this->getTokenFromDatabase($token),
            'redis' => $this->getTokenFromRedis($token),
            default => null,
        };

        if (!$tokenData) {
            return null;
        }

        return new DefaultAuthInfo(
            userId: $tokenData['user_id'] ?? 'anonymous',
            clientId: $tokenData['client_id'] ?? '',
            scopes: $tokenData['scopes'] ?? [],
            tokenType: 'Bearer',
            token: $token
        );
    }

    /**
     * Get token data from database.
     */
    protected function getTokenFromDatabase(string $token): ?array
    {
        try {
            $record = \DB::table('mcp_access_tokens')
                ->where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            if (!$record) {
                return null;
            }

            return json_decode($record->data, true);
        } catch (\Throwable $e) {
            Log::channel(config('mcp.logging.channel'))->warning('Database token lookup failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get token data from Redis.
     */
    protected function getTokenFromRedis(string $token): ?array
    {
        try {
            $redis = app('redis')->connection('default');
            $data = $redis->get("mcp:auth:access:{$token}");
            
            if (!$data) {
                return null;
            }

            return json_decode($data, true);
        } catch (\Throwable $e) {
            Log::channel(config('mcp.logging.channel'))->warning('Redis token lookup failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if auth info has required scopes.
     */
    protected function hasRequiredScopes(AuthInfo $authInfo, array $requiredScopes): bool
    {
        $tokenScopes = $authInfo->getScopes();
        
        // Check if all required scopes are present
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001, // MCP unauthorized error code
                'message' => $message,
            ],
            'id' => null,
        ], 401);
    }

    /**
     * Return forbidden response.
     */
    protected function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32002, // MCP forbidden error code
                'message' => $message,
            ],
            'id' => null,
        ], 403);
    }
}