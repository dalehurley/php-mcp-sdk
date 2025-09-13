<?php

declare(strict_types=1);

namespace MCP\Tests\Server;

use MCP\Server\McpServer;
use MCP\Server\Server;
use MCP\Shared\Transport;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Requests\InitializeRequest;
use MCP\Types\Requests\ListToolsRequest;
use MCP\Types\Requests\CallToolRequest;
use MCP\Types\Requests\ListResourcesRequest;
use MCP\Types\Requests\ReadResourceRequest;
use MCP\Types\Requests\ListPromptsRequest;
use MCP\Types\Requests\GetPromptRequest;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\Notifications\InitializedNotification;
use MCP\Types\Protocol as ProtocolConstants;
use MCP\Types\McpError;
use MCP\Types\ErrorCode;
use MCP\Shared\RequestHandlerExtra;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use function Amp\async;
use function Amp\delay;

/**
 * Mock transport for testing
 */
class MockTransport implements Transport
{
    /** @var callable|null */
    private $messageHandler = null;
    /** @var callable|null */
    private $closeHandler = null;
    /** @var callable|null */
    private $errorHandler = null;
    private array $sentMessages = [];
    private bool $started = false;
    private bool $closed = false;

    public function start(): \Amp\Future
    {
        $this->started = true;
        return async(fn() => null);
    }

    public function send(array $message): \Amp\Future
    {
        $this->sentMessages[] = $message;
        return async(fn() => null);
    }

    public function close(): \Amp\Future
    {
        $this->closed = true;
        if ($this->closeHandler) {
            ($this->closeHandler)();
        }
        return async(fn() => null);
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

    // Test helper methods
    public function simulateMessage(array $message): void
    {
        if ($this->messageHandler) {
            ($this->messageHandler)($message);
        }
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function clearSentMessages(): void
    {
        $this->sentMessages = [];
    }
}

class IntegrationTest extends TestCase
{
    private MockTransport $transport;
    private McpServer $server;
    private Implementation $serverInfo;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->serverInfo = new Implementation('test-server', '1.0.0');
        $this->server = new McpServer($this->serverInfo);
    }

    public function testServerConnection(): void
    {
        $connectFuture = $this->server->connect($this->transport);

        // Wait for connection to complete
        $connectFuture->await();

        $this->assertTrue($this->transport->isStarted());
        $this->assertTrue($this->server->isConnected());
    }

    public function testInitializationFlow(): void
    {
        // Connect server
        $this->server->connect($this->transport)->await();

        // Simulate initialize request from client
        $initRequest = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => ProtocolConstants::LATEST_PROTOCOL_VERSION,
                'capabilities' => [
                    'roots' => ['listChanged' => true]
                ],
                'clientInfo' => [
                    'name' => 'test-client',
                    'version' => '1.0.0'
                ]
            ]
        ];

        $this->transport->simulateMessage($initRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        // Check that server responded with initialize result
        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals(ProtocolConstants::LATEST_PROTOCOL_VERSION, $response['result']['protocolVersion']);
        $this->assertArrayHasKey('capabilities', $response['result']);
        $this->assertEquals($this->serverInfo->toArray(), $response['result']['serverInfo']);

        // Simulate initialized notification
        $this->transport->clearSentMessages();
        $initializedNotification = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized'
        ];

        $this->transport->simulateMessage($initializedNotification);

        // Should not send any response to notification
        $this->assertCount(0, $this->transport->getSentMessages());
    }

    public function testToolListAndCall(): void
    {
        // Register a test tool
        $this->server->registerTool(
            'calculator',
            [
                'title' => 'Calculator',
                'description' => 'Perform calculations',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => ['type' => 'string']
                    ],
                    'required' => ['expression']
                ]
            ],
            function (array $args): CallToolResult {
                $result = eval('return ' . $args['expression'] . ';');
                return new CallToolResult([
                    ['type' => 'text', 'text' => (string)$result]
                ]);
            }
        );

        // Connect and initialize
        $this->server->connect($this->transport)->await();
        $this->initializeServer();

        // Test list tools
        $this->transport->clearSentMessages();
        $listToolsRequest = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list'
        ];

        $this->transport->simulateMessage($listToolsRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals(2, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertCount(1, $response['result']['tools']);

        $tool = $response['result']['tools'][0];
        $this->assertEquals('calculator', $tool['name']);
        $this->assertEquals('Calculator', $tool['title']);
        $this->assertEquals('Perform calculations', $tool['description']);

        // Test call tool
        $this->transport->clearSentMessages();
        $callToolRequest = [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'calculator',
                'arguments' => [
                    'expression' => '2 + 2'
                ]
            ]
        ];

        $this->transport->simulateMessage($callToolRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals(3, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('content', $response['result']);
        $this->assertEquals('4', $response['result']['content'][0]['text']);
    }

    public function testResourceListAndRead(): void
    {
        // Register a test resource
        $this->server->registerResource(
            'test-file',
            'file://test.txt',
            ['title' => 'Test File', 'mimeType' => 'text/plain'],
            function (string $uri): ReadResourceResult {
                return new ReadResourceResult([
                    ['type' => 'text', 'text' => 'Test file content']
                ]);
            }
        );

        // Connect and initialize
        $this->server->connect($this->transport)->await();
        $this->initializeServer();

        // Test list resources
        $this->transport->clearSentMessages();
        $listResourcesRequest = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'resources/list'
        ];

        $this->transport->simulateMessage($listResourcesRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals(4, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('resources', $response['result']);
        $this->assertCount(1, $response['result']['resources']);

        $resource = $response['result']['resources'][0];
        $this->assertEquals('file://test.txt', $resource['uri']);
        $this->assertEquals('test-file', $resource['name']);
        $this->assertEquals('Test File', $resource['title']);

        // Test read resource
        $this->transport->clearSentMessages();
        $readResourceRequest = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'resources/read',
            'params' => [
                'uri' => 'file://test.txt'
            ]
        ];

        $this->transport->simulateMessage($readResourceRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals(5, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('contents', $response['result']);
        $this->assertEquals('Test file content', $response['result']['contents'][0]['text']);
    }

    public function testPromptListAndGet(): void
    {
        // Register a test prompt
        $this->server->registerPrompt(
            'greeting',
            [
                'title' => 'Greeting Prompt',
                'description' => 'Generate a greeting',
                'argsSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string']
                    ],
                    'required' => ['name']
                ]
            ],
            function (array $args): GetPromptResult {
                return new GetPromptResult([
                    ['type' => 'text', 'text' => "Hello, {$args['name']}!"]
                ]);
            }
        );

        // Connect and initialize
        $this->server->connect($this->transport)->await();
        $this->initializeServer();

        // Test list prompts
        $this->transport->clearSentMessages();
        $listPromptsRequest = [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'prompts/list'
        ];

        $this->transport->simulateMessage($listPromptsRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals(6, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('prompts', $response['result']);
        $this->assertCount(1, $response['result']['prompts']);

        $prompt = $response['result']['prompts'][0];
        $this->assertEquals('greeting', $prompt['name']);
        $this->assertEquals('Greeting Prompt', $prompt['title']);
        $this->assertEquals('Generate a greeting', $prompt['description']);

        // Test get prompt
        $this->transport->clearSentMessages();
        $getPromptRequest = [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'greeting',
                'arguments' => [
                    'name' => 'World'
                ]
            ]
        ];

        $this->transport->simulateMessage($getPromptRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals(7, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertEquals('Hello, World!', $response['result']['messages'][0]['text']);
    }

    public function testErrorHandling(): void
    {
        // Connect and initialize
        $this->server->connect($this->transport)->await();
        $this->initializeServer();

        // Test calling non-existent tool
        $this->transport->clearSentMessages();
        $callToolRequest = [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'nonexistent',
                'arguments' => []
            ]
        ];

        $this->transport->simulateMessage($callToolRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals(8, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(ErrorCode::InvalidParams->value, $response['error']['code']);
        $this->assertStringContainsString('Tool nonexistent not found', $response['error']['message']);
    }

    public function testSchemaValidationErrors(): void
    {
        // Register tool with strict schema
        $this->server->registerTool(
            'strict-tool',
            [
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'required_param' => ['type' => 'string']
                    ],
                    'required' => ['required_param']
                ]
            ],
            function (array $args): CallToolResult {
                return new CallToolResult([
                    ['type' => 'text', 'text' => 'Success']
                ]);
            }
        );

        // Connect and initialize
        $this->server->connect($this->transport)->await();
        $this->initializeServer();

        // Test with invalid arguments
        $this->transport->clearSentMessages();
        $callToolRequest = [
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/call',
            'params' => [
                'name' => 'strict-tool',
                'arguments' => [] // Missing required parameter
            ]
        ];

        $this->transport->simulateMessage($callToolRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $this->assertCount(1, $messages);

        $response = $messages[0];
        $this->assertEquals(9, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(ErrorCode::InvalidParams->value, $response['error']['code']);
        $this->assertStringContainsString('Schema validation failed', $response['error']['message']);
    }

    public function testDisabledItems(): void
    {
        // Register and then disable a tool
        $tool = $this->server->registerTool(
            'disabled-tool',
            [],
            function (): CallToolResult {
                return new CallToolResult([]);
            }
        );

        $tool->disable();

        // Connect and initialize
        $this->server->connect($this->transport)->await();
        $this->initializeServer();

        // Test that disabled tool is not listed
        $this->transport->clearSentMessages();
        $listToolsRequest = [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/list'
        ];

        $this->transport->simulateMessage($listToolsRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $response = $messages[0];
        $this->assertCount(0, $response['result']['tools'] ?? []);

        // Test that calling disabled tool returns error
        $this->transport->clearSentMessages();
        $callToolRequest = [
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'tools/call',
            'params' => [
                'name' => 'disabled-tool',
                'arguments' => []
            ]
        ];

        $this->transport->simulateMessage($callToolRequest);

        // Wait for async processing to complete
        \Amp\delay(10);

        $messages = $this->transport->getSentMessages();
        $response = $messages[0];
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('disabled', $response['error']['message']);
    }

    private function initializeServer(): void
    {
        // Simulate full initialization flow
        $initRequest = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => ProtocolConstants::LATEST_PROTOCOL_VERSION,
                'capabilities' => [],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0']
            ]
        ];

        $this->transport->simulateMessage($initRequest);
        $this->transport->clearSentMessages();

        $initializedNotification = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized'
        ];

        $this->transport->simulateMessage($initializedNotification);
    }
}
