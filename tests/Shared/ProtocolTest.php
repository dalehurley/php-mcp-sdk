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

    protected function setUp(): void
    {
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
        \Amp\delay(10);

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
            \Amp\delay(10);

            // Simulate response
            $this->transport->simulateMessage([
                'jsonrpc' => '2.0',
                'id' => 0, // First request ID
                'result' => ['success' => true],
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
            \Amp\delay(10);

            // Simulate progress notification
            $this->transport->simulateMessage([
                'jsonrpc' => '2.0',
                'method' => 'notifications/progress',
                'params' => [
                    'progressToken' => 0, // Matches request ID
                    'progress' => 50,
                    'total' => 100,
                    'message' => 'Processing...',
                ],
            ]);

            \Amp\delay(10);

            // Simulate completion
            $this->transport->simulateMessage([
                'jsonrpc' => '2.0',
                'id' => 0,
                'result' => ['success' => true],
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
        \Amp\delay(20);

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

        \Amp\delay(10);

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

        \Amp\delay(10);

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

        // Simulate a request that gets cancelled
        $requestFuture = async(function () {
            $request = new Request('test');
            $validationService = new ValidationService();

            $future = $this->protocol->request($request, $validationService);

            // Wait for request to be sent
            \Amp\delay(10);

            // Simulate cancellation notification
            $this->transport->simulateMessage([
                'jsonrpc' => '2.0',
                'method' => 'notifications/cancelled',
                'params' => [
                    'requestId' => 0,
                    'reason' => 'Cancelled by user',
                ],
            ]);

            try {
                return $future->await();
            } catch (\Exception $e) {
                // Expected to be cancelled
                return null;
            }
        });

        // Wait for the request future to complete
        $requestFuture->await();

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

        $requestFuture = async(function () {
            $request = new Request('test');
            $validationService = new ValidationService();

            $future = $this->protocol->request($request, $validationService);

            // Wait for request to be sent
            \Amp\delay(10);

            // Simulate connection close
            $this->transport->simulateClose();

            return $future->await();
        });

        $requestFuture->await();
    }

    public function testConnectionCloseWithMultiplePendingRequestsNoUnhandledFutures(): void
    {
        $this->protocol->connect($this->transport)->await();

        // Create multiple pending requests without simulating responses
        $requestFutures = [];
        for ($i = 0; $i < 3; $i++) {
            $request = new Request('test' . $i);
            $validationService = new ValidationService();

            $requestFutures[] = async(function () use ($request, $validationService) {
                try {
                    return $this->protocol->request($request, $validationService)->await();
                } catch (McpError $e) {
                    // Expected - connection will be closed
                    if ($e->errorCode === ErrorCode::ConnectionClosed) {
                        return 'closed';
                    }
                    throw $e;
                }
            });
        }

        // Wait for requests to be sent
        \Amp\delay(10);

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
            foreach ($requestFutures as $future) {
                $result = $future->await();
                $this->assertEquals('closed', $result);
            }

            // Give time for any unhandled futures to be detected
            \Amp\delay(50);

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

        \Amp\delay(10);

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

        // Filter only request-type capability calls (not handler registrations)
        $requestCalls = array_filter($protocol->capabilityCalls, fn($call) => $call['type'] === 'request');

        $this->assertCount(1, $requestCalls);
        $firstRequestCall = array_values($requestCalls)[0];
        $this->assertEquals('test', $firstRequestCall['method']);
        $this->assertEquals('request', $firstRequestCall['type']);
    }
}
