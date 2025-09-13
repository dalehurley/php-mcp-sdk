<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MCP\Laravel\Http\Controllers\McpController;
use MCP\Laravel\Http\Controllers\McpDashboardController;
use MCP\Laravel\Http\Controllers\McpOAuthController;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| Routes for Model Context Protocol (MCP) endpoints.
| These routes handle JSON-RPC communication, OAuth authentication,
| and optional web-based dashboard interface.
|
*/

$prefix = config('mcp.routes.prefix', 'mcp');
$middleware = config('mcp.routes.middleware', ['api']);
$authMiddleware = array_merge($middleware, config('mcp.routes.auth_middleware', ['mcp.auth']));
$domain = config('mcp.routes.domain');

// Configure route group
$routeConfig = [
    'prefix' => $prefix,
    'middleware' => $middleware,
];

if ($domain) {
    $routeConfig['domain'] = $domain;
}

Route::group($routeConfig, function () use ($authMiddleware) {
    // Core MCP endpoints
    Route::post('/', [McpController::class, 'handle'])
        ->name('mcp.handle')
        ->middleware($authMiddleware);

    Route::get('/sse', [McpController::class, 'sse'])
        ->name('mcp.sse')
        ->middleware($authMiddleware);

    // Information endpoints (no auth required)
    Route::get('/info', [McpController::class, 'info'])
        ->name('mcp.info');

    Route::get('/health', [McpController::class, 'health'])
        ->name('mcp.health');

    // List endpoints (with auth)
    Route::get('/tools', [McpController::class, 'listTools'])
        ->name('mcp.tools')
        ->middleware($authMiddleware);

    Route::get('/resources', [McpController::class, 'listResources'])
        ->name('mcp.resources')
        ->middleware($authMiddleware);

    // OAuth 2.1 endpoints (if auth is enabled)
    if (config('mcp.auth.enabled', false)) {
        Route::group(['prefix' => 'oauth'], function () {
            Route::get('/authorize', [McpOAuthController::class, 'authorize'])
                ->name('mcp.oauth.authorize');

            Route::post('/token', [McpOAuthController::class, 'token'])
                ->name('mcp.oauth.token');

            Route::post('/revoke', [McpOAuthController::class, 'revoke'])
                ->name('mcp.oauth.revoke');

            Route::get('/.well-known/oauth-authorization-server', [McpOAuthController::class, 'metadata'])
                ->name('mcp.oauth.metadata');
        });
    }

    // Dashboard and UI endpoints (if enabled)
    if (config('mcp.ui.enabled', true)) {
        Route::group(['prefix' => 'dashboard'], function () use ($authMiddleware) {
            // Main dashboard
            Route::get('/', [McpDashboardController::class, 'index'])
                ->name('mcp.dashboard');

            // API endpoints for dashboard
            Route::get('/api/stats', [McpDashboardController::class, 'stats'])
                ->name('mcp.dashboard.stats')
                ->middleware($authMiddleware);

            Route::get('/api/logs', [McpDashboardController::class, 'logs'])
                ->name('mcp.dashboard.logs')
                ->middleware($authMiddleware);

            Route::post('/api/test-tool', [McpDashboardController::class, 'testTool'])
                ->name('mcp.dashboard.test-tool')
                ->middleware($authMiddleware);

            Route::post('/api/test-resource', [McpDashboardController::class, 'testResource'])
                ->name('mcp.dashboard.test-resource')
                ->middleware($authMiddleware);

            Route::post('/api/test-prompt', [McpDashboardController::class, 'testPrompt'])
                ->name('mcp.dashboard.test-prompt')
                ->middleware($authMiddleware);
        });

        // Inspector endpoint for development
        if (config('app.debug')) {
            Route::get('/inspect', [McpDashboardController::class, 'inspect'])
                ->name('mcp.inspect');
        }
    }
});

// Rate limiting for MCP routes
if ($rateLimit = config('mcp.routes.rate_limit')) {
    Route::middleware("throttle:{$rateLimit}")
        ->group(function () use ($prefix) {
            // Apply rate limiting to all MCP routes
        });
}

// CORS support for MCP routes (if needed)
if (config('mcp.routes.cors_enabled', false)) {
    Route::options('{any}', function () {
        return response('', 200)
            ->header('Access-Control-Allow-Origin', config('mcp.routes.cors_origin', '*'))
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    })->where('any', '.*');
}