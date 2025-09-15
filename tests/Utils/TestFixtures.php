<?php

declare(strict_types=1);

namespace MCP\Tests\Utils;

use MCP\Types\CallToolResult;
use MCP\Types\EmbeddedResource;
use MCP\Types\GetPromptResult;
use MCP\Types\ImageContent;
use MCP\Types\Implementation;
use MCP\Types\JSONRPCError;
use MCP\Types\JSONRPCRequest;
use MCP\Types\JSONRPCResponse;
use MCP\Types\ListPromptsResult;
use MCP\Types\ListResourcesResult;
use MCP\Types\ListToolsResult;
use MCP\Types\Prompt;
use MCP\Types\PromptArgument;
use MCP\Types\ReadResourceResult;
use MCP\Types\Resource;
use MCP\Types\TextContent;
use MCP\Types\Tool;

/**
 * Test fixtures and sample data for testing.
 */
class TestFixtures
{
    public static function sampleImplementation(): Implementation
    {
        return new Implementation(
            name: 'test-server',
            version: '1.0.0'
        );
    }

    public static function sampleTool(): Tool
    {
        return new Tool(
            name: 'test-tool',
            description: 'A test tool for demonstration',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'message' => [
                        'type' => 'string',
                        'description' => 'The message to process',
                    ],
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of times to repeat',
                        'minimum' => 1,
                        'maximum' => 10,
                    ],
                ],
                'required' => ['message'],
            ]
        );
    }

    public static function sampleResource(): Resource
    {
        return new Resource(
            uri: 'test://resource/sample.txt',
            name: 'Sample Resource',
            description: 'A sample resource for testing',
            mimeType: 'text/plain'
        );
    }

    public static function samplePrompt(): Prompt
    {
        return new Prompt(
            name: 'test-prompt',
            description: 'A test prompt template',
            arguments: [
                new PromptArgument(
                    name: 'topic',
                    description: 'The topic to discuss',
                    required: true
                ),
                new PromptArgument(
                    name: 'tone',
                    description: 'The tone of the response',
                    required: false
                ),
            ]
        );
    }

    public static function sampleTextContent(string $text = 'Hello, World!'): TextContent
    {
        return new TextContent(text: $text);
    }

    public static function sampleImageContent(): ImageContent
    {
        return new ImageContent(
            data: base64_encode('fake-image-data'),
            mimeType: 'image/png'
        );
    }

    public static function sampleEmbeddedResource(): EmbeddedResource
    {
        return new EmbeddedResource(
            type: 'resource',
            resource: self::sampleResource()
        );
    }

    public static function sampleJSONRPCRequest(): JSONRPCRequest
    {
        return new JSONRPCRequest(
            jsonrpc: '2.0',
            id: 1,
            method: 'test/method',
            params: ['key' => 'value']
        );
    }

    public static function sampleJSONRPCResponse(): JSONRPCResponse
    {
        return new JSONRPCResponse(
            jsonrpc: '2.0',
            id: 1,
            result: ['success' => true]
        );
    }

    public static function sampleJSONRPCError(): JSONRPCError
    {
        return new JSONRPCError(
            code: -32600,
            message: 'Invalid Request',
            data: ['details' => 'Request was malformed']
        );
    }

    public static function sampleCallToolResult(): CallToolResult
    {
        return new CallToolResult(
            content: [self::sampleTextContent('Tool executed successfully')],
            isError: false
        );
    }

    public static function sampleGetPromptResult(): GetPromptResult
    {
        return new GetPromptResult(
            description: 'Generated prompt response',
            messages: [
                [
                    'role' => 'user',
                    'content' => self::sampleTextContent('Please help me with this task'),
                ],
            ]
        );
    }

    public static function sampleReadResourceResult(): ReadResourceResult
    {
        return new ReadResourceResult(
            contents: [self::sampleTextContent('Resource content here')]
        );
    }

    public static function sampleListToolsResult(): ListToolsResult
    {
        return new ListToolsResult(
            tools: [self::sampleTool()]
        );
    }

    public static function sampleListResourcesResult(): ListResourcesResult
    {
        return new ListResourcesResult(
            resources: [self::sampleResource()]
        );
    }

    public static function sampleListPromptsResult(): ListPromptsResult
    {
        return new ListPromptsResult(
            prompts: [self::samplePrompt()]
        );
    }

    /**
     * Generate a sample JSON-RPC message for testing.
     */
    public static function sampleMessage(
        string $method = 'test/method',
        array $params = [],
        ?int $id = 1
    ): array {
        $message = [
            'jsonrpc' => '2.0',
            'method' => $method,
        ];

        if (!empty($params)) {
            $message['params'] = $params;
        }

        if ($id !== null) {
            $message['id'] = $id;
        }

        return $message;
    }

    /**
     * Generate a sample error response.
     */
    public static function sampleErrorResponse(
        int $id = 1,
        int $code = -32600,
        string $message = 'Invalid Request'
    ): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * Generate a sample success response.
     */
    public static function sampleSuccessResponse(
        int $id = 1,
        array $result = []
    ): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result ?: ['success' => true],
        ];
    }

    /**
     * Generate sample tool call parameters.
     */
    public static function sampleToolCallParams(): array
    {
        return [
            'name' => 'test-tool',
            'arguments' => [
                'message' => 'Hello from test',
                'count' => 3,
            ],
        ];
    }

    /**
     * Generate sample resource read parameters.
     */
    public static function sampleResourceReadParams(): array
    {
        return [
            'uri' => 'test://resource/sample.txt',
        ];
    }

    /**
     * Generate sample prompt get parameters.
     */
    public static function samplePromptGetParams(): array
    {
        return [
            'name' => 'test-prompt',
            'arguments' => [
                'topic' => 'artificial intelligence',
                'tone' => 'professional',
            ],
        ];
    }

    /**
     * Generate a large message for performance testing.
     */
    public static function largeMessage(int $sizeKB = 1024): array
    {
        $largeData = str_repeat('x', $sizeKB * 1024);

        return self::sampleMessage('test/large', [
            'data' => $largeData,
        ]);
    }

    /**
     * Generate multiple messages for batch testing.
     */
    public static function batchMessages(int $count = 10): array
    {
        $messages = [];
        for ($i = 0; $i < $count; $i++) {
            $messages[] = self::sampleMessage(
                'test/batch',
                ['index' => $i],
                $i + 1
            );
        }

        return $messages;
    }
}
