<?php

declare(strict_types=1);

namespace MCP\Laravel\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use MCP\Laravel\McpServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up basic configuration for tests
        config([
            'mcp.server.name' => 'test-server',
            'mcp.server.version' => '1.0.0',
            'mcp.routes.enabled' => true,
            'mcp.auth.enabled' => false,
            'mcp.cache.enabled' => true,
            'mcp.logging.enabled' => false, // Disable logging in tests
            'mcp.ui.enabled' => true,
        ]);
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Mcp' => \MCP\Laravel\Facades\Mcp::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        // Setup the application configuration
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // MCP-specific configuration
        $app['config']->set('mcp', [
            'server' => [
                'name' => 'test-server',
                'version' => '1.0.0',
                'auto_discover' => [
                    'enabled' => true,
                    'namespaces' => [
                        'App\\Mcp\\Tools' => 'tools',
                        'App\\Mcp\\Resources' => 'resources',
                        'App\\Mcp\\Prompts' => 'prompts',
                    ],
                ],
                'capabilities' => [],
            ],
            'client' => [
                'name' => 'test-client',
                'version' => '1.0.0',
                'options' => [],
            ],
            'routes' => [
                'enabled' => true,
                'prefix' => 'mcp',
                'middleware' => ['api'],
                'auth_middleware' => ['mcp.auth'],
            ],
            'auth' => [
                'enabled' => false,
                'guard' => 'api',
                'tokens' => [
                    'storage_driver' => 'cache',
                ],
            ],
            'transports' => [
                'http' => [
                    'session' => [
                        'driver' => 'cache',
                        'lifetime' => 3600,
                    ],
                    'security' => [
                        'allowed_hosts' => ['localhost', '127.0.0.1'],
                        'max_request_size' => 10 * 1024 * 1024,
                    ],
                    'sse' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'cache' => [
                'enabled' => true,
                'store' => 'array',
                'ttl' => [
                    'tools' => 300,
                    'resources' => 60,
                    'prompts' => 300,
                ],
            ],
            'logging' => [
                'enabled' => false,
                'channel' => 'testing',
            ],
            'ui' => [
                'enabled' => true,
            ],
        ]);
    }

    /**
     * Create a mock MCP tool for testing.
     */
    protected function createMockTool(string $name = 'test-tool'): object
    {
        return new class($name) extends \MCP\Laravel\Tools\BaseTool {
            private string $toolName;

            public function __construct(string $name)
            {
                $this->toolName = $name;
            }

            public function name(): string
            {
                return $this->toolName;
            }

            public function description(): string
            {
                return "Test tool: {$this->toolName}";
            }

            public function inputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'string'],
                    ],
                ];
            }

            public function handle(array $params): array
            {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Test result: ' . ($params['input'] ?? 'no input'),
                        ],
                    ],
                ];
            }
        };
    }

    /**
     * Create a mock MCP resource for testing.
     */
    protected function createMockResource(string $name = 'test-resource'): object
    {
        return new class($name) extends \MCP\Laravel\Resources\BaseResource {
            private string $resourceName;

            public function __construct(string $name)
            {
                $this->resourceName = $name;
            }

            public function uri(): string
            {
                return "test://{$this->resourceName}";
            }

            public function name(): string
            {
                return $this->resourceName;
            }

            public function description(): string
            {
                return "Test resource: {$this->resourceName}";
            }

            public function read(string $uri): array
            {
                return [
                    'contents' => [
                        [
                            'uri' => $uri,
                            'mimeType' => 'text/plain',
                            'text' => "Test content for {$this->resourceName}",
                        ],
                    ],
                ];
            }
        };
    }

    /**
     * Create a mock MCP prompt for testing.
     */
    protected function createMockPrompt(string $name = 'test-prompt'): object
    {
        return new class($name) extends \MCP\Laravel\Prompts\BasePrompt {
            private string $promptName;

            public function __construct(string $name)
            {
                $this->promptName = $name;
            }

            public function name(): string
            {
                return $this->promptName;
            }

            public function description(): string
            {
                return "Test prompt: {$this->promptName}";
            }

            public function arguments(): array
            {
                return [
                    [
                        'name' => 'query',
                        'description' => 'Query input',
                        'required' => true,
                    ],
                ];
            }

            public function handle(array $params): array
            {
                return [
                    'messages' => [
                        $this->createUserMessage($params['query'] ?? 'test query'),
                    ],
                ];
            }
        };
    }
}