<?php

declare(strict_types=1);

namespace MCP\Tests\Types\Messages;

use MCP\Types\Messages\ClientRequest;
use MCP\Types\Requests\InitializeRequest;
use MCP\Types\Requests\ListResourcesRequest;
use MCP\Types\Requests\PingRequest;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ClientRequest union helper.
 */
class ClientRequestTest extends TestCase
{
    /**
     * Test getMethods returns all client request methods.
     */
    public function testGetMethods(): void
    {
        $methods = ClientRequest::getMethods();

        $this->assertIsArray($methods);
        $this->assertContains('ping', $methods);
        $this->assertContains('initialize', $methods);
        $this->assertContains('completion/complete', $methods);
        $this->assertContains('resources/list', $methods);
        $this->assertContains('tools/call', $methods);

        // Should not contain server methods
        $this->assertNotContains('sampling/createMessage', $methods);
        $this->assertNotContains('elicitation/create', $methods);
    }

    /**
     * Test isValidMethod.
     */
    public function testIsValidMethod(): void
    {
        // Valid client methods
        $this->assertTrue(ClientRequest::isValidMethod('initialize'));
        $this->assertTrue(ClientRequest::isValidMethod('resources/list'));
        $this->assertTrue(ClientRequest::isValidMethod('tools/list'));

        // Invalid methods
        $this->assertFalse(ClientRequest::isValidMethod('sampling/createMessage'));
        $this->assertFalse(ClientRequest::isValidMethod('invalid/method'));
        $this->assertFalse(ClientRequest::isValidMethod(''));
    }

    /**
     * Test fromArray with initialize request.
     */
    public function testFromArrayInitializeRequest(): void
    {
        $data = [
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ];

        $request = ClientRequest::fromArray($data);

        $this->assertInstanceOf(InitializeRequest::class, $request);
        $this->assertEquals('initialize', $request->getMethod());
    }

    /**
     * Test fromArray with list resources request.
     */
    public function testFromArrayListResourcesRequest(): void
    {
        $data = [
            'method' => 'resources/list',
            'params' => ['cursor' => 'next-page'],
        ];

        $request = ClientRequest::fromArray($data);

        $this->assertInstanceOf(ListResourcesRequest::class, $request);
        $this->assertEquals('resources/list', $request->getMethod());
        $this->assertNotNull($request->getCursor());
    }

    /**
     * Test fromArray with ping request.
     */
    public function testFromArrayPingRequest(): void
    {
        $data = ['method' => 'ping'];

        $request = ClientRequest::fromArray($data);

        $this->assertInstanceOf(PingRequest::class, $request);
        $this->assertEquals('ping', $request->getMethod());
    }

    /**
     * Test fromArray with invalid method.
     */
    public function testFromArrayInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown client request method: invalid/method');

        ClientRequest::fromArray(['method' => 'invalid/method']);
    }

    /**
     * Test fromArray without method.
     */
    public function testFromArrayWithoutMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request must have a method property');

        ClientRequest::fromArray(['params' => []]);
    }
}
