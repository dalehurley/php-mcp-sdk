<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use PHPUnit\Framework\TestCase;
use MCP\Shared\LaravelIntegration;

class LaravelIntegrationTest extends TestCase
{
    public function testCreateHttpRouteHandlerConfig(): void
    {
        $config = LaravelIntegration::createHttpRouteHandlerConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('transport_options', $config);
        $this->assertArrayHasKey('example_usage', $config);

        $transportOptions = $config['transport_options'];
        $this->assertEquals(['localhost', '127.0.0.1'], $transportOptions['allowedHosts']);
        $this->assertTrue($transportOptions['enableDnsRebindingProtection']);
        $this->assertFalse($transportOptions['enableJsonResponse']);
    }

    public function testCreateHttpRouteHandlerConfigWithCustomOptions(): void
    {
        $options = [
            'allowedHosts' => ['example.com'],
            'enableJsonResponse' => true,
            'enableDnsRebindingProtection' => false
        ];

        $config = LaravelIntegration::createHttpRouteHandlerConfig($options);

        $transportOptions = $config['transport_options'];
        $this->assertEquals(['example.com'], $transportOptions['allowedHosts']);
        $this->assertFalse($transportOptions['enableDnsRebindingProtection']);
        $this->assertTrue($transportOptions['enableJsonResponse']);
    }

    public function testCreateAuthMiddlewareConfig(): void
    {
        $config = LaravelIntegration::createAuthMiddlewareConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('middleware_class', $config);
        $this->assertArrayHasKey('config', $config);
        $this->assertArrayHasKey('example_implementation', $config);

        $this->assertEquals('McpAuthMiddleware', $config['middleware_class']);
    }

    public function testCreateServiceProviderConfig(): void
    {
        $config = LaravelIntegration::createServiceProviderConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('bindings', $config);
        $this->assertArrayHasKey('config_file', $config);
        $this->assertArrayHasKey('config_structure', $config);

        $configStructure = $config['config_structure'];
        $this->assertEquals(['localhost', '127.0.0.1'], $configStructure['allowed_hosts']);
        $this->assertEquals(3600, $configStructure['session_timeout']);
        $this->assertEquals(4 * 1024 * 1024, $configStructure['max_message_size']);
    }

    public function testExtractAuthInfo(): void
    {
        $requestData = [
            'headers' => [
                'Authorization' => 'Bearer test-token',
                'X-API-Key' => 'api-key-123',
                'X-MCP-Session-ID' => 'session-456'
            ],
            'query' => [],
            'user' => [
                'id' => 1,
                'email' => 'test@example.com'
            ]
        ];

        $authInfo = LaravelIntegration::extractAuthInfo($requestData);

        $this->assertEquals('test-token', $authInfo['bearer_token']);
        $this->assertEquals('api-key-123', $authInfo['api_key']);
        $this->assertEquals('session-456', $authInfo['session_id']);
        $this->assertEquals(['id' => 1, 'email' => 'test@example.com'], $authInfo['user']);
    }

    public function testExtractAuthInfoWithCustomFields(): void
    {
        $requestData = [
            'headers' => [
                'X-Custom-Auth' => 'custom-value'
            ]
        ];

        $config = [
            'custom_fields' => [
                'custom_auth' => 'X-Custom-Auth'
            ]
        ];

        $authInfo = LaravelIntegration::extractAuthInfo($requestData, $config);

        $this->assertEquals('custom-value', $authInfo['custom_auth']);
    }

    public function testCreateArtisanCommandConfig(): void
    {
        $config = LaravelIntegration::createArtisanCommandConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('signature', $config);
        $this->assertArrayHasKey('description', $config);
        $this->assertArrayHasKey('example_implementation', $config);

        $this->assertEquals('mcp:serve {--host=127.0.0.1} {--port=8080}', $config['signature']);
        $this->assertEquals('Start MCP server', $config['description']);
    }

    public function testCreateArtisanCommandConfigWithCustomValues(): void
    {
        $signature = 'mcp:start {--bind=0.0.0.0}';
        $description = 'Start custom MCP server';

        $config = LaravelIntegration::createArtisanCommandConfig($signature, $description);

        $this->assertEquals($signature, $config['signature']);
        $this->assertEquals($description, $config['description']);
    }

    public function testGetValidationRules(): void
    {
        $rules = LaravelIntegration::getValidationRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('jsonrpc', $rules);
        $this->assertArrayHasKey('method', $rules);
        $this->assertArrayHasKey('params', $rules);
        $this->assertArrayHasKey('id', $rules);
        $this->assertArrayHasKey('result', $rules);
        $this->assertArrayHasKey('error', $rules);

        $this->assertEquals('required|in:2.0', $rules['jsonrpc']);
        $this->assertEquals('required_without:result,error|string', $rules['method']);
    }

    public function testGetExampleControllerMethods(): void
    {
        $methods = LaravelIntegration::getExampleControllerMethods();

        $this->assertIsArray($methods);
        $this->assertArrayHasKey('handle_mcp_request', $methods);
        $this->assertArrayHasKey('mcp_sse_endpoint', $methods);
        $this->assertArrayHasKey('mcp_websocket_upgrade', $methods);

        $this->assertStringContainsString('public function handle(Request $request)', $methods['handle_mcp_request']);
        $this->assertStringContainsString('text/event-stream', $methods['mcp_sse_endpoint']);
        $this->assertStringContainsString('WebSocket transport not yet implemented', $methods['mcp_websocket_upgrade']);
    }
}
