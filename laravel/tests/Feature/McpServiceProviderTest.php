<?php

declare(strict_types=1);

namespace MCP\Laravel\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MCP\Server\McpServer;
use MCP\Client\Client;
use MCP\Laravel\Facades\Mcp;
use MCP\Laravel\Facades\McpFacadeService;
use MCP\Laravel\Http\Controllers\McpController;
use MCP\Laravel\Http\Middleware\McpAuth;
use MCP\Laravel\Tests\TestCase;

class McpServiceProviderTest extends TestCase
{
    public function test_mcp_server_is_registered(): void
    {
        $this->assertTrue($this->app->bound(McpServer::class));
        $this->assertInstanceOf(McpServer::class, $this->app->make(McpServer::class));
    }

    public function test_mcp_client_is_registered(): void
    {
        $this->assertTrue($this->app->bound(Client::class));
        $this->assertInstanceOf(Client::class, $this->app->make(Client::class));
    }

    public function test_mcp_controller_is_registered(): void
    {
        $this->assertTrue($this->app->bound(McpController::class));
        $this->assertInstanceOf(McpController::class, $this->app->make(McpController::class));
    }

    public function test_mcp_middleware_is_registered(): void
    {
        $this->assertTrue($this->app->bound(McpAuth::class));
        $this->assertInstanceOf(McpAuth::class, $this->app->make(McpAuth::class));
    }

    public function test_mcp_facade_service_is_registered(): void
    {
        $this->assertTrue($this->app->bound(McpFacadeService::class));
        $this->assertInstanceOf(McpFacadeService::class, $this->app->make(McpFacadeService::class));
    }

    public function test_facade_works(): void
    {
        $server = Mcp::server();
        $client = Mcp::client();

        $this->assertInstanceOf(McpServer::class, $server);
        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_routes_are_loaded_when_enabled(): void
    {
        config(['mcp.routes.enabled' => true]);
        
        // Reload routes
        $this->app->make('router')->getRoutes()->refreshActionLookups();
        
        $this->assertTrue(
            $this->app->make('router')->getRoutes()->hasNamedRoute('mcp.handle')
        );
        $this->assertTrue(
            $this->app->make('router')->getRoutes()->hasNamedRoute('mcp.info')
        );
    }

    public function test_routes_are_not_loaded_when_disabled(): void
    {
        config(['mcp.routes.enabled' => false]);
        
        // Reload the service provider
        $provider = new \MCP\Laravel\McpServiceProvider($this->app);
        $provider->boot();
        
        $this->assertFalse(
            $this->app->make('router')->getRoutes()->hasNamedRoute('mcp.handle')
        );
    }

    public function test_commands_are_registered(): void
    {
        $commands = \Illuminate\Console\Application::all();
        
        $this->assertArrayHasKey('mcp:server', $commands);
        $this->assertArrayHasKey('mcp:install', $commands);
        $this->assertArrayHasKey('mcp:make-tool', $commands);
        $this->assertArrayHasKey('mcp:make-resource', $commands);
        $this->assertArrayHasKey('mcp:make-prompt', $commands);
    }

    public function test_middleware_is_aliased(): void
    {
        $router = $this->app->make('router');
        $middleware = $router->getMiddleware();
        
        $this->assertArrayHasKey('mcp.auth', $middleware);
        $this->assertEquals(McpAuth::class, $middleware['mcp.auth']);
    }
}