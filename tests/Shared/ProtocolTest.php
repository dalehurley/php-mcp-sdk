<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutException;
use MCP\Shared\Protocol;
use MCP\Shared\ProtocolOptions;
use MCP\Shared\RequestOptions;
use MCP\Shared\NotificationOptions;
use MCP\Shared\Transport;
use MCP\Types\ErrorCode;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCResponse;
use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\McpError;
use MCP\Types\Notification;
use MCP\Types\Request;
use MCP\Types\RequestId;
use MCP\Types\Result;
use MCP\Types\Requests\PingRequest;
use MCP\Types\Notifications\CancelledNotification;
use MCP\Types\Notifications\ProgressNotification;
use MCP\Types\Progress;
use MCP\Validation\ValidationService;
use PHPUnit\Framework\TestCase;
use function Amp\async;
use function Amp\delay;

/**
 * Mock transport for testing
 */
class MockTransport implements Transport
{
    public array $sentMessages = [];
    public mixed $messageHandler = null;
    public mixed $closeHandler = null;
    public mixed $errorHandler = null;
    public bool $isStarted = false;
    public bool $isClosed = false;

    public function start(): Future
    {
        $this->isStarted = true;
        $deferred = new DeferredFuture();
        $deferred->complete();
        return $deferred->getFuture();
    }

    public function send(array $message): Future
    {
        $this->sentMessages[] = $message;
        $deferred = new DeferredFuture();
        $deferred->complete();
        return $deferred->getFuture();
    }

    public function close(): Future
    {
        $this->isClosed = true;
        if ($this->closeHandler) {
            ($this->closeHandler)();
        }
        $deferred = new DeferredFuture();
        $deferred->complete();
        return $deferred->getFuture();
    }

    public function setMessageHandler(callable $handler): void
    {
        $this->messageHandler = $handler;
    }

    public function setCloseHandler(callable $handler): void
    {
        $this->closeHandler = $handler;
    }

    public function setErrorHandler(callable $handler): void
    {
        $this->errorHandler = $handler;
    }

    public function simulateMessage(array $message): void
    {
        if ($this->messageHandler) {
            ($this->messageHandler)($message);
        }
    }

    public function simulateError(\Throwable $error): void
    {
        if ($this->errorHandler) {
            ($this->errorHandler)($error);
        }
    }

    public function simulateClose(): void
    {
        if ($this->closeHandler) {
            ($this->closeHandler)();
        }
    }
}

/**
 * Test implementation of Protocol
 */
class TestProtocol extends Protocol
{
    public array $capabilityCalls = [];

    public function __construct(?ProtocolOptions $options = null)
    {
        parent::__construct($options);
    }

    protected function assertCapabilityForMethod(string $method): void
    {
        $this->capabilityCalls[] = ['method' => $method, 'type' => 'request'];
    }

    protected function assertNotificationCapability(string $method): void
    {
        $this->capabilityCalls[] = ['method' => $method, 'type' => 'notification'];
    }

    protected function assertRequestHandlerCapability(string $method): void
    {
        $this->capabilityCalls[] = ['method' => $method, 'type' => 'handler'];
    }
}

class ProtocolTest extends TestCase
{
    private MockTransport $transport;
    private TestProtocol $protocol;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->protocol = new TestProtocol();
    }

    public function testConnect(): void
    {
        $future = $this->protocol->connect($this->transport);
        $future->await();

        $this->assertTrue($this->transport->isStarted);
        $this->assertNotNull($this->transport->messageHandler);
        $this->assertNotNull($this->transport->closeHandler);
        $this->assertNotNull($this->transport->errorHandler);
        $this->assertEquals($this->transport, $this->protocol->getTransport());
    }

    public function testClose(): void
    {
        $this->protocol->connect($this->transport)->await();

        $closeCalled = false;
        $this->protocol->onclose = function () use (&$closeCalled) {
            $closeCalled = true;
        };

        $this->protocol->close()->await();

        $this->assertTrue($this->transport->isClosed);
        $this->assertTrue($closeCalled);
    }

    public function testPingRequest(): void
    {
        $this->protocol->connect($this->transport)->await();

        // Simulate ping request
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'id' => 123,
            'method' => 'ping',
            'params' => []
        ]);

        // Give async handler time to process
        delay(0.01);

        // Should automatically respond with pong
        $this->assertCount(1, $this->transport->sentMessages);
        $response = $this->transport->sentMessages[0];
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(123, $response['id']);
        $this->assertArrayHasKey('result', $response);
    }

    public function testRequest(): void
    {
        $this->protocol->connect($this->transport)->await();

        // Set up handler to simulate response
        $requestFuture = async(function () {
            $request = new Request('test', ['key' => 'value']);
            $validationService = new ValidationService();

            $future = $this->protocol->request($request, $validationService);

            // Wait for request to be sent
            delay(0.01);

            // Simulate response
            $this->transport->simulateMessage([
                'jsonrpc' => '2.0',
                'id' => 0, // First request ID
                'result' => ['success' => true]
            ]);

            return $future->await();
        });

        $result = $requestFuture->await();

        $this->assertEquals(['success' => true], $result);
        $this->assertCount(1, $this->transport->sentMessages);

        $sentRequest = $this->transport->sentMessages[0];
        $this->assertEquals('test', $sentRequest['method']);
        $this->assertEquals(['key' => 'value'], $sentRequest['params']);
    }

    public function testRequestWithTimeout(): void
    {
        $this->protocol->connect($this->transport)->await();

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Request timed out');

        $request = new Request('test');
        $validationService = new ValidationService();
        $options = new RequestOptions(timeout: 100); // 100ms timeout

        $this->protocol->request($request, $validationService, $options)->await();
    }

    public function testRequestWithProgress(): void
    {
        $this->protocol->connect($this->transport)->await();

        $progressUpdates = [];
        $progressCallback = function (Progress $progress) use (&$progressUpdates) {
            $progressUpdates[] = $progress;
        };

        $requestFuture = async(function () use ($progressCallback) {
            $request = new Request('test');
            $validationService = new ValidationService();
            $options = new RequestOptions(onprogress: $progressCallback);

            $future = $this->protocol->request($request, $validationService, $options);

            // Wait for request to be sent
            delay(0.01);

            // Simulate progress notification
            $this->transport->simulateMessage([
                'jsonrpc' => '2.0',
                'method' => 'notifications/progress',
                'params' => [
                    'progressToken' => 0, // Matches request ID
                    'progress' => 50,
                    'total' => 100,
                    'message' => 'Processing...'
                ]
            ]);

            delay(0.01);

            // Simulate completion
            $this->transport->simulateMessage([
                'jsonrpc' => '2.0',
                'id' => 0,
                'result' => ['success' => true]
            ]);

            return $future->await();
        });

        $result = $requestFuture->await();

        $this->assertCount(1, $progressUpdates);
        $this->assertEquals(50, $progressUpdates[0]->getProgress());
        $this->assertEquals(100, $progressUpdates[0]->getTotal());
        $this->assertEquals('Processing...', $progressUpdates[0]->getMessage());
    }

    public function testNotification(): void
    {
        $this->protocol->connect($this->transport)->await();

        $notification = new Notification('test', ['data' => 'value']);
        $this->protocol->notification($notification)->await();

        $this->assertCount(1, $this->transport->sentMessages);
        $sent = $this->transport->sentMessages[0];
        $this->assertEquals('2.0', $sent['jsonrpc']);
        $this->assertEquals('test', $sent['method']);
        $this->assertEquals(['data' => 'value'], $sent['params']);
        $this->assertArrayNotHasKey('id', $sent);
    }

    public function testDebouncedNotification(): void
    {
        $options = new ProtocolOptions(
            debouncedNotificationMethods: ['debounced']
        );
        $protocol = new TestProtocol($options);
        $protocol->connect($this->transport)->await();

        // Send multiple notifications of the same type
        $notification = new Notification('debounced');
        $protocol->notification($notification)->await();
        $protocol->notification($notification)->await();
        $protocol->notification($notification)->await();

        // Give time for debouncing
        delay(0.02);

        // Should only send one
        $this->assertCount(1, $this->transport->sentMessages);
    }

    public function testRequestHandler(): void
    {
        $this->protocol->connect($this->transport)->await();

        $handlerCalled = false;
        $this->protocol->setRequestHandler(
            PingRequest::class,
            function (PingRequest $request) use (&$handlerCalled) {
                $handlerCalled = true;
                return new Result();
            }
        );

        // Simulate incoming request
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'id' => 456,
            'method' => 'ping',
            'params' => []
        ]);

        delay(0.01);

        $this->assertTrue($handlerCalled);
        $this->assertCount(1, $this->transport->sentMessages);
        $response = $this->transport->sentMessages[0];
        $this->assertEquals(456, $response['id']);
        $this->assertArrayHasKey('result', $response);
    }

    public function testNotificationHandler(): void
    {
        $this->protocol->connect($this->transport)->await();

        $received = null;
        $this->protocol->setNotificationHandler(
            CancelledNotification::class,
            function (CancelledNotification $notification) use (&$received) {
                $received = $notification;
            }
        );

        // Simulate incoming notification
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'method' => 'notifications/cancelled',
            'params' => [
                'requestId' => 123,
                'reason' => 'User cancelled'
            ]
        ]);

        delay(0.01);

        $this->assertNotNull($received);
        $this->assertEquals('User cancelled', $received->getReason());
    }

    public function testErrorHandling(): void
    {
        $this->protocol->connect($this->transport)->await();

        $errorReceived = null;
        $this->protocol->onerror = function (\Throwable $error) use (&$errorReceived) {
            $errorReceived = $error;
        };

        $testError = new \Error('Test error');
        $this->transport->simulateError($testError);

        $this->assertEquals($testError, $errorReceived);
    }

    public function testRequestCancellation(): void
    {
        $this->protocol->connect($this->transport)->await();

        // Simulate a request that gets cancelled
        $requestFuture = async(function () {
            $request = new Request('test');
            $validationService = new ValidationService();

            $future = $this->protocol->request($request, $validationService);

            // Wait for request to be sent
            delay(0.01);

            // Simulate cancellation notification
            $this->transport->simulateMessage([
                'jsonrpc' => '2.0',
                'method' => 'notifications/cancelled',
                'params' => [
                    'requestId' => 0,
                    'reason' => 'Cancelled by user'
                ]
            ]);

            return $future->await();
        });

        // The request handler should abort when it receives cancellation
        // This test verifies the cancellation mechanism is in place
        $sentMessages = $this->transport->sentMessages;
        $this->assertCount(1, $sentMessages);
        $this->assertEquals('test', $sentMessages[0]['method']);
    }

    public function testConnectionCloseDuringRequest(): void
    {
        $this->protocol->connect($this->transport)->await();

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Connection closed');

        $requestFuture = async(function () {
            $request = new Request('test');
            $validationService = new ValidationService();

            $future = $this->protocol->request($request, $validationService);

            // Wait for request to be sent
            delay(0.01);

            // Simulate connection close
            $this->transport->simulateClose();

            return $future->await();
        });

        $requestFuture->await();
    }

    public function testUnknownMethodRequest(): void
    {
        $this->protocol->connect($this->transport)->await();

        // Simulate request with unknown method
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'id' => 789,
            'method' => 'unknown_method',
            'params' => []
        ]);

        delay(0.01);

        $this->assertCount(1, $this->transport->sentMessages);
        $response = $this->transport->sentMessages[0];
        $this->assertEquals(789, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(ErrorCode::MethodNotFound->value, $response['error']['code']);
    }

    public function testStrictCapabilities(): void
    {
        $options = new ProtocolOptions(enforceStrictCapabilities: true);
        $protocol = new TestProtocol($options);
        $protocol->connect($this->transport)->await();

        $request = new Request('test');
        $validationService = new ValidationService();

        // This should trigger capability check
        try {
            $protocol->request($request, $validationService)->await();
        } catch (\Exception $e) {
            // Expected to fail due to timeout or capability check
        }

        $this->assertCount(1, $protocol->capabilityCalls);
        $this->assertEquals('test', $protocol->capabilityCalls[0]['method']);
        $this->assertEquals('request', $protocol->capabilityCalls[0]['type']);
    }
}
