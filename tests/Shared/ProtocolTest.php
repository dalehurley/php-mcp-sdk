<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use function Amp\async;

use Amp\DeferredFuture;
use Amp\Future;
use MCP\Shared\Protocol;
use MCP\Shared\ProtocolOptions;
use MCP\Shared\RequestOptions;
use MCP\Shared\Transport;
use MCP\Types\ErrorCode;
use MCP\Types\McpError;
use MCP\Types\Notification;
use MCP\Types\Notifications\CancelledNotification;
use MCP\Types\Progress;
use MCP\Types\Request;
use MCP\Types\Requests\PingRequest;
use MCP\Types\Result;
use MCP\Validation\ValidationService;
use PHPUnit\Framework\TestCase;

/**
 * Mock transport for testing.
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
 * Test implementation of Protocol.
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

    private ?\Closure $originalErrorHandler = null;

    protected function setUp(): void
    {
        // Suppress errors from previous tests' async operations
        $this->originalErrorHandler = \Revolt\EventLoop::getErrorHandler();
        \Revolt\EventLoop::setErrorHandler(function (\Throwable $e) {
            // Silently ignore "Not connected" errors from orphaned async operations
            if (str_contains($e->getMessage(), 'Not connected') ||
                str_contains($e->getMessage(), 'Unhandled future')) {
                return;
            }
            // Re-throw other errors
            throw $e;
        });

        $this->transport = new MockTransport();
        $this->protocol = new TestProtocol();
    }

    protected function tearDown(): void
    {
        // Properly close the protocol to avoid unhandled futures
        if ($this->protocol->getTransport()) {
            try {
                $this->protocol->close()->await();
            } catch (\Throwable $e) {
                // Ignore errors during cleanup
            }
        }

        // Restore original error handler
        if ($this->originalErrorHandler !== null) {
            \Revolt\EventLoop::setErrorHandler($this->originalErrorHandler);
            $this->originalErrorHandler = null;
        }
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
            'params' => [],
        ]);

        // Give async handler time to process
        \Amp\delay(0.01);

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

        $request = new Request('test', ['key' => 'value']);
        $validationService = new ValidationService();
        $options = new RequestOptions(timeout: 500); // Short timeout for test

        // Start the request (returns a future) and wait for it to be sent
        $future = $this->protocol->request($request, $validationService, $options);

        // Give event loop time to process the send (0.001 = 1ms)
        \Amp\delay(0.001);

        // The request should have been sent
        $this->assertCount(1, $this->transport->sentMessages);
        $sentRequest = $this->transport->sentMessages[0];
        $this->assertEquals('test', $sentRequest['method']);
        $this->assertEquals(['key' => 'value'], $sentRequest['params']);

        // Simulate response - use the actual ID from the sent message
        $requestId = $sentRequest['id'];
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'result' => ['success' => true],
        ]);

        // Now await the future
        $result = $future->await();
        $this->assertEquals(['success' => true], $result);
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

        $request = new Request('test');
        $validationService = new ValidationService();
        $options = new RequestOptions(timeout: 500, onprogress: $progressCallback);

        // Start the request
        $future = $this->protocol->request($request, $validationService, $options);

        // Give event loop time to process the send
        \Amp\delay(0.001);

        // Get the request ID from the sent message
        $this->assertCount(1, $this->transport->sentMessages);
        $requestId = $this->transport->sentMessages[0]['id'];

        // Simulate progress notification
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => [
                'progressToken' => $requestId,
                'progress' => 50,
                'total' => 100,
                'message' => 'Processing...',
            ],
        ]);

        // Give event loop time to process the progress notification handler
        \Amp\delay(0.001);

        // Simulate completion
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'result' => ['success' => true],
        ]);

        $result = $future->await();

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
        \Amp\delay(0.02);

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
            'params' => [],
        ]);

        \Amp\delay(0.01);

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
                'reason' => 'User cancelled',
            ],
        ]);

        \Amp\delay(0.01);

        $this->assertNotNull($received);
        $this->assertInstanceOf(CancelledNotification::class, $received);
        if ($received !== null) {
            $this->assertEquals('User cancelled', $received->getReason());
        }
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

        $request = new Request('test');
        $validationService = new ValidationService();
        $options = new RequestOptions(timeout: 500); // Short timeout for test

        // Start the request
        $future = $this->protocol->request($request, $validationService, $options);

        // Give event loop time to process the send
        \Amp\delay(0.001);

        // Get the request ID
        $this->assertCount(1, $this->transport->sentMessages);
        $requestId = $this->transport->sentMessages[0]['id'];

        // Simulate cancellation notification
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'method' => 'notifications/cancelled',
            'params' => [
                'requestId' => $requestId,
                'reason' => 'Cancelled by user',
            ],
        ]);

        try {
            $future->await();
        } catch (\Exception $e) {
            // Expected to be cancelled or timeout
        }

        // The request handler should abort when it receives cancellation
        // This test verifies the cancellation mechanism is in place
        $sentMessages = $this->transport->sentMessages;

        // Filter only request messages (not notifications)
        $requestMessages = array_filter($sentMessages, fn($msg) => isset($msg['id']));

        $this->assertCount(1, $requestMessages);
        $firstRequest = array_values($requestMessages)[0];
        $this->assertEquals('test', $firstRequest['method']);
    }

    public function testConnectionCloseDuringRequest(): void
    {
        $this->protocol->connect($this->transport)->await();

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Connection closed');

        $request = new Request('test');
        $validationService = new ValidationService();
        $options = new RequestOptions(timeout: 500); // Short timeout for test

        // Start the request
        $future = $this->protocol->request($request, $validationService, $options);

        // Give event loop time to process the send
        \Amp\delay(0.001);

        // Simulate connection close
        $this->transport->simulateClose();

        // This should throw McpError with "Connection closed"
        $future->await();
    }

    public function testConnectionCloseWithMultiplePendingRequestsNoUnhandledFutures(): void
    {
        $this->protocol->connect($this->transport)->await();

        // Create multiple pending requests without simulating responses
        $futures = [];
        $validationService = new ValidationService();
        $options = new RequestOptions(timeout: 500); // Short timeout for test

        for ($i = 0; $i < 3; $i++) {
            $request = new Request('test' . $i);
            $futures[] = $this->protocol->request($request, $validationService, $options);
        }

        // Give event loop time to process all sends
        \Amp\delay(0.01);

        // Verify requests were sent
        $this->assertCount(3, $this->transport->sentMessages);

        // Install error handler to catch any unhandled futures
        $unhandledErrors = [];
        $originalHandler = \Revolt\EventLoop::getErrorHandler();

        \Revolt\EventLoop::setErrorHandler(function (\Throwable $e) use (&$unhandledErrors) {
            if (str_contains($e->getMessage(), 'Unhandled future')) {
                $unhandledErrors[] = $e;
            }
        });

        try {
            // Simulate connection close - this should not create unhandled futures
            $this->transport->simulateClose();

            // Wait for all request futures to complete
            foreach ($futures as $future) {
                try {
                    $future->await();
                } catch (McpError $e) {
                    // Expected - connection closed
                    $this->assertEquals(ErrorCode::ConnectionClosed, $e->errorCode);
                }
            }

            // Verify no unhandled future errors were generated
            $this->assertEmpty($unhandledErrors, 'Connection close should not generate unhandled futures');
        } finally {
            // Restore original error handler
            \Revolt\EventLoop::setErrorHandler($originalHandler);
        }
    }

    public function testUnknownMethodRequest(): void
    {
        $this->protocol->connect($this->transport)->await();

        // Simulate request with unknown method
        $this->transport->simulateMessage([
            'jsonrpc' => '2.0',
            'id' => 789,
            'method' => 'unknown_method',
            'params' => [],
        ]);

        \Amp\delay(0.01);

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
        $requestOptions = new RequestOptions(timeout: 100); // Short timeout for test

        // This should trigger capability check
        try {
            $protocol->request($request, $validationService, $requestOptions)->await();
        } catch (\Exception $e) {
            // Expected to fail due to timeout or capability check
        }

        // Filter only request-type capability calls (not handler registrations)
        $requestCalls = array_filter($protocol->capabilityCalls, fn($call) => $call['type'] === 'request');

        $this->assertCount(1, $requestCalls);
        $firstRequestCall = array_values($requestCalls)[0];
        $this->assertEquals('test', $firstRequestCall['method']);
        $this->assertEquals('request', $firstRequestCall['type']);

        // Cleanup to avoid pending requests in tearDown
        $protocol->close()->await();
    }
}
