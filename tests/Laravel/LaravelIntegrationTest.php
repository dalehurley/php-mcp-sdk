<?php

declare(strict_types=1);

namespace MCP\Tests\Laravel;

use PHPUnit\Framework\TestCase;
use MCP\Shared\LaravelIntegration;

class LaravelIntegrationTest extends TestCase
{
    public function testLaravelIntegrationClassExists(): void
    {
        $this->assertTrue(class_exists(LaravelIntegration::class));
    }

    public function testCreateHttpRouteHandlerConfig(): void
    {
        $config = LaravelIntegration::createHttpRouteHandlerConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('transport_options', $config);
    }

    public function testCreateAuthMiddlewareConfig(): void
    {
        $config = LaravelIntegration::createAuthMiddlewareConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('middleware_class', $config);
        $this->assertArrayHasKey('config', $config);
    }

    public function testCreateServiceProviderConfig(): void
    {
        $config = LaravelIntegration::createServiceProviderConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('bindings', $config);
        $this->assertArrayHasKey('config_file', $config);
        $this->assertArrayHasKey('config_structure', $config);
        $this->assertArrayHasKey('example_service_provider', $config);
    }

    public function testCreateArtisanCommandConfig(): void
    {
        $config = LaravelIntegration::createArtisanCommandConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('signature', $config);
        $this->assertArrayHasKey('description', $config);
        $this->assertArrayHasKey('example_implementation', $config);
    }

    public function testCreateHttpRouteHandlerConfigWithOptions(): void
    {
        $options = [
            'enable_json_response' => true,
            'session_id_generator' => 'custom_generator'
        ];

        $config = LaravelIntegration::createHttpRouteHandlerConfig($options);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('transport_options', $config);
    }

    public function testCreateAuthMiddlewareConfigWithCustomConfig(): void
    {
        $customConfig = [
            'provider' => 'oauth',
            'client_id' => 'test-client'
        ];

        $config = LaravelIntegration::createAuthMiddlewareConfig($customConfig);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('middleware_class', $config);
    }
}
