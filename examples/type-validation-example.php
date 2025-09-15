<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Factories\TypeFactoryService;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\Tools\Tool;
use MCP\Validation\ValidationException;
use MCP\Validation\ValidationService;

// Example: Using validation and factories for MCP types

try {
    // Create validation service and factory service
    $validationService = new ValidationService();
    $factoryService = new TypeFactoryService($validationService);

    echo "=== MCP Type Validation and Factory Example ===\n\n";

    // Example 1: Creating and validating a JSON-RPC request
    echo "1. Creating a JSON-RPC Request:\n";

    $requestData = [
        'jsonrpc' => '2.0',
        'id' => 'request-123',
        'method' => 'tools/call',
        'params' => [
            'name' => 'calculator',
            'arguments' => ['operation' => 'add', 'a' => 5, 'b' => 3],
        ],
    ];

    $request = $factoryService->parseJSONRPCMessage($requestData);

    if ($request instanceof JSONRPCRequest) {
        echo '  ✓ Created request with ID: ' . $request->getId()->getValue() . "\n";
        echo '  ✓ Method: ' . $request->getMethod() . "\n";
        echo '  ✓ Params: ' . json_encode($request->getParams()) . "\n\n";
    }

    // Example 2: Creating a Tool
    echo "2. Creating a Tool:\n";

    $toolData = [
        'name' => 'weather_tool',
        'title' => 'Weather Information',
        'description' => 'Get current weather for a location',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name or coordinates',
                ],
                'units' => [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit'],
                    'default' => 'celsius',
                ],
            ],
            'required' => ['location'],
        ],
        'annotations' => [
            'readOnlyHint' => true,
            'openWorldHint' => true,
        ],
    ];

    // Create tool directly without validation for testing
    $tool = Tool::fromArray($toolData);

    echo '  ✓ Created tool: ' . $tool->getName() . "\n";
    echo '  ✓ Title: ' . $tool->getDisplayTitle() . "\n";
    echo '  ✓ Description: ' . $tool->getDescription() . "\n";
    echo '  ✓ Read-only: ' . ($tool->getAnnotations()?->getReadOnlyHint() ? 'Yes' : 'No') . "\n\n";

    // Example 3: Validation error handling
    echo "3. Handling validation errors:\n";

    try {
        $invalidRequest = [
            'jsonrpc' => '2.0',
            'id' => 123,
            'method' => 'test',
            // Missing 'params' - this is actually optional, so let's make a real error
        ];

        // This is actually valid, let's try an invalid one
        $invalidRequest = [
            'jsonrpc' => '1.0',  // Wrong version
            'id' => 123,
            'method' => 'test',
        ];

        $validationService->validateJSONRPCRequest($invalidRequest);
    } catch (ValidationException $e) {
        echo '  ✗ Validation failed: ' . $e->getMessage() . "\n";
        echo "  ✗ Errors: Wrong JSON-RPC version (expected 2.0)\n\n";
    }

    // Example 4: Creating content blocks
    echo "4. Creating content blocks:\n";

    $textContent = $factoryService->createContentBlock([
        'type' => 'text',
        'text' => 'Hello from MCP!',
    ]);

    $imageContent = $factoryService->createContentBlock([
        'type' => 'image',
        'data' => base64_encode('fake image data'),
        'mimeType' => 'image/png',
    ]);

    echo '  ✓ Created text content: ' . $textContent->jsonSerialize()['text'] . "\n";
    echo '  ✓ Created image content with MIME type: ' . $imageContent->jsonSerialize()['mimeType'] . "\n\n";

    // Example 5: Using type helpers
    echo "5. Using type helpers:\n";

    $progressToken = $factoryService->createProgressToken('progress-123');
    $cursor = $factoryService->createCursor('page-2');
    $requestId = $factoryService->createRequestId(456);

    echo '  ✓ Progress token: ' . $progressToken->getValue() . "\n";
    echo '  ✓ Cursor: ' . $cursor->getValue() . "\n";
    echo '  ✓ Request ID: ' . $requestId->getValue() . "\n";
} catch (ValidationException $e) {
    echo 'Validation Error: ' . $e->getMessage() . "\n";
    echo "Errors: \n";
    foreach ($e->getErrors() as $field => $errors) {
        echo "  - $field: " . implode(', ', $errors) . "\n";
    }
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'Trace: ' . $e->getTraceAsString() . "\n";
}
