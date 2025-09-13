<?php

declare(strict_types=1);

namespace MCP\Laravel\Tests\Unit;

use MCP\Laravel\Tools\BaseTool;
use MCP\Laravel\Resources\BaseResource;
use MCP\Laravel\Prompts\BasePrompt;
use MCP\Laravel\Tests\TestCase;

class BaseClassesTest extends TestCase
{
    public function test_base_tool_interface(): void
    {
        $tool = new class extends BaseTool {
            public function name(): string { return 'test-tool'; }
            public function description(): string { return 'Test tool'; }
            public function inputSchema(): array { return ['type' => 'object']; }
            public function handle(array $params): array { return ['result' => 'ok']; }
        };

        $this->assertEquals('test-tool', $tool->name());
        $this->assertEquals('Test tool', $tool->description());
        $this->assertEquals(['type' => 'object'], $tool->inputSchema());
        $this->assertEquals(['result' => 'ok'], $tool->handle([]));

        $array = $tool->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('inputSchema', $array);
        $this->assertEquals('test-tool', $array['name']);
    }

    public function test_base_tool_with_title_and_annotations(): void
    {
        $tool = new class extends BaseTool {
            public function name(): string { return 'test-tool'; }
            public function description(): string { return 'Test tool'; }
            public function inputSchema(): array { return ['type' => 'object']; }
            public function handle(array $params): array { return ['result' => 'ok']; }
            public function title(): ?string { return 'Test Tool Title'; }
            public function annotations(): array { return ['category' => 'test']; }
        };

        $array = $tool->toArray();
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('annotations', $array);
        $this->assertEquals('Test Tool Title', $array['title']);
        $this->assertEquals(['category' => 'test'], $array['annotations']);
    }

    public function test_base_resource_interface(): void
    {
        $resource = new class extends BaseResource {
            public function uri(): string { return 'test://resource'; }
            public function name(): string { return 'test-resource'; }
            public function description(): string { return 'Test resource'; }
            public function read(string $uri): array { return ['contents' => []]; }
        };

        $this->assertEquals('test://resource', $resource->uri());
        $this->assertEquals('test-resource', $resource->name());
        $this->assertEquals('Test resource', $resource->description());
        $this->assertEquals(['contents' => []], $resource->read('test://resource'));
        $this->assertFalse($resource->supportsTemplates());

        $array = $resource->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('uri', $array);
        $this->assertArrayNotHasKey('uriTemplate', $array);
    }

    public function test_base_resource_with_templates(): void
    {
        $resource = new class extends BaseResource {
            public function uri(): string { return 'test://resource/{id}'; }
            public function name(): string { return 'test-resource'; }
            public function description(): string { return 'Test resource'; }
            public function read(string $uri): array { return ['contents' => []]; }
        };

        $this->assertTrue($resource->supportsTemplates());
        $this->assertEquals('test://resource/{id}', $resource->uriTemplate());

        $array = $resource->toArray();
        $this->assertArrayHasKey('uriTemplate', $array);
        $this->assertArrayNotHasKey('uri', $array);
    }

    public function test_base_prompt_interface(): void
    {
        $prompt = new class extends BasePrompt {
            public function name(): string { return 'test-prompt'; }
            public function description(): string { return 'Test prompt'; }
            public function arguments(): array { return [['name' => 'input', 'required' => true]]; }
            public function handle(array $params): array {
                return [
                    'messages' => [
                        ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'test']]
                    ]
                ];
            }
        };

        $this->assertEquals('test-prompt', $prompt->name());
        $this->assertEquals('Test prompt', $prompt->description());
        $this->assertEquals([['name' => 'input', 'required' => true]], $prompt->arguments());

        $result = $prompt->handle(['input' => 'test']);
        $this->assertArrayHasKey('messages', $result);
        $this->assertIsArray($result['messages']);

        $array = $prompt->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('arguments', $array);
    }

    public function test_base_prompt_helper_methods(): void
    {
        $prompt = new class extends BasePrompt {
            public function name(): string { return 'test'; }
            public function description(): string { return 'test'; }
            public function arguments(): array { return []; }
            public function handle(array $params): array {
                return [
                    'messages' => [
                        $this->createUserMessage('Hello'),
                        $this->createAssistantMessage('Hi'),
                        $this->createSystemMessage('System'),
                    ]
                ];
            }
        };

        $result = $prompt->handle([]);
        $messages = $result['messages'];

        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Hello', $messages[0]['content']['text']);

        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertEquals('Hi', $messages[1]['content']['text']);

        $this->assertEquals('system', $messages[2]['role']);
        $this->assertEquals('System', $messages[2]['content']['text']);
    }

    public function test_base_tool_caching_configuration(): void
    {
        $tool = new class extends BaseTool {
            public function name(): string { return 'test'; }
            public function description(): string { return 'test'; }
            public function inputSchema(): array { return []; }
            public function handle(array $params): array { return []; }
            public function cacheable(): bool { return true; }
            public function cacheTtl(): int { return 600; }
        };

        $this->assertTrue($tool->cacheable());
        $this->assertEquals(600, $tool->cacheTtl());
    }

    public function test_base_tool_auth_configuration(): void
    {
        $tool = new class extends BaseTool {
            public function name(): string { return 'test'; }
            public function description(): string { return 'test'; }
            public function inputSchema(): array { return []; }
            public function handle(array $params): array { return []; }
            public function requiresAuth(): bool { return true; }
            public function requiredScopes(): array { return ['mcp:tools', 'custom:scope']; }
        };

        $this->assertTrue($tool->requiresAuth());
        $this->assertEquals(['mcp:tools', 'custom:scope'], $tool->requiredScopes());
    }

    public function test_base_resource_subscription_support(): void
    {
        $resource = new class extends BaseResource {
            public function uri(): string { return 'test://resource'; }
            public function name(): string { return 'test'; }
            public function description(): string { return 'test'; }
            public function read(string $uri): array { return []; }
            public function subscribable(): bool { return true; }
        };

        $this->assertTrue($resource->subscribable());
    }
}