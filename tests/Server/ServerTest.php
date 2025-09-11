<?php

declare(strict_types=1);

namespace MCP\Tests\Server;

use MCP\Server\Server;
use MCP\Server\ServerOptions;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Requests\InitializeRequest;
use MCP\Types\Results\InitializeResult;
use MCP\Types\Notifications\InitializedNotification;
use MCP\Types\Protocol as ProtocolConstants;
use MCP\Types\LoggingLevel;
use MCP\Types\Requests\SetLevelRequest;
use MCP\Types\Result;
use MCP\Shared\RequestHandlerExtra;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ServerTest extends TestCase
{
    private Implementation $serverInfo;
    private ServerCapabilities $capabilities;

    protected function setUp(): void
    {
        $this->serverInfo = new Implementation('test-server', '1.0.0');
        $this->capabilities = new ServerCapabilities(
            tools: ['listChanged' => true],
            resources: ['listChanged' => true],
            prompts: ['listChanged' => true],
            logging: []
        );
    }

    public function testServerConstruction(): void
    {
        $server = new Server($this->serverInfo);

        $this->assertInstanceOf(Server::class, $server);
        $this->assertNull($server->getClientCapabilities());
        $this->assertNull($server->getClientVersion());
    }

    public function testServerConstructionWithOptions(): void
    {
        // Create ServerOptions using the full class name since it's defined in the same file
        $optionsClass = 'MCP\Server\ServerOptions';
        $options = new $optionsClass(
            capabilities: $this->capabilities,
            instructions: 'Test instructions'
        );

        $server = new Server($this->serverInfo, $options);

        $this->assertInstanceOf(Server::class, $server);
    }

    public function testInitializationFlow(): void
    {
        $server = new Server($this->serverInfo);
        $clientCapabilities = new ClientCapabilities(
            roots: ['listChanged' => true],
            sampling: []
        );

        $initRequest = InitializeRequest::fromArray([
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => ProtocolConstants::LATEST_PROTOCOL_VERSION,
                'capabilities' => $clientCapabilities->jsonSerialize(),
                'clientInfo' => (new Implementation('test-client', '1.0.0'))->jsonSerialize()
            ]
        ]);

        // Create a mock RequestHandlerExtra
        $extra = $this->createMock(RequestHandlerExtra::class);

        // Get the initialize handler through reflection since it's private
        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('_oninitialize');
        $method->setAccessible(true);

        $result = $method->invoke($server, $initRequest);

        $this->assertInstanceOf(InitializeResult::class, $result);
        $this->assertEquals(ProtocolConstants::LATEST_PROTOCOL_VERSION, $result->getProtocolVersion());
        $this->assertEquals($this->serverInfo, $result->getServerInfo());
        $this->assertInstanceOf(ServerCapabilities::class, $result->getCapabilities());

        // Check that client capabilities are stored
        $this->assertEquals($clientCapabilities, $server->getClientCapabilities());
        $this->assertEquals(new Implementation('test-client', '1.0.0'), $server->getClientVersion());
    }

    public function testInitializationWithUnsupportedProtocolVersion(): void
    {
        $server = new Server($this->serverInfo);
        $clientCapabilities = new ClientCapabilities();

        $initRequest = InitializeRequest::fromArray([
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '999.0.0', // Unsupported version
                'capabilities' => $clientCapabilities->jsonSerialize(),
                'clientInfo' => (new Implementation('test-client', '1.0.0'))->jsonSerialize()
            ]
        ]);

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('_oninitialize');
        $method->setAccessible(true);

        $result = $method->invoke($server, $initRequest);

        // Should fall back to latest supported version
        $this->assertEquals(ProtocolConstants::LATEST_PROTOCOL_VERSION, $result->getProtocolVersion());
    }

    public function testOnInitializedCallback(): void
    {
        $server = new Server($this->serverInfo);
        $callbackCalled = false;

        $server->oninitialized = function () use (&$callbackCalled) {
            $callbackCalled = true;
        };

        // Simulate initialized notification
        $notification = new InitializedNotification();

        // We need to trigger the notification handler
        // This is a bit tricky since the handler is set internally
        // For now, we'll test that the callback property is set correctly
        $this->assertIsCallable($server->oninitialized);

        // Call the callback directly to test it works
        ($server->oninitialized)();
        $this->assertTrue($callbackCalled);
    }

    public function testRegisterCapabilities(): void
    {
        $server = new Server($this->serverInfo);
        $additionalCapabilities = new ServerCapabilities(
            tools: ['listChanged' => true]
        );

        // Should not throw when not connected
        $server->registerCapabilities($additionalCapabilities);

        // This test verifies the method exists and can be called
        $this->assertTrue(true);
    }

    public function testRegisterCapabilitiesAfterConnect(): void
    {
        $server = new Server($this->serverInfo);

        // Use reflection to set the transport as connected to test the error condition
        // We need to access the Protocol parent class since transport is private there
        $protocolReflection = new \ReflectionClass('MCP\Shared\Protocol');
        $transportProperty = $protocolReflection->getProperty('transport');
        $transportProperty->setAccessible(true);

        // Create a mock transport and set it
        $transport = $this->createMock(\MCP\Shared\Transport::class);
        $transportProperty->setValue($server, $transport);

        // Try to register capabilities after connecting - should throw
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot register capabilities after connecting to transport');

        $server->registerCapabilities(new ServerCapabilities());
    }

    public function testLoggingLevelHandler(): void
    {
        $optionsClass = 'MCP\Server\ServerOptions';
        $options = new $optionsClass(
            capabilities: new ServerCapabilities(logging: [])
        );
        $server = new Server($this->serverInfo, $options);

        // This test verifies that the server can be constructed with logging capabilities
        // The actual request handling is tested in integration tests
        $this->assertInstanceOf(Server::class, $server);
    }

    public function testCapabilityAssertions(): void
    {
        $server = new Server($this->serverInfo);

        // Test assertCapabilityForMethod through reflection
        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('assertCapabilityForMethod');
        $method->setAccessible(true);

        // Should not throw for ping
        $method->invoke($server, 'ping');

        // Should throw for sampling without client capability
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Client does not support sampling');
        $method->invoke($server, 'sampling/createMessage');
    }

    public function testNotificationCapabilityAssertions(): void
    {
        $server = new Server($this->serverInfo);

        // Test assertNotificationCapability through reflection
        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('assertNotificationCapability');
        $method->setAccessible(true);

        // Should not throw for cancellation notifications
        $method->invoke($server, 'notifications/cancelled');

        // Should throw for logging without server capability
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Server does not support logging');
        $method->invoke($server, 'notifications/message');
    }

    public function testRequestHandlerCapabilityAssertions(): void
    {
        $server = new Server($this->serverInfo);

        // Test assertRequestHandlerCapability through reflection
        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('assertRequestHandlerCapability');
        $method->setAccessible(true);

        // Should not throw for initialize
        $method->invoke($server, 'initialize');

        // Should throw for tools without server capability
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Server does not support tools');
        $method->invoke($server, 'tools/list');
    }

    public function testPingMethod(): void
    {
        $server = new Server($this->serverInfo);

        // The ping method should return a Future
        $future = $server->ping();
        $this->assertInstanceOf(\Amp\Future::class, $future);
    }

    public function testSendLoggingMessage(): void
    {
        $optionsClass = 'MCP\Server\ServerOptions';
        $options = new $optionsClass(
            capabilities: new ServerCapabilities(logging: [])
        );
        $server = new Server($this->serverInfo, $options);

        $params = [
            'level' => 'info',
            'logger' => 'test',
            'data' => 'test message'
        ];

        $future = $server->sendLoggingMessage($params);
        $this->assertInstanceOf(\Amp\Future::class, $future);
    }

    public function testSendResourceNotifications(): void
    {
        $server = new Server($this->serverInfo);

        // Test resource updated notification
        $future1 = $server->sendResourceUpdated(['uri' => 'test://resource']);
        $this->assertInstanceOf(\Amp\Future::class, $future1);

        // Test resource list changed notification
        $future2 = $server->sendResourceListChanged();
        $this->assertInstanceOf(\Amp\Future::class, $future2);

        // Test tool list changed notification
        $future3 = $server->sendToolListChanged();
        $this->assertInstanceOf(\Amp\Future::class, $future3);

        // Test prompt list changed notification
        $future4 = $server->sendPromptListChanged();
        $this->assertInstanceOf(\Amp\Future::class, $future4);
    }
}
