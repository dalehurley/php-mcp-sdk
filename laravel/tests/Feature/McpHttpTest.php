<?php

declare(strict_types=1);

namespace MCP\Laravel\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MCP\Laravel\Tests\TestCase;

class McpHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable routes for testing
        config(['mcp.routes.enabled' => true]);
    }

    public function test_info_endpoint_returns_server_information(): void
    {
        $response = $this->getJson('/mcp/info');

        $response->assertOk()
            ->assertJsonStructure([
                'server' => [
                    'name',
                    'version',
                    'capabilities',
                    'transport',
                ],
                'laravel' => [
                    'version',
                    'environment',
                ],
            ]);
    }

    public function test_health_endpoint_returns_health_status(): void
    {
        $response = $this->getJson('/mcp/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks',
            ]);
    }

    public function test_handle_endpoint_requires_json_rpc(): void
    {
        $response = $this->postJson('/mcp/', [
            'not' => 'jsonrpc',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Missing jsonrpc field',
                ],
            ]);
    }

    public function test_handle_endpoint_validates_json_rpc_version(): void
    {
        $response = $this->postJson('/mcp/', [
            'jsonrpc' => '1.0',
            'method' => 'test',
            'id' => 1,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid jsonrpc version',
                ],
            ]);
    }

    public function test_handle_endpoint_accepts_valid_json_rpc(): void
    {
        // Disable auth for this test
        config(['mcp.auth.enabled' => false]);
        
        $response = $this->postJson('/mcp/', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ]);

        // Should not get a validation error (might get method not found, which is fine)
        $response->assertDontSeeText('Missing jsonrpc field');
        $response->assertDontSeeText('Invalid jsonrpc version');
    }

    public function test_tools_endpoint_requires_auth_when_enabled(): void
    {
        config(['mcp.auth.enabled' => true]);
        
        $response = $this->getJson('/mcp/tools');

        $response->assertStatus(401)
            ->assertJsonStructure([
                'jsonrpc',
                'error' => [
                    'code',
                    'message',
                ],
            ]);
    }

    public function test_tools_endpoint_works_without_auth_when_disabled(): void
    {
        config(['mcp.auth.enabled' => false]);
        
        $response = $this->getJson('/mcp/tools');

        $response->assertOk()
            ->assertJsonStructure([
                'tools',
            ]);
    }

    public function test_resources_endpoint_requires_auth_when_enabled(): void
    {
        config(['mcp.auth.enabled' => true]);
        
        $response = $this->getJson('/mcp/resources');

        $response->assertStatus(401);
    }

    public function test_resources_endpoint_works_without_auth_when_disabled(): void
    {
        config(['mcp.auth.enabled' => false]);
        
        $response = $this->getJson('/mcp/resources');

        $response->assertOk()
            ->assertJsonStructure([
                'resources',
            ]);
    }

    public function test_sse_endpoint_returns_event_stream(): void
    {
        config(['mcp.auth.enabled' => false]);
        
        $response = $this->get('/mcp/sse');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream')
            ->assertHeader('Cache-Control', 'no-cache');
    }

    public function test_dashboard_endpoint_loads_when_enabled(): void
    {
        config(['mcp.ui.enabled' => true]);
        
        $response = $this->get('/mcp/dashboard');

        $response->assertOk();
    }

    public function test_request_size_limit_is_enforced(): void
    {
        config(['mcp.auth.enabled' => false]);
        config(['mcp.transports.http.security.max_request_size' => 100]); // 100 bytes
        
        $largePayload = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => ['data' => str_repeat('x', 1000)], // > 100 bytes
            'id' => 1,
        ];

        $response = $this->postJson('/mcp/', $largePayload);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'jsonrpc',
                'error' => [
                    'code',
                    'message',
                ],
            ]);
    }

    public function test_host_validation_works(): void
    {
        config(['mcp.auth.enabled' => false]);
        config(['mcp.transports.http.security.allowed_hosts' => ['localhost']]);
        
        // This will use the default test host which should be localhost
        $response = $this->postJson('/mcp/', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ]);

        // Should not get host validation error
        $response->assertDontSeeText('Host not allowed');
    }
}