<?php

/**
 * Example demonstrating the protocol message types in PHP MCP SDK.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Cursor;
use MCP\Types\Implementation;
use MCP\Types\Messages\ClientRequest;
use MCP\Types\Messages\ServerResult;
use MCP\Types\Notifications\InitializedNotification;
use MCP\Types\Notifications\ProgressNotification;
use MCP\Types\Progress;
use MCP\Types\ProgressToken;
use MCP\Types\Protocol;
use MCP\Types\Requests\InitializeRequest;
use MCP\Types\Requests\ListResourcesRequest;
use MCP\Types\Resources\Resource;
use MCP\Types\Results\InitializeResult;
use MCP\Types\Results\ListResourcesResult;

echo "=== PHP MCP SDK Protocol Messages Example ===\n\n";

// Example 1: Client sends Initialize Request
echo "1. Client Initialize Request:\n";
$clientInfo = new Implementation('example-client', '1.0.0', 'Example Client');
$clientCapabilities = ClientCapabilities::fromArray([
    'sampling' => [],
    'roots' => ['listChanged' => true],
]);

$initRequest = InitializeRequest::create(
    Protocol::LATEST_PROTOCOL_VERSION,
    $clientCapabilities,
    $clientInfo
);

echo json_encode($initRequest->jsonSerialize(), JSON_PRETTY_PRINT) . "\n\n";

// Example 2: Server responds with Initialize Result
echo "2. Server Initialize Result:\n";
$serverInfo = new Implementation('example-server', '1.0.0', 'Example Server');
$serverCapabilities = ServerCapabilities::fromArray([
    'prompts' => ['listChanged' => true],
    'resources' => ['subscribe' => true, 'listChanged' => true],
    'tools' => ['listChanged' => true],
    'logging' => [],
]);

$initResult = new InitializeResult(
    Protocol::DEFAULT_NEGOTIATED_PROTOCOL_VERSION,
    $serverCapabilities,
    $serverInfo,
    'Welcome to Example Server! Use "help" command for available tools.'
);

echo json_encode($initResult->jsonSerialize(), JSON_PRETTY_PRINT) . "\n\n";

// Example 3: Client sends Initialized Notification
echo "3. Client Initialized Notification:\n";
$initializedNotif = InitializedNotification::create();
echo json_encode($initializedNotif->jsonSerialize(), JSON_PRETTY_PRINT) . "\n\n";

// Example 4: List Resources with Pagination
echo "4. List Resources Request with Pagination:\n";
$listResourcesReq = ListResourcesRequest::create();
$cursor = new Cursor('page2');
$listResourcesReqWithCursor = $listResourcesReq->withCursor($cursor);
echo json_encode($listResourcesReqWithCursor->jsonSerialize(), JSON_PRETTY_PRINT) . "\n\n";

// Example 5: List Resources Result
echo "5. List Resources Result:\n";
$resources = [
    Resource::fromArray([
        'name' => 'config',
        'uri' => 'file:///app/config.json',
        'title' => 'Application Configuration',
        'description' => 'Main configuration file for the application',
        'mimeType' => 'application/json',
    ]),
    Resource::fromArray([
        'name' => 'database',
        'uri' => 'file:///app/db/schema.sql',
        'title' => 'Database Schema',
        'description' => 'SQL schema for the application database',
        'mimeType' => 'application/sql',
    ]),
];

$nextCursor = new Cursor('page3');
$listResourcesResult = new ListResourcesResult($resources, $nextCursor);
echo json_encode($listResourcesResult->jsonSerialize(), JSON_PRETTY_PRINT) . "\n\n";

// Example 6: Progress Notification
echo "6. Progress Notification:\n";
$progressToken = ProgressToken::fromString('task-123');
$progress = new Progress(
    progress: 45.0,
    total: 100.0,
    message: 'Processing files...'
);
$progressNotif = ProgressNotification::create($progressToken, $progress);
echo json_encode($progressNotif->jsonSerialize(), JSON_PRETTY_PRINT) . "\n\n";

// Example 7: Using Message Union Helpers
echo "7. Parsing Client Request using Union Helper:\n";
$requestData = [
    'method' => 'resources/list',
    'params' => ['cursor' => 'page2'],
];

try {
    if (ClientRequest::isValidMethod($requestData['method'])) {
        $parsedRequest = ClientRequest::fromArray($requestData);
        echo 'Parsed request type: ' . get_class($parsedRequest) . "\n";
        if ($parsedRequest instanceof ListResourcesRequest) {
            echo 'Has cursor: ' . ($parsedRequest->getCursor() ? 'Yes' : 'No') . "\n";
        }
    }
} catch (\Exception $e) {
    echo 'Error parsing request: ' . $e->getMessage() . "\n";
}

echo "\n";

// Example 8: Parsing Server Result
echo "8. Parsing Server Result using Union Helper:\n";
$resultData = [
    'protocolVersion' => Protocol::LATEST_PROTOCOL_VERSION,
    'capabilities' => $serverCapabilities->jsonSerialize(),
    'serverInfo' => $serverInfo->jsonSerialize(),
];

try {
    $parsedResult = ServerResult::fromArray($resultData);
    echo 'Parsed result type: ' . get_class($parsedResult) . "\n";
    if ($parsedResult instanceof InitializeResult) {
        echo 'Server version: ' . $parsedResult->getServerInfo()->getVersion() . "\n";
    }
} catch (\Exception $e) {
    echo 'Error parsing result: ' . $e->getMessage() . "\n";
}

echo "\n=== Example Complete ===\n";
