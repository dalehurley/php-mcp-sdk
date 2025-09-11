<?php

declare(strict_types=1);

namespace MCP\Tests\Server;

use MCP\Server\McpServer;
use MCP\Server\ServerOptions;
use MCP\Server\RegisteredTool;
use MCP\Server\RegisteredResource;
use MCP\Server\RegisteredPrompt;
use MCP\Server\ResourceTemplate;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\Tools\ToolAnnotations;
use MCP\Types\McpError;
use MCP\Types\ErrorCode;
use MCP\Shared\RequestHandlerExtra;
use MCP\Shared\UriTemplate;
use PHPUnit\Framework\TestCase;

class McpServerTest extends TestCase
{
    private Implementation $serverInfo;
    private McpServer $server;

    protected function setUp(): void
    {
        $this->serverInfo = new Implementation('test-server', '1.0.0');
        $this->server = new McpServer($this->serverInfo);
    }

    public function testServerConstruction(): void
    {
        $this->assertInstanceOf(McpServer::class, $this->server);
        $this->assertInstanceOf(\MCP\Server\Server::class, $this->server->server);
        $this->assertFalse($this->server->isConnected());
    }

    public function testToolRegistration(): void
    {
        $callback = function (array $args): CallToolResult {
            return new CallToolResult([
                ['type' => 'text', 'text' => 'Result: ' . $args['input']]
            ]);
        };

        $tool = $this->server->registerTool(
            'test-tool',
            [
                'title' => 'Test Tool',
                'description' => 'A test tool',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'string']
                    ],
                    'required' => ['input']
                ]
            ],
            $callback
        );

        $this->assertInstanceOf(RegisteredTool::class, $tool);
        $this->assertEquals('Test Tool', $tool->title);
        $this->assertEquals('A test tool', $tool->description);
        $this->assertTrue($tool->enabled);
    }

    public function testToolRegistrationWithOverloads(): void
    {
        // Test simple callback registration
        $callback = function (): CallToolResult {
            return new CallToolResult([
                ['type' => 'text', 'text' => 'Simple result']
            ]);
        };

        $tool = $this->server->tool('simple-tool', $callback);
        $this->assertInstanceOf(RegisteredTool::class, $tool);
        $this->assertTrue($tool->enabled);

        // Test with description
        $tool2 = $this->server->tool('described-tool', 'Tool description', $callback);
        $this->assertEquals('Tool description', $tool2->description);

        // Test with schema
        $schema = [
            'type' => 'object',
            'properties' => ['param' => ['type' => 'string']]
        ];
        $tool3 = $this->server->tool('schema-tool', 'Description', $schema, $callback);
        $this->assertEquals($schema, $tool3->inputSchema);
    }

    public function testToolDuplicateRegistration(): void
    {
        $callback = function (): CallToolResult {
            return new CallToolResult([]);
        };

        $this->server->registerTool('duplicate', [], $callback);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Tool duplicate is already registered');
        $this->server->registerTool('duplicate', [], $callback);
    }

    public function testResourceRegistration(): void
    {
        $callback = function (string $uri): ReadResourceResult {
            return new ReadResourceResult([
                ['type' => 'text', 'text' => "Content for {$uri}"]
            ]);
        };

        $resource = $this->server->registerResource(
            'test-resource',
            'test://resource',
            [
                'title' => 'Test Resource',
                'description' => 'A test resource',
                'mimeType' => 'text/plain'
            ],
            $callback
        );

        $this->assertInstanceOf(RegisteredResource::class, $resource);
        $this->assertEquals('test-resource', $resource->name);
        $this->assertEquals('Test Resource', $resource->title);
        $this->assertTrue($resource->enabled);
    }

    public function testResourceTemplateRegistration(): void
    {
        $template = new ResourceTemplate(
            'test://files/{path}',
            []
        );

        $callback = function (string $uri, array $variables): ReadResourceResult {
            return new ReadResourceResult([
                ['type' => 'text', 'text' => "File content for {$variables['path']}"]
            ]);
        };

        $resource = $this->server->registerResource(
            'file-template',
            $template,
            ['title' => 'File Template'],
            $callback
        );

        $this->assertInstanceOf(\MCP\Server\RegisteredResourceTemplate::class, $resource);
        $this->assertEquals('File Template', $resource->title);
        $this->assertTrue($resource->enabled);
    }

    public function testPromptRegistration(): void
    {
        $callback = function (array $args): GetPromptResult {
            return new GetPromptResult([
                ['type' => 'text', 'text' => "Prompt with name: {$args['name']}"]
            ]);
        };

        $prompt = $this->server->registerPrompt(
            'test-prompt',
            [
                'title' => 'Test Prompt',
                'description' => 'A test prompt',
                'argsSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string']
                    ],
                    'required' => ['name']
                ]
            ],
            $callback
        );

        $this->assertInstanceOf(RegisteredPrompt::class, $prompt);
        $this->assertEquals('Test Prompt', $prompt->title);
        $this->assertEquals('A test prompt', $prompt->description);
        $this->assertTrue($prompt->enabled);
    }

    public function testPromptRegistrationWithOverloads(): void
    {
        $callback = function (): GetPromptResult {
            return new GetPromptResult([
                ['type' => 'text', 'text' => 'Simple prompt']
            ]);
        };

        // Simple registration
        $prompt1 = $this->server->prompt('simple-prompt', $callback);
        $this->assertInstanceOf(RegisteredPrompt::class, $prompt1);

        // With description
        $prompt2 = $this->server->prompt('described-prompt', 'Description', $callback);
        $this->assertEquals('Description', $prompt2->description);

        // With schema
        $schema = ['type' => 'object', 'properties' => ['param' => ['type' => 'string']]];
        $prompt3 = $this->server->prompt('schema-prompt', 'Description', $schema, $callback);
        $this->assertEquals($schema, $prompt3->argsSchema);
    }

    public function testDynamicEnableDisable(): void
    {
        $callback = function (): CallToolResult {
            return new CallToolResult([]);
        };

        $tool = $this->server->registerTool('dynamic-tool', [], $callback);

        $this->assertTrue($tool->enabled);

        $tool->disable();
        $this->assertFalse($tool->enabled);

        $tool->enable();
        $this->assertTrue($tool->enabled);
    }

    public function testToolUpdate(): void
    {
        $callback = function (): CallToolResult {
            return new CallToolResult([]);
        };

        $tool = $this->server->registerTool('update-tool', ['title' => 'Original'], $callback);

        $this->assertEquals('Original', $tool->title);

        $tool->update(['title' => 'Updated']);
        $this->assertEquals('Updated', $tool->title);
    }

    public function testToolRemoval(): void
    {
        $callback = function (): CallToolResult {
            return new CallToolResult([]);
        };

        $tool = $this->server->registerTool('remove-tool', [], $callback);
        $this->assertInstanceOf(RegisteredTool::class, $tool);

        // Remove should trigger the onRemove callback
        $tool->remove();

        // The tool should still exist as an object, but would be removed from server's registry
        $this->assertInstanceOf(RegisteredTool::class, $tool);
    }

    public function testResourceUpdate(): void
    {
        $callback = function (): ReadResourceResult {
            return new ReadResourceResult([]);
        };

        $resource = $this->server->registerResource(
            'update-resource',
            'test://resource',
            ['title' => 'Original'],
            $callback
        );

        $this->assertEquals('Original', $resource->title);

        $resource->update(['title' => 'Updated']);
        $this->assertEquals('Updated', $resource->title);
    }

    public function testPromptUpdate(): void
    {
        $callback = function (): GetPromptResult {
            return new GetPromptResult([]);
        };

        $prompt = $this->server->registerPrompt(
            'update-prompt',
            ['title' => 'Original'],
            $callback
        );

        $this->assertEquals('Original', $prompt->title);

        $prompt->update(['title' => 'Updated']);
        $this->assertEquals('Updated', $prompt->title);
    }

    public function testSchemaValidation(): void
    {
        // This test would require a mock transport and full request handling
        // For now, we test that the schema validation utilities work
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer']
            ],
            'required' => ['name']
        ];

        // Test schema normalization through the server's utility methods
        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('schemaToJsonSchema');
        $method->setAccessible(true);

        $normalized = $method->invoke($this->server, $schema);
        $this->assertIsArray($normalized);
        $this->assertEquals('object', $normalized['type']);
    }

    public function testPromptArgumentExtraction(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'The name parameter'
                ],
                'optional' => [
                    'type' => 'string',
                    'description' => 'Optional parameter'
                ]
            ],
            'required' => ['name']
        ];

        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('promptArgumentsFromSchema');
        $method->setAccessible(true);

        $arguments = $method->invoke($this->server, $schema);
        $this->assertIsArray($arguments);
        $this->assertCount(2, $arguments);

        // Check that required argument is marked as such
        $nameArg = array_filter($arguments, fn($arg) => $arg['name'] === 'name')[0] ?? null;
        $this->assertNotNull($nameArg);
        $this->assertTrue($nameArg['required'] ?? false);
    }

    public function testSchemaFieldExtraction(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'field1' => ['type' => 'string'],
                'field2' => ['type' => 'number']
            ]
        ];

        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('getSchemaField');
        $method->setAccessible(true);

        $field1 = $method->invoke($this->server, $schema, 'field1');
        $this->assertEquals(['type' => 'string'], $field1);

        $nonExistent = $method->invoke($this->server, $schema, 'nonexistent');
        $this->assertNull($nonExistent);
    }

    public function testIsSchemaDetection(): void
    {
        $reflection = new \ReflectionClass($this->server);
        $method = $reflection->getMethod('isSchema');
        $method->setAccessible(true);

        // Should detect JSON Schema
        $this->assertTrue($method->invoke($this->server, ['type' => 'object']));
        $this->assertTrue($method->invoke($this->server, ['properties' => []]));
        $this->assertTrue($method->invoke($this->server, ['required' => []]));

        // Should not detect non-schema arrays
        $this->assertFalse($method->invoke($this->server, ['title' => 'Not a schema']));
        $this->assertFalse($method->invoke($this->server, 'string'));
        $this->assertFalse($method->invoke($this->server, 123));
    }

    public function testNotificationMethods(): void
    {
        // Test that notification methods return void and don't throw
        $this->server->sendResourceListChanged();
        $this->server->sendToolListChanged();
        $this->server->sendPromptListChanged();

        // If we get here without exceptions, the test passes
        $this->assertTrue(true);
    }

    public function testLoggingMessage(): void
    {
        $params = [
            'level' => 'info',
            'logger' => 'test',
            'data' => 'test message'
        ];

        $future = $this->server->sendLoggingMessage($params);
        $this->assertInstanceOf(\Amp\Future::class, $future);
    }
}
