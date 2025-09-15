#!/usr/bin/env php
<?php

/**
 * Resource Server Example.
 *
 * This example demonstrates how to create an MCP server with:
 * - Static resources (configuration files, documentation)
 * - Dynamic resources with URI templates
 * - Resource subscriptions and notifications
 * - File system resources
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load required files to ensure all classes are available
require_once __DIR__ . '/../../src/Shared/Protocol.php';
require_once __DIR__ . '/../../src/Server/RegisteredItems.php';
require_once __DIR__ . '/../../src/Server/ResourceTemplate.php';
require_once __DIR__ . '/../../src/Server/ServerOptions.php';
require_once __DIR__ . '/../../src/Server/Server.php';

use function Amp\async;

use MCP\Server\McpServer;
use MCP\Server\ResourceTemplate;
use MCP\Server\ServerOptions;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Implementation;
use MCP\Types\Notifications\ResourceListChangedNotification;
use MCP\Types\Resources\TextResourceContents;
use MCP\Types\Results\ListResourcesResult;
use MCP\Types\Results\ReadResourceResult;

// Create the server with resource capabilities
$server = new McpServer(
    new Implementation('resource-server', '1.0.0', 'Resource Management Server'),
    new ServerOptions(
        capabilities: new ServerCapabilities(
            resources: ['subscribe' => true, 'listChanged' => true]
        ),
        instructions: 'This server provides various types of resources including static files, dynamic content, and file system access.'
    )
);

// In-memory resource storage for dynamic resources
$dynamicResources = [];
$resourceSubscribers = [];

// Register static configuration resource
$server->resource(
    'config',
    'config://server.json',
    [
        'title' => 'Server Configuration',
        'description' => 'Current server configuration and settings',
        'mimeType' => 'application/json',
    ],
    function ($uri, $extra) {
        $config = [
            'server' => [
                'name' => 'resource-server',
                'version' => '1.0.0',
                'started_at' => date('c'),
                'features' => [
                    'static_resources',
                    'dynamic_resources',
                    'file_system_access',
                    'resource_subscriptions',
                ],
            ],
            'resources' => [
                'total_static' => 3,
                'total_dynamic' => count($GLOBALS['dynamicResources']),
                'subscriptions_active' => count($GLOBALS['resourceSubscribers']),
            ],
        ];

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: $uri,
                    text: json_encode($config, JSON_PRETTY_PRINT),
                    mimeType: 'application/json'
                ),
            ]
        );
    }
);

// Register documentation resource
$server->resource(
    'docs',
    'docs://README.md',
    [
        'title' => 'Server Documentation',
        'description' => 'How to use this resource server',
        'mimeType' => 'text/markdown',
    ],
    function ($uri, $extra) {
        $docs = <<<'EOF'
            # Resource Server Documentation

            This MCP server demonstrates various resource management capabilities:

            ## Available Resources

            ### Static Resources
            - `config://server.json` - Server configuration and status
            - `docs://README.md` - This documentation
            - `logs://latest.log` - Recent server activity logs

            ### Dynamic Resources
            - `data://items/{id}` - Dynamic data items (create via tools)
            - `cache://keys/{key}` - Cached values with TTL

            ### File System Resources
            - `file:///path/to/file` - Direct file system access (read-only)

            ## Tools Available

            - `create-resource` - Create a new dynamic resource
            - `cache-set` - Store a value in cache with TTL
            - `list-directory` - List files in a directory

            ## Resource Subscriptions

            This server supports resource subscriptions. When you subscribe to resources,
            you'll receive notifications when:
            - New dynamic resources are created
            - Cache entries are added or expire
            - File system changes are detected (if watching is enabled)

            ## Usage Examples

            1. List all resources: Use the MCP client's `listResources()` method
            2. Read a resource: Use `readResource()` with the URI
            3. Subscribe to changes: Use `subscribeToResources()` 
            4. Create dynamic content: Call the `create-resource` tool

            EOF;

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: $uri,
                    text: $docs,
                    mimeType: 'text/markdown'
                ),
            ]
        );
    }
);

// Register logs resource with recent activity
$server->resource(
    'logs',
    'logs://latest.log',
    [
        'title' => 'Server Activity Log',
        'description' => 'Recent server activity and requests',
        'mimeType' => 'text/plain',
    ],
    function ($uri, $extra) {
        // Generate some sample log entries
        $logEntries = [
            '[' . date('Y-m-d H:i:s') . '] INFO: Server started',
            '[' . date('Y-m-d H:i:s', time() - 60) . '] INFO: Resource config://server.json accessed',
            '[' . date('Y-m-d H:i:s', time() - 120) . '] INFO: Dynamic resource created: data://items/user-123',
            '[' . date('Y-m-d H:i:s', time() - 180) . '] INFO: Cache entry set: cache://keys/session-abc',
            '[' . date('Y-m-d H:i:s', time() - 240) . '] INFO: Resource subscription established',
        ];

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: $uri,
                    text: implode("\n", $logEntries) . "\n",
                    mimeType: 'text/plain'
                ),
            ]
        );
    }
);

// Register dynamic resource template for data items
$dataTemplate = new ResourceTemplate(
    'data/items/{id}',
    [
        'list' => function ($extra) use (&$dynamicResources) {
            $resources = [];
            foreach ($dynamicResources as $id => $data) {
                $resources[] = [
                    'uri' => "data://items/$id",
                    'name' => "item-$id",
                    'title' => $data['title'] ?? "Item $id",
                    'description' => $data['description'] ?? 'Dynamic data item',
                    'mimeType' => 'application/json',
                ];
            }

            return new ListResourcesResult($resources);
        },
    ]
);

$server->resource(
    'data-items',
    $dataTemplate,
    [
        'title' => 'Dynamic Data Items',
        'description' => 'Dynamically created data resources',
    ],
    function ($uri, $variables, $extra) use (&$dynamicResources) {
        $id = $variables['id'] ?? 'unknown';

        if (!isset($dynamicResources[$id])) {
            throw new \Exception("Resource not found: $id");
        }

        $data = $dynamicResources[$id];
        $data['id'] = $id;
        $data['accessed_at'] = date('c');

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: $uri,
                    text: json_encode($data, JSON_PRETTY_PRINT),
                    mimeType: 'application/json'
                ),
            ]
        );
    }
);

// Register cache resource template
$cacheTemplate = new ResourceTemplate(
    'cache/keys/{key}',
    [
        'list' => function ($extra) {
            // In a real implementation, this would list actual cache keys
            return new ListResourcesResult([
                [
                    'uri' => 'cache://keys/example',
                    'name' => 'cache-example',
                    'title' => 'Example Cache Entry',
                    'description' => 'Sample cached value',
                ],
            ]);
        },
    ]
);

$server->resource(
    'cache-keys',
    $cacheTemplate,
    [
        'title' => 'Cache Entries',
        'description' => 'Cached values with TTL',
    ],
    function ($uri, $variables, $extra) {
        $key = $variables['key'] ?? 'unknown';

        // Simulate cache lookup
        $cacheData = [
            'key' => $key,
            'value' => "Cached value for $key",
            'created_at' => date('c', time() - rand(0, 3600)),
            'ttl' => rand(300, 3600),
            'hit_count' => rand(1, 100),
        ];

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: $uri,
                    text: json_encode($cacheData, JSON_PRETTY_PRINT),
                    mimeType: 'application/json'
                ),
            ]
        );
    }
);

// Tool to create dynamic resources
$server->tool(
    'create-resource',
    'Create a new dynamic resource',
    [
        'id' => [
            'type' => 'string',
            'description' => 'Unique identifier for the resource',
        ],
        'title' => [
            'type' => 'string',
            'description' => 'Human-readable title',
        ],
        'data' => [
            'type' => 'object',
            'description' => 'The data content for the resource',
        ],
    ],
    function (array $args) use (&$dynamicResources, &$resourceSubscribers, $server) {
        $id = $args['id'] ?? uniqid();
        $title = $args['title'] ?? "Resource $id";
        $data = $args['data'] ?? [];

        // Store the resource
        $dynamicResources[$id] = [
            'title' => $title,
            'description' => 'Dynamic resource created at ' . date('c'),
            'data' => $data,
            'created_at' => date('c'),
        ];

        // Notify subscribers of the new resource
        if (!empty($resourceSubscribers)) {
            $notification = new ResourceListChangedNotification();
            foreach ($resourceSubscribers as $subscriber) {
                // In a real implementation, send notification to subscriber
                error_log("Notifying subscriber of new resource: data://items/$id");
            }
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Created resource: data://items/$id\nTitle: $title\nData: " . json_encode($data),
                ],
            ],
        ];
    }
);

// Tool to set cache values
$server->tool(
    'cache-set',
    'Store a value in cache with TTL',
    [
        'key' => [
            'type' => 'string',
            'description' => 'Cache key',
        ],
        'value' => [
            'type' => 'string',
            'description' => 'Value to cache',
        ],
        'ttl' => [
            'type' => 'integer',
            'description' => 'Time to live in seconds',
            'default' => 3600,
        ],
    ],
    function (array $args) {
        $key = $args['key'] ?? '';
        $value = $args['value'] ?? '';
        $ttl = $args['ttl'] ?? 3600;

        // In a real implementation, this would store in actual cache
        $cacheInfo = [
            'key' => $key,
            'value' => $value,
            'ttl' => $ttl,
            'expires_at' => date('c', time() + $ttl),
            'uri' => "cache://keys/$key",
        ];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Cached value for key '$key' with TTL of {$ttl}s\nExpires: " . $cacheInfo['expires_at'],
                ],
            ],
        ];
    }
);

// Tool to list directory contents (file system access)
$server->tool(
    'list-directory',
    'List contents of a directory',
    [
        'path' => [
            'type' => 'string',
            'description' => 'Directory path to list',
        ],
        'include_hidden' => [
            'type' => 'boolean',
            'description' => 'Include hidden files',
            'default' => false,
        ],
    ],
    function (array $args) {
        $path = $args['path'] ?? '.';
        $includeHidden = $args['include_hidden'] ?? false;

        // Security: restrict to safe directories
        $safePath = realpath($path);
        if ($safePath === false || !is_dir($safePath)) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => "Directory not found or not accessible: $path"],
                ],
                'isError' => true,
            ];
        }

        // Basic security check - don't allow access outside project
        $projectRoot = realpath(__DIR__ . '/../..');
        if (strpos($safePath, $projectRoot) !== 0) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => 'Access denied: Path outside project directory'],
                ],
                'isError' => true,
            ];
        }

        try {
            $files = [];
            $iterator = new \DirectoryIterator($safePath);

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }

                $fileName = $fileInfo->getFilename();
                if (!$includeHidden && $fileName[0] === '.') {
                    continue;
                }

                $files[] = [
                    'name' => $fileName,
                    'type' => $fileInfo->isDir() ? 'directory' : 'file',
                    'size' => $fileInfo->isFile() ? $fileInfo->getSize() : null,
                    'modified' => date('c', $fileInfo->getMTime()),
                    'uri' => 'file://' . $fileInfo->getRealPath(),
                ];
            }

            // Sort by name
            usort($files, fn ($a, $b) => strcmp($a['name'], $b['name']));

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Directory listing for: $path\n\n" .
                            json_encode($files, JSON_PRETTY_PRINT),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => 'Error listing directory: ' . $e->getMessage()],
                ],
                'isError' => true,
            ];
        }
    }
);

// Start background task to simulate resource changes
async(function () use (&$dynamicResources, &$resourceSubscribers) {
    while (true) {
        \Amp\delay(30); // Wait 30 seconds

        // Simulate creating a new resource
        $id = 'auto-' . uniqid();
        $dynamicResources[$id] = [
            'title' => 'Auto-generated Resource',
            'description' => 'Automatically created at ' . date('c'),
            'data' => [
                'random_value' => rand(1, 1000),
                'timestamp' => time(),
            ],
            'created_at' => date('c'),
        ];

        error_log("Auto-created resource: data://items/$id");

        // In a real implementation, notify subscribers here
        if (!empty($resourceSubscribers)) {
            error_log('Notifying ' . count($resourceSubscribers) . ' subscribers of resource change');
        }
    }
});

// Set up the transport and start the server
async(function () use ($server) {
    try {
        $transport = new StdioServerTransport();

        echo "Starting Resource Server on stdio...\n";
        echo "This server provides static, dynamic, and file system resources.\n";
        echo "Use the MCP client to explore available resources and create new ones.\n\n";

        $server->connect($transport)->await();
    } catch (\Throwable $e) {
        error_log('Server error: ' . $e->getMessage());
        exit(1);
    }
})->await();
