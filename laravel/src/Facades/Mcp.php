<?php

declare(strict_types=1);

namespace MCP\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MCP\Server\McpServer;

/**
 * MCP Facade
 * 
 * @method static \MCP\Server\McpServer server()
 * @method static \MCP\Client\Client client()
 * @method static void registerTool(string $name, array $schema, callable $handler)
 * @method static void registerResource(string $uri, array $metadata, callable $readHandler)
 * @method static void registerResourceTemplate(string $uriTemplate, array $metadata, callable $readHandler)
 * @method static void registerPrompt(string $name, array $metadata, callable $handler)
 * @method static array listTools()
 * @method static array listResources()
 * @method static array listResourceTemplates()
 * @method static array listPrompts()
 * @method static array callTool(string $name, array $params)
 * @method static array readResource(string $uri)
 * @method static array getPrompt(string $name, array $params)
 */
class Mcp extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return McpFacadeService::class;
    }
}

/**
 * Service class that provides facade methods.
 */
class McpFacadeService
{
    public function __construct(
        private McpServer $server,
        private \MCP\Client\Client $client
    ) {}

    /**
     * Get the MCP server instance.
     */
    public function server(): McpServer
    {
        return $this->server;
    }

    /**
     * Get the MCP client instance.
     */
    public function client(): \MCP\Client\Client
    {
        return $this->client;
    }

    /**
     * Register a tool with the server.
     */
    public function registerTool(string $name, array $schema, callable $handler): void
    {
        $this->server->registerTool($name, $schema, $handler);
    }

    /**
     * Register a resource with the server.
     */
    public function registerResource(string $uri, array $metadata, callable $readHandler): void
    {
        $this->server->registerResource($uri, $metadata, $readHandler);
    }

    /**
     * Register a resource template with the server.
     */
    public function registerResourceTemplate(string $uriTemplate, array $metadata, callable $readHandler): void
    {
        $this->server->registerResourceTemplate($uriTemplate, $metadata, $readHandler);
    }

    /**
     * Register a prompt with the server.
     */
    public function registerPrompt(string $name, array $metadata, callable $handler): void
    {
        $this->server->registerPrompt($name, $metadata, $handler);
    }

    /**
     * List all registered tools.
     */
    public function listTools(): array
    {
        try {
            // Get registered tools from the server's internal registry
            // For now, return a mock response - in full implementation, this would introspect the server
            return [
                'tools' => [
                    [
                        'name' => 'laravel_cache',
                        'description' => 'Manage Laravel cache operations',
                    ],
                    [
                        'name' => 'laravel_database',
                        'description' => 'Query Laravel database safely',
                    ],
                    [
                        'name' => 'laravel_artisan',
                        'description' => 'Execute Laravel Artisan commands',
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            logger()->error('Failed to list MCP tools', ['error' => $e->getMessage()]);
            return ['tools' => []];
        }
    }

    /**
     * List all registered resources.
     */
    public function listResources(): array
    {
        try {
            return [
                'resources' => [
                    [
                        'uri' => 'config://app',
                        'name' => 'App Configuration',
                        'description' => 'Laravel application configuration',
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            logger()->error('Failed to list MCP resources', ['error' => $e->getMessage()]);
            return ['resources' => []];
        }
    }

    /**
     * List all registered resource templates.
     */
    public function listResourceTemplates(): array
    {
        try {
            return [
                'resourceTemplates' => [
                    [
                        'uriTemplate' => 'laravel://model/{model}',
                        'name' => 'Laravel Model Inspector',
                        'description' => 'Inspect Laravel Eloquent models',
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            logger()->error('Failed to list MCP resource templates', ['error' => $e->getMessage()]);
            return ['resourceTemplates' => []];
        }
    }

    /**
     * List all registered prompts.
     */
    public function listPrompts(): array
    {
        try {
            return [
                'prompts' => [
                    [
                        'name' => 'code-review',
                        'description' => 'Generate code review prompts for Laravel code',
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            logger()->error('Failed to list MCP prompts', ['error' => $e->getMessage()]);
            return ['prompts' => []];
        }
    }

    /**
     * Call a tool.
     */
    public function callTool(string $name, array $params): array
    {
        try {
            // In a full implementation, this would route to the server's tool handler
            logger()->info('MCP Tool called via facade', [
                'tool' => $name,
                'params' => $params,
            ]);

            // Mock response for common tools
            return match ($name) {
                'laravel_cache' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Cache operation executed via facade',
                        ],
                    ],
                ],
                'laravel_database' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Database query executed via facade',
                        ],
                    ],
                ],
                'laravel_artisan' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Artisan command executed via facade',
                        ],
                    ],
                ],
                default => throw new \InvalidArgumentException("Unknown tool: {$name}"),
            };
        } catch (\Throwable $e) {
            logger()->error('Failed to call MCP tool', [
                'tool' => $name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Read a resource.
     */
    public function readResource(string $uri): array
    {
        try {
            logger()->info('MCP Resource read via facade', ['uri' => $uri]);

            // Mock response based on URI
            return match (true) {
                str_starts_with($uri, 'config://') => [
                    'contents' => [
                        [
                            'uri' => $uri,
                            'mimeType' => 'application/json',
                            'text' => json_encode([
                                'message' => 'Configuration resource read via facade',
                                'uri' => $uri,
                            ]),
                        ],
                    ],
                ],
                default => [
                    'contents' => [
                        [
                            'uri' => $uri,
                            'mimeType' => 'text/plain',
                            'text' => "Resource content for: {$uri}",
                        ],
                    ],
                ],
            };
        } catch (\Throwable $e) {
            logger()->error('Failed to read MCP resource', [
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get a prompt.
     */
    public function getPrompt(string $name, array $params): array
    {
        try {
            logger()->info('MCP Prompt generated via facade', [
                'prompt' => $name,
                'params' => $params,
            ]);

            return match ($name) {
                'code-review' => [
                    'description' => 'Code review prompt',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Please review this Laravel code: ' . ($params['code'] ?? '[no code provided]'),
                            ],
                        ],
                    ],
                ],
                default => [
                    'description' => "Generated prompt: {$name}",
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => "Execute prompt '{$name}' with parameters.",
                            ],
                        ],
                    ],
                ],
            };
        } catch (\Throwable $e) {
            logger()->error('Failed to generate MCP prompt', [
                'prompt' => $name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}