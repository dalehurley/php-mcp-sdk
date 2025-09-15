#!/usr/bin/env php
<?php

/**
 * File Reader MCP Server.
 *
 * Demonstrates resource management with file system operations.
 * This server provides:
 * - File reading capabilities
 * - Directory listing
 * - File information
 * - Safe file operations with proper error handling
 *
 * Perfect example for understanding MCP resources and how to work
 * with external data sources safely.
 *
 * Usage:
 *   php file-reader-server.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;

// Create file reader server
$server = new McpServer(
    new Implementation(
        name: 'file-reader-server',
        version: '1.0.0',
        description: 'A server that provides safe file reading capabilities'
    ),
    new StdioServerTransport()
);

// Define safe directory (current working directory and subdirectories only)
$safeBasePath = getcwd();

/**
 * Check if a path is safe to access (within safe directory).
 */
function isSafePath(string $path, string $basePath): bool
{
    $realPath = realpath($path);
    $realBasePath = realpath($basePath);

    return $realPath !== false &&
        $realBasePath !== false &&
        strpos($realPath, $realBasePath) === 0;
}

// Tool: Read file contents
$server->addTool(
    name: 'read_file',
    description: 'Read the contents of a text file',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'path' => [
                'type' => 'string',
                'description' => 'Path to the file to read (relative to current directory)',
            ],
        ],
        'required' => ['path'],
    ],
    handler: function (array $args) use ($safeBasePath): array {
        $filePath = $args['path'];

        // Security check
        if (!isSafePath($filePath, $safeBasePath)) {
            throw new McpError(
                code: -32602,
                message: 'Access denied: Path is outside safe directory'
            );
        }

        if (!file_exists($filePath)) {
            throw new McpError(
                code: -32602,
                message: "File not found: {$filePath}"
            );
        }

        if (!is_readable($filePath)) {
            throw new McpError(
                code: -32602,
                message: "File is not readable: {$filePath}"
            );
        }

        if (is_dir($filePath)) {
            throw new McpError(
                code: -32602,
                message: "Path is a directory, not a file: {$filePath}"
            );
        }

        $contents = file_get_contents($filePath);
        $fileSize = filesize($filePath);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "File: {$filePath}\n" .
                        "Size: {$fileSize} bytes\n" .
                        "Contents:\n" .
                        "---\n" .
                        $contents,
                ],
            ],
        ];
    }
);

// Tool: List directory contents
$server->addTool(
    name: 'list_directory',
    description: 'List the contents of a directory',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'path' => [
                'type' => 'string',
                'description' => 'Path to the directory to list (default: current directory)',
                'default' => '.',
            ],
        ],
    ],
    handler: function (array $args) use ($safeBasePath): array {
        $dirPath = $args['path'] ?? '.';

        // Security check
        if (!isSafePath($dirPath, $safeBasePath)) {
            throw new McpError(
                code: -32602,
                message: 'Access denied: Path is outside safe directory'
            );
        }

        if (!is_dir($dirPath)) {
            throw new McpError(
                code: -32602,
                message: "Not a directory: {$dirPath}"
            );
        }

        $items = scandir($dirPath);
        if ($items === false) {
            throw new McpError(
                code: -32602,
                message: "Cannot read directory: {$dirPath}"
            );
        }

        $listing = "Directory: {$dirPath}\n\n";

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $dirPath . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($itemPath);
            $size = $isDir ? '' : ' (' . filesize($itemPath) . ' bytes)';
            $type = $isDir ? '[DIR]' : '[FILE]';

            $listing .= "{$type} {$item}{$size}\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $listing,
                ],
            ],
        ];
    }
);

// Tool: Get file information
$server->addTool(
    name: 'file_info',
    description: 'Get detailed information about a file or directory',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'path' => [
                'type' => 'string',
                'description' => 'Path to the file or directory',
            ],
        ],
        'required' => ['path'],
    ],
    handler: function (array $args) use ($safeBasePath): array {
        $path = $args['path'];

        // Security check
        if (!isSafePath($path, $safeBasePath)) {
            throw new McpError(
                code: -32602,
                message: 'Access denied: Path is outside safe directory'
            );
        }

        if (!file_exists($path)) {
            throw new McpError(
                code: -32602,
                message: "Path not found: {$path}"
            );
        }

        $stat = stat($path);
        $isDir = is_dir($path);
        $isFile = is_file($path);
        $isReadable = is_readable($path);
        $isWritable = is_writable($path);

        $info = "Path: {$path}\n";
        $info .= 'Type: ' . ($isDir ? 'Directory' : ($isFile ? 'File' : 'Other')) . "\n";
        $info .= 'Size: ' . $stat['size'] . " bytes\n";
        $info .= 'Permissions: ' . substr(sprintf('%o', fileperms($path)), -4) . "\n";
        $info .= 'Readable: ' . ($isReadable ? 'Yes' : 'No') . "\n";
        $info .= 'Writable: ' . ($isWritable ? 'Yes' : 'No') . "\n";
        $info .= 'Last Modified: ' . date('Y-m-d H:i:s', $stat['mtime']) . "\n";
        $info .= 'Last Accessed: ' . date('Y-m-d H:i:s', $stat['atime']) . "\n";

        if ($isFile) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $info .= 'Extension: ' . ($extension ?: 'none') . "\n";

            // Try to detect MIME type
            if (function_exists('mime_content_type')) {
                $mimeType = mime_content_type($path);
                $info .= 'MIME Type: ' . ($mimeType ?: 'unknown') . "\n";
            }
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $info,
                ],
            ],
        ];
    }
);

// Resource: Current working directory
$server->addResource(
    uri: 'file://cwd',
    name: 'Current Working Directory',
    description: 'Information about the current working directory',
    mimeType: 'text/plain',
    handler: function () use ($safeBasePath): string {
        $info = "Current Working Directory: {$safeBasePath}\n\n";
        $info .= "This file server operates within this directory for security.\n";
        $info .= "Available operations:\n";
        $info .= "- read_file: Read text file contents\n";
        $info .= "- list_directory: List directory contents\n";
        $info .= "- file_info: Get detailed file/directory information\n\n";
        $info .= "All paths are relative to: {$safeBasePath}\n";

        return $info;
    }
);

// Resource: Server statistics
$server->addResource(
    uri: 'file://stats',
    name: 'Server Statistics',
    description: 'Statistics about file operations',
    mimeType: 'application/json',
    handler: function () use ($safeBasePath): string {
        $stats = [
            'safe_base_path' => $safeBasePath,
            'php_version' => PHP_VERSION,
            'server_time' => date('c'),
            'available_tools' => ['read_file', 'list_directory', 'file_info'],
            'security_features' => [
                'path_validation' => true,
                'safe_directory_restriction' => true,
                'permission_checking' => true,
            ],
        ];

        return json_encode($stats, JSON_PRETTY_PRINT);
    }
);

// Prompt: File operations help
$server->addPrompt(
    name: 'file_help',
    description: 'Get help with file operations',
    handler: function (): array {
        return [
            'description' => 'File Reader Server Help',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I use the file reader server?',
                        ],
                    ],
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "The File Reader Server provides safe file system access:\n\n" .
                                "**Available Tools:**\n" .
                                "• **read_file** - Read text file contents\n" .
                                "  Example: read_file({\"path\": \"README.md\"})\n\n" .
                                "• **list_directory** - List directory contents\n" .
                                "  Example: list_directory({\"path\": \".\"})\n\n" .
                                "• **file_info** - Get detailed file information\n" .
                                "  Example: file_info({\"path\": \"composer.json\"})\n\n" .
                                "**Security Features:**\n" .
                                "• All access restricted to current directory and subdirectories\n" .
                                "• Permission checking before file operations\n" .
                                "• Path validation to prevent directory traversal\n\n" .
                                "Try: 'List the files in the current directory'",
                        ],
                    ],
                ],
            ],
        ];
    }
);

// Start the server
echo "📁 File Reader MCP Server starting...\n";
echo "Safe directory: {$safeBasePath}\n";
echo "Available operations: read_file, list_directory, file_info\n" . PHP_EOL;
$server->run();
