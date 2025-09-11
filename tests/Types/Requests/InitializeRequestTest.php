<?php

declare(strict_types=1);

namespace MCP\Tests\Types\Requests;

use MCP\Types\Requests\InitializeRequest;
use MCP\Types\Protocol;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ClientCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * Test class for InitializeRequest.
 */
class InitializeRequestTest extends TestCase
{
    /**
     * Test create method.
     */
    public function testCreate(): void
    {
        $clientInfo = new Implementation('test-client', '1.0.0');
        $capabilities = ClientCapabilities::fromArray(['sampling' => []]);
        
        $request = InitializeRequest::create(
            Protocol::LATEST_PROTOCOL_VERSION,
            $capabilities,
            $clientInfo
        );
        
        $this->assertInstanceOf(InitializeRequest::class, $request);
        $this->assertEquals('initialize', $request->getMethod());
        $this->assertEquals(Protocol::LATEST_PROTOCOL_VERSION, $request->getProtocolVersion());
        $this->assertNotNull($request->getCapabilities());
        $this->assertNotNull($request->getClientInfo());
    }

    /**
     * Test getters with valid data.
     */
    public function testGetters(): void
    {
        $request = new InitializeRequest([
            'protocolVersion' => '2025-06-18',
            'capabilities' => ['experimental' => []],
            'clientInfo' => [
                'name' => 'test-client',
                'version' => '1.0.0'
            ]
        ]);
        
        $this->assertEquals('2025-06-18', $request->getProtocolVersion());
        
        $capabilities = $request->getCapabilities();
        $this->assertNotNull($capabilities);
        $this->assertInstanceOf(ClientCapabilities::class, $capabilities);
        
        $clientInfo = $request->getClientInfo();
        $this->assertNotNull($clientInfo);
        $this->assertInstanceOf(Implementation::class, $clientInfo);
        $this->assertEquals('test-client', $clientInfo->getName());
    }

    /**
     * Test getters with missing data.
     */
    public function testGettersWithMissingData(): void
    {
        $request = new InitializeRequest();
        
        $this->assertNull($request->getProtocolVersion());
        $this->assertNull($request->getCapabilities());
        $this->assertNull($request->getClientInfo());
    }

    /**
     * Test isValid method.
     */
    public function testIsValid(): void
    {
        // Valid request
        $this->assertTrue(InitializeRequest::isValid([
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0']
            ]
        ]));
        
        // Wrong method
        $this->assertFalse(InitializeRequest::isValid([
            'method' => 'other',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => []
            ]
        ]));
        
        // Missing required params
        $this->assertFalse(InitializeRequest::isValid([
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18'
            ]
        ]));
        
        // No params
        $this->assertFalse(InitializeRequest::isValid([
            'method' => 'initialize'
        ]));
    }

    /**
     * Test JSON serialization.
     */
    public function testJsonSerialization(): void
    {
        $clientInfo = new Implementation('my-client', '2.0.0', 'My Client');
        $capabilities = ClientCapabilities::fromArray([
            'roots' => ['listChanged' => true],
            'sampling' => []
        ]);
        
        $request = InitializeRequest::create(
            Protocol::LATEST_PROTOCOL_VERSION,
            $capabilities,
            $clientInfo
        );
        
        $json = $request->jsonSerialize();
        
        $this->assertEquals('initialize', $json['method']);
        $this->assertArrayHasKey('params', $json);
        $this->assertEquals(Protocol::LATEST_PROTOCOL_VERSION, $json['params']['protocolVersion']);
        $this->assertIsArray($json['params']['capabilities']);
        $this->assertIsArray($json['params']['clientInfo']);
    }
}
