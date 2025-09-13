<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\McpController;

/**
 * Example Laravel routes for MCP integration
 *
 * This file shows how to set up routes for handling MCP requests
 * when using the core PHP MCP SDK with Laravel.
 */

// MCP HTTP endpoint
Route::prefix('mcp')->group(function () {
    // Main MCP endpoint for JSON-RPC requests
    Route::post('/', [McpController::class, 'handle'])
        ->name('mcp.handle');

    // Server information endpoint
    Route::get('/info', [McpController::class, 'info'])
        ->name('mcp.info');

    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'mcp_version' => '2024-11-05'
        ]);
    })->name('mcp.health');

    // Tools endpoints (RESTful style for easier testing)
    Route::prefix('tools')->group(function () {
        Route::get('/', [McpController::class, 'listTools'])
            ->name('mcp.tools.list');

        Route::post('/{tool}/call', [McpController::class, 'callTool'])
            ->name('mcp.tools.call');
    });

    // Resources endpoints
    Route::prefix('resources')->group(function () {
        Route::get('/', [McpController::class, 'listResources'])
            ->name('mcp.resources.list');

        Route::get('/{resource}', [McpController::class, 'getResource'])
            ->name('mcp.resources.get');
    });
});

// Optional: Add middleware for authentication, rate limiting, etc.
/*
Route::prefix('mcp')
    ->middleware(['auth:api', 'throttle:100,1']) // 100 requests per minute
    ->group(function () {
        // Your MCP routes here
    });
*/
