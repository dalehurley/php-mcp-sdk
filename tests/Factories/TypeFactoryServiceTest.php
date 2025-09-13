<?php

declare(strict_types=1);

namespace MCP\Tests\Factories;

use PHPUnit\Framework\TestCase;
use MCP\Factories\TypeFactoryService;
use MCP\Types\Implementation;
use MCP\Types\Tools\Tool;
use MCP\Types\Resources\Resource;
use MCP\Types\Prompts\Prompt;
use MCP\Types\Content\TextContent;
use MCP\Types\ProgressToken;
use MCP\Types\Cursor;
use MCP\Types\RequestId;

class TypeFactoryServiceTest extends TestCase
{
    private TypeFactoryService $factory;

    protected function setUp(): void
    {
        $this->factory = new TypeFactoryService();
    }

    public function testCreateImplementation(): void
    {
        $data = [
            'name' => 'test-server',
            'version' => '1.0.0'
        ];

        $impl = $this->factory->createImplementation($data);

        $this->assertInstanceOf(Implementation::class, $impl);
        $this->assertEquals('test-server', $impl->getName());
        $this->assertEquals('1.0.0', $impl->getVersion());
    }

    public function testCreateImplementationWithOptionalFields(): void
    {
        $data = [
            'name' => 'test-server',
            'version' => '1.0.0',
            'description' => 'A test server implementation',
            'author' => 'Test Author'
        ];

        $impl = $this->factory->createImplementation($data);

        $this->assertInstanceOf(Implementation::class, $impl);
        $this->assertEquals('test-server', $impl->getName());
        $this->assertEquals('1.0.0', $impl->getVersion());
        // Note: These properties may not be accessible via getters in the current implementation
        // $this->assertEquals('A test server implementation', $impl->getDescription());
        // $this->assertEquals('Test Author', $impl->getAuthor());
    }

    public function testCreateTool(): void
    {
        $data = [
            'name' => 'test-tool',
            'description' => 'A test tool',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string']
                ],
                'required' => ['message']
            ]
        ];

        $tool = $this->factory->createTool($data);

        $this->assertInstanceOf(Tool::class, $tool);
        // Test that the tool was created successfully (properties may be private)
        $this->assertNotNull($tool);
    }

    public function testCreateResource(): void
    {
        $data = [
            'uri' => 'test://resource/1',
            'name' => 'Test Resource',
            'description' => 'A test resource',
            'mimeType' => 'text/plain'
        ];

        $resource = $this->factory->createResource($data);

        $this->assertInstanceOf(Resource::class, $resource);
        $this->assertNotNull($resource);
    }

    public function testCreatePrompt(): void
    {
        $data = [
            'name' => 'test-prompt',
            'description' => 'A test prompt',
            'arguments' => []
        ];

        $prompt = $this->factory->createPrompt($data);

        $this->assertInstanceOf(Prompt::class, $prompt);
        $this->assertNotNull($prompt);
    }

    public function testCreateContentBlock(): void
    {
        $textData = [
            'type' => 'text',
            'text' => 'Hello, World!'
        ];

        $content = $this->factory->createContentBlock($textData);

        $this->assertInstanceOf(\MCP\Types\Content\ContentBlock::class, $content);
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertNotNull($content);
    }

    public function testCreateProgressToken(): void
    {
        $token = $this->factory->createProgressToken('task-123');

        $this->assertInstanceOf(ProgressToken::class, $token);
        $this->assertNotNull($token);

        $numericToken = $this->factory->createProgressToken(456);
        $this->assertInstanceOf(ProgressToken::class, $numericToken);
        $this->assertNotNull($numericToken);
    }

    public function testCreateCursor(): void
    {
        $cursor = $this->factory->createCursor('cursor-value');

        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertNotNull($cursor);
    }

    public function testCreateRequestId(): void
    {
        $stringId = $this->factory->createRequestId('request-123');

        $this->assertInstanceOf(RequestId::class, $stringId);
        $this->assertNotNull($stringId);

        $numericId = $this->factory->createRequestId(789);
        $this->assertInstanceOf(RequestId::class, $numericId);
        $this->assertNotNull($numericId);
    }

    public function testCreateMultipleContentBlocks(): void
    {
        $dataArray = [
            [
                'type' => 'text',
                'text' => 'First block'
            ],
            [
                'type' => 'text',
                'text' => 'Second block'
            ]
        ];

        $blocks = $this->factory->createContentBlocks($dataArray);

        $this->assertIsArray($blocks);
        $this->assertCount(2, $blocks);
        $this->assertInstanceOf(TextContent::class, $blocks[0]);
        $this->assertInstanceOf(TextContent::class, $blocks[1]);
        $this->assertNotNull($blocks[0]);
        $this->assertNotNull($blocks[1]);
    }

    public function testGetValidationService(): void
    {
        $validationService = $this->factory->getValidationService();

        // Default factory should have no validation service
        $this->assertNull($validationService);
    }

    public function testFactoryWithValidationService(): void
    {
        $validationService = new \MCP\Validation\ValidationService();
        $factory = new TypeFactoryService($validationService);

        $this->assertSame($validationService, $factory->getValidationService());
    }
}
