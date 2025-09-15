<?php

declare(strict_types=1);

namespace MCP\Tests\Types\Results;

use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Implementation;
use MCP\Types\Protocol;
use MCP\Types\Results\InitializeResult;
use PHPUnit\Framework\TestCase;

/**
 * Test class for InitializeResult.
 */
class InitializeResultTest extends TestCase
{
    /**
     * Test construction and getters.
     */
    public function testConstructionAndGetters(): void
    {
        $serverInfo = new Implementation('test-server', '1.0.0');
        $capabilities = ServerCapabilities::fromArray([
            'prompts' => [],
            'tools' => ['listChanged' => true],
        ]);

        $result = new InitializeResult(
            Protocol::DEFAULT_NEGOTIATED_PROTOCOL_VERSION,
            $capabilities,
            $serverInfo,
            'Welcome to the server!'
        );

        $this->assertEquals(Protocol::DEFAULT_NEGOTIATED_PROTOCOL_VERSION, $result->getProtocolVersion());
        $this->assertSame($capabilities, $result->getCapabilities());
        $this->assertSame($serverInfo, $result->getServerInfo());
        $this->assertEquals('Welcome to the server!', $result->getInstructions());
    }

    /**
     * Test fromArray method.
     */
    public function testFromArray(): void
    {
        $data = [
            'protocolVersion' => '2025-03-26',
            'capabilities' => [
                'logging' => [],
                'resources' => ['subscribe' => true],
            ],
            'serverInfo' => [
                'name' => 'example-server',
                'version' => '2.0.0',
                'title' => 'Example Server',
            ],
            'instructions' => 'Server instructions here',
            '_meta' => ['custom' => 'data'],
        ];

        $result = InitializeResult::fromArray($data);

        $this->assertEquals('2025-03-26', $result->getProtocolVersion());
        $this->assertInstanceOf(ServerCapabilities::class, $result->getCapabilities());
        $this->assertInstanceOf(Implementation::class, $result->getServerInfo());
        $this->assertEquals('example-server', $result->getServerInfo()->getName());
        $this->assertEquals('Server instructions here', $result->getInstructions());
        $this->assertEquals(['custom' => 'data'], $result->getMeta());
    }

    /**
     * Test fromArray with missing required fields.
     */
    public function testFromArrayMissingFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InitializeResult must have a protocolVersion property');

        InitializeResult::fromArray(['capabilities' => []]);
    }

    /**
     * Test JSON serialization.
     */
    public function testJsonSerialization(): void
    {
        $serverInfo = new Implementation('server', '1.0.0');
        $capabilities = ServerCapabilities::fromArray(['logging' => []]);

        $result = new InitializeResult(
            '2025-03-26',
            $capabilities,
            $serverInfo
        );

        $json = $result->jsonSerialize();

        $this->assertEquals('2025-03-26', $json['protocolVersion']);
        $this->assertIsArray($json['capabilities']);
        $this->assertIsArray($json['serverInfo']);
        $this->assertArrayNotHasKey('instructions', $json);
        $this->assertArrayNotHasKey('_meta', $json);
    }

    /**
     * Test JSON serialization with all fields.
     */
    public function testJsonSerializationWithAllFields(): void
    {
        $serverInfo = new Implementation('server', '1.0.0');
        $capabilities = ServerCapabilities::fromArray([]);

        $result = new InitializeResult(
            '2025-03-26',
            $capabilities,
            $serverInfo,
            'Instructions',
            ['meta' => 'data']
        );

        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('instructions', $json);
        $this->assertEquals('Instructions', $json['instructions']);
        $this->assertArrayHasKey('_meta', $json);
        $this->assertEquals(['meta' => 'data'], $json['_meta']);
    }
}
