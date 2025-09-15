#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Types\ErrorCode;
use MCP\Types\Implementation;
use MCP\Types\McpError;

// Demonstrate the basic types
echo "PHP MCP SDK Demo\n";
echo "================\n\n";

// Create an implementation
$implementation = new Implementation(
    name: 'demo-server',
    version: '1.0.0',
    title: 'Demo MCP Server'
);

echo "Server Info:\n";
echo '- Name: ' . $implementation->getName() . "\n";
echo '- Version: ' . $implementation->getVersion() . "\n";
echo '- Title: ' . $implementation->getTitle() . "\n\n";

// Convert to array (for JSON encoding)
$serverInfo = $implementation->toArray();
echo "JSON representation:\n";
echo json_encode($serverInfo, JSON_PRETTY_PRINT) . "\n\n";

// Demonstrate error handling
try {
    // Simulate an error
    throw new McpError(
        ErrorCode::MethodNotFound,
        'Tool "unknown-tool" not found',
        ['requestedTool' => 'unknown-tool']
    );
} catch (McpError $e) {
    echo "Error caught:\n";
    echo '- Code: ' . $e->errorCode->value . "\n";
    echo '- Message: ' . $e->getMessage() . "\n";
    echo '- Data: ' . json_encode($e->data) . "\n\n";

    // Convert to JSON-RPC error format
    $jsonRpcError = $e->toJsonRpcError();
    echo "JSON-RPC error format:\n";
    echo json_encode($jsonRpcError, JSON_PRETTY_PRINT) . "\n\n";
}

// Demonstrate creating from JSON-RPC error
$errorData = [
    'code' => -32602,
    'message' => 'Invalid params',
    'data' => ['param' => 'value'],
];

$error = McpError::fromJsonRpcError($errorData);
echo "Error created from JSON-RPC:\n";
echo '- Error Code: ' . $error->errorCode->name . ' (' . $error->errorCode->value . ")\n";
echo '- Message: ' . $error->getMessage() . "\n\n";

echo "Demo completed!\n";
