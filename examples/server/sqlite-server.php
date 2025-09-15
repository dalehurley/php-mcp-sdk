#!/usr/bin/env php
<?php

/**
 * SQLite Database Server Example.
 *
 * This example demonstrates how to create an MCP server that:
 * - Connects to SQLite database
 * - Provides database schema as resources
 * - Executes safe SQL queries
 * - Implements query result caching
 * - Provides database management tools
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
use MCP\Server\ServerOptions;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Content\TextContent;
use MCP\Types\Implementation;
use MCP\Types\Resources\TextResourceContents;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\ReadResourceResult;

// Create the server
$server = new McpServer(
    new Implementation('sqlite-server', '1.0.0', 'SQLite Database Server'),
    new ServerOptions(
        capabilities: new ServerCapabilities(
            tools: ['listChanged' => true],
            resources: ['listChanged' => true]
        ),
        instructions: 'This server provides safe access to SQLite databases with schema inspection and query execution.'
    )
);

// Database configuration
$dbPath = __DIR__ . '/example.db';
$pdo = null;

// Initialize database with sample data
function initializeDatabase(string $dbPath): \PDO
{
    $isNewDb = !file_exists($dbPath);

    $pdo = new \PDO("sqlite:$dbPath", null, null, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);

    if ($isNewDb) {
        echo "Creating new SQLite database with sample data...\n";

        // Create tables
        $pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                full_name TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT 1
            )
        ');

        $pdo->exec('
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $pdo->exec('
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES posts(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $pdo->exec('
            CREATE INDEX idx_posts_user_id ON posts(user_id);
            CREATE INDEX idx_comments_post_id ON comments(post_id);
            CREATE INDEX idx_comments_user_id ON comments(user_id);
        ');

        // Insert sample data
        $users = [
            ['alice', 'alice@example.com', 'Alice Johnson'],
            ['bob', 'bob@example.com', 'Bob Smith'],
            ['charlie', 'charlie@example.com', 'Charlie Brown'],
            ['diana', 'diana@example.com', 'Diana Prince'],
        ];

        $stmt = $pdo->prepare('INSERT INTO users (username, email, full_name) VALUES (?, ?, ?)');
        foreach ($users as $user) {
            $stmt->execute($user);
        }

        // Sample posts
        $posts = [
            [1, 'Getting Started with MCP', 'This is an introduction to the Model Context Protocol...'],
            [1, 'PHP MCP SDK Features', 'The PHP MCP SDK provides comprehensive tools for...'],
            [2, 'Database Integration', 'Working with databases in MCP servers is straightforward...'],
            [3, 'Security Best Practices', 'When building MCP servers, security should be a top priority...'],
            [4, 'Real-world Applications', 'Here are some practical examples of MCP servers in action...'],
        ];

        $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)');
        foreach ($posts as $post) {
            $stmt->execute($post);
        }

        // Sample comments
        $comments = [
            [1, 2, 'Great explanation! Very helpful.'],
            [1, 3, 'Thanks for sharing this.'],
            [2, 1, 'Looking forward to trying this out.'],
            [2, 4, 'Security is indeed crucial.'],
            [3, 2, 'The database features look promising.'],
            [4, 5, 'Excellent real-world examples!'],
        ];

        $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)');
        foreach ($comments as $comment) {
            $stmt->execute($comment);
        }

        echo "Sample database created successfully!\n";
    }

    return $pdo;
}

// Initialize database
try {
    $pdo = initializeDatabase($dbPath);
} catch (\Exception $e) {
    echo 'Failed to initialize database: ' . $e->getMessage() . "\n";
    exit(1);
}

// Query result cache
$queryCache = [];
$cacheTimeout = 300; // 5 minutes

// Register database schema resource
$server->resource(
    'schema',
    'db://schema.sql',
    [
        'title' => 'Database Schema',
        'description' => 'Complete database schema with table definitions',
        'mimeType' => 'application/sql',
    ],
    function ($uri, $extra) use ($pdo) {
        try {
            // Get table schema
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll();

            $schema = "-- Database Schema\n";
            $schema .= '-- Generated at ' . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                $tableName = $table['name'];

                // Get CREATE TABLE statement
                $createStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$tableName'")->fetchColumn();
                $schema .= "$createStmt;\n\n";

                // Get indexes for this table
                $indexes = $pdo->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='$tableName' AND sql IS NOT NULL")->fetchAll();
                foreach ($indexes as $index) {
                    $schema .= $index['sql'] . ";\n";
                }
                $schema .= "\n";
            }

            return new ReadResourceResult(
                contents: [
                    new TextResourceContents(
                        uri: $uri,
                        text: $schema,
                        mimeType: 'application/sql'
                    ),
                ]
            );
        } catch (\Exception $e) {
            throw new \Exception('Failed to generate schema: ' . $e->getMessage());
        }
    }
);

// Register table data resources
$server->resource(
    'tables',
    'db://tables/{table}',
    [
        'title' => 'Table Data',
        'description' => 'View data from database tables',
    ],
    function ($uri, $variables, $extra) use ($pdo) {
        $tableName = $variables['table'] ?? 'unknown';

        // Security: validate table name
        $validTables = ['users', 'posts', 'comments'];
        if (!in_array($tableName, $validTables)) {
            throw new \Exception("Invalid table name: $tableName");
        }

        try {
            // Get table structure
            $columns = $pdo->query("PRAGMA table_info($tableName)")->fetchAll();

            // Get sample data (limit to 10 rows)
            $data = $pdo->query("SELECT * FROM $tableName LIMIT 10")->fetchAll();

            $output = "Table: $tableName\n";
            $output .= str_repeat('=', strlen("Table: $tableName")) . "\n\n";

            $output .= "Columns:\n";
            foreach ($columns as $col) {
                $output .= "- {$col['name']} ({$col['type']})";
                if ($col['pk']) {
                    $output .= ' PRIMARY KEY';
                }
                if ($col['notnull']) {
                    $output .= ' NOT NULL';
                }
                if ($col['dflt_value'] !== null) {
                    $output .= " DEFAULT {$col['dflt_value']}";
                }
                $output .= "\n";
            }

            $output .= "\nSample Data (first 10 rows):\n";
            $output .= json_encode($data, JSON_PRETTY_PRINT);

            return new ReadResourceResult(
                contents: [
                    new TextResourceContents(
                        uri: $uri,
                        text: $output,
                        mimeType: 'text/plain'
                    ),
                ]
            );
        } catch (\Exception $e) {
            throw new \Exception("Failed to read table $tableName: " . $e->getMessage());
        }
    }
);

// Tool to execute SELECT queries
$server->tool(
    'query-select',
    'Execute a SELECT query safely',
    [
        'query' => [
            'type' => 'string',
            'description' => 'SELECT query to execute (SELECT statements only)',
        ],
        'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of rows to return',
            'default' => 100,
            'maximum' => 1000,
        ],
    ],
    function (array $args) use ($pdo, &$queryCache, $cacheTimeout) {
        $query = trim($args['query'] ?? '');
        $limit = min(1000, max(1, $args['limit'] ?? 100));

        if (empty($query)) {
            return new CallToolResult(
                content: [new TextContent('Query is required')],
                isError: true
            );
        }

        // Security: only allow SELECT statements
        if (!preg_match('/^\s*SELECT\s+/i', $query)) {
            return new CallToolResult(
                content: [new TextContent('Only SELECT queries are allowed')],
                isError: true
            );
        }

        // Check for dangerous operations
        $forbidden = ['DELETE', 'UPDATE', 'INSERT', 'DROP', 'ALTER', 'CREATE', 'PRAGMA'];
        foreach ($forbidden as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $query)) {
                return new CallToolResult(
                    content: [new TextContent("Forbidden keyword '$keyword' in query")],
                    isError: true
                );
            }
        }

        // Check cache
        $cacheKey = md5($query . $limit);
        if (isset($queryCache[$cacheKey])) {
            $cached = $queryCache[$cacheKey];
            if (time() - $cached['timestamp'] < $cacheTimeout) {
                $result = $cached['data'];
                $result['cached'] = true;
                $result['cache_age'] = time() - $cached['timestamp'];

                return new CallToolResult(
                    content: [new TextContent(
                        "Query Results (cached):\n" .
                            json_encode($result, JSON_PRETTY_PRINT)
                    )]
                );
            } else {
                unset($queryCache[$cacheKey]);
            }
        }

        try {
            // Add LIMIT if not present
            if (!preg_match('/\bLIMIT\s+\d+/i', $query)) {
                $query .= " LIMIT $limit";
            }

            $startTime = microtime(true);
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll();
            $executionTime = microtime(true) - $startTime;

            $result = [
                'query' => $query,
                'row_count' => count($results),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'data' => $results,
                'cached' => false,
            ];

            // Cache the result
            $queryCache[$cacheKey] = [
                'data' => $result,
                'timestamp' => time(),
            ];

            return new CallToolResult(
                content: [new TextContent(
                    "Query Results:\n" .
                        json_encode($result, JSON_PRETTY_PRINT)
                )]
            );
        } catch (\Exception $e) {
            return new CallToolResult(
                content: [new TextContent('Query failed: ' . $e->getMessage())],
                isError: true
            );
        }
    }
);

// Tool to get table statistics
$server->tool(
    'table-stats',
    'Get statistics for database tables',
    [
        'table' => [
            'type' => 'string',
            'description' => 'Table name (optional - if not provided, shows all tables)',
            'enum' => ['users', 'posts', 'comments'],
        ],
    ],
    function (array $args) use ($pdo) {
        $tableName = $args['table'] ?? null;

        try {
            if ($tableName) {
                // Stats for specific table
                $validTables = ['users', 'posts', 'comments'];
                if (!in_array($tableName, $validTables)) {
                    return new CallToolResult(
                        content: [new TextContent("Invalid table name: $tableName")],
                        isError: true
                    );
                }

                $stats = getTableStats($pdo, $tableName);

                return new CallToolResult(
                    content: [new TextContent(
                        "Statistics for table '$tableName':\n" .
                            json_encode($stats, JSON_PRETTY_PRINT)
                    )]
                );
            } else {
                // Stats for all tables
                $allStats = [];
                $tables = ['users', 'posts', 'comments'];

                foreach ($tables as $table) {
                    $allStats[$table] = getTableStats($pdo, $table);
                }

                return new CallToolResult(
                    content: [new TextContent(
                        "Database Statistics:\n" .
                            json_encode($allStats, JSON_PRETTY_PRINT)
                    )]
                );
            }
        } catch (\Exception $e) {
            return new CallToolResult(
                content: [new TextContent('Failed to get statistics: ' . $e->getMessage())],
                isError: true
            );
        }
    }
);

// Tool to search across tables
$server->tool(
    'search',
    'Search for text across database tables',
    [
        'term' => [
            'type' => 'string',
            'description' => 'Search term',
        ],
        'tables' => [
            'type' => 'array',
            'description' => 'Tables to search in',
            'items' => [
                'type' => 'string',
                'enum' => ['users', 'posts', 'comments'],
            ],
            'default' => ['users', 'posts', 'comments'],
        ],
    ],
    function (array $args) use ($pdo) {
        $term = $args['term'] ?? '';
        $tables = $args['tables'] ?? ['users', 'posts', 'comments'];

        if (empty($term)) {
            return new CallToolResult(
                content: [new TextContent('Search term is required')],
                isError: true
            );
        }

        if (strlen($term) < 3) {
            return new CallToolResult(
                content: [new TextContent('Search term must be at least 3 characters')],
                isError: true
            );
        }

        try {
            $results = [];
            $searchTerm = "%$term%";

            foreach ($tables as $table) {
                switch ($table) {
                    case 'users':
                        $stmt = $pdo->prepare("
                            SELECT 'users' as table_name, id, username, email, full_name, created_at
                            FROM users 
                            WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?
                            LIMIT 10
                        ");
                        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
                        break;

                    case 'posts':
                        $stmt = $pdo->prepare("
                            SELECT 'posts' as table_name, p.id, p.title, p.content, u.username, p.created_at
                            FROM posts p
                            JOIN users u ON p.user_id = u.id
                            WHERE p.title LIKE ? OR p.content LIKE ?
                            LIMIT 10
                        ");
                        $stmt->execute([$searchTerm, $searchTerm]);
                        break;

                    case 'comments':
                        $stmt = $pdo->prepare("
                            SELECT 'comments' as table_name, c.id, c.content, u.username, p.title as post_title, c.created_at
                            FROM comments c
                            JOIN users u ON c.user_id = u.id
                            JOIN posts p ON c.post_id = p.id
                            WHERE c.content LIKE ?
                            LIMIT 10
                        ");
                        $stmt->execute([$searchTerm]);
                        break;
                }

                $tableResults = $stmt->fetchAll();
                if (!empty($tableResults)) {
                    $results[$table] = $tableResults;
                }
            }

            if (empty($results)) {
                return new CallToolResult(
                    content: [new TextContent("No results found for '$term'")]
                );
            }

            return new CallToolResult(
                content: [new TextContent(
                    "Search results for '$term':\n" .
                        json_encode($results, JSON_PRETTY_PRINT)
                )]
            );
        } catch (\Exception $e) {
            return new CallToolResult(
                content: [new TextContent('Search failed: ' . $e->getMessage())],
                isError: true
            );
        }
    }
);

// Tool to clear query cache
$server->tool(
    'clear-cache',
    'Clear the query result cache',
    [],
    function (array $args) use (&$queryCache) {
        $cacheSize = count($queryCache);
        $queryCache = [];

        return new CallToolResult(
            content: [new TextContent("Cleared $cacheSize cached query results")]
        );
    }
);

/**
 * Get statistics for a table.
 */
function getTableStats(\PDO $pdo, string $tableName): array
{
    $stats = [
        'table_name' => $tableName,
        'row_count' => 0,
        'columns' => [],
        'indexes' => [],
    ];

    // Get row count
    $stats['row_count'] = $pdo->query("SELECT COUNT(*) FROM $tableName")->fetchColumn();

    // Get column information
    $columns = $pdo->query("PRAGMA table_info($tableName)")->fetchAll();
    foreach ($columns as $col) {
        $stats['columns'][] = [
            'name' => $col['name'],
            'type' => $col['type'],
            'nullable' => !$col['notnull'],
            'primary_key' => (bool) $col['pk'],
            'default_value' => $col['dflt_value'],
        ];
    }

    // Get index information
    $indexes = $pdo->query("PRAGMA index_list($tableName)")->fetchAll();
    foreach ($indexes as $idx) {
        $indexInfo = $pdo->query("PRAGMA index_info('{$idx['name']}')")->fetchAll();
        $stats['indexes'][] = [
            'name' => $idx['name'],
            'unique' => (bool) $idx['unique'],
            'columns' => array_column($indexInfo, 'name'),
        ];
    }

    return $stats;
}

// Set up the transport and start the server
async(function () use ($server, $dbPath) {
    try {
        $transport = new StdioServerTransport();

        echo "Starting SQLite Database Server on stdio...\n";
        echo "Database: $dbPath\n";
        echo "Available tables: users, posts, comments\n";
        echo "Use query-select tool for safe SELECT queries\n";
        echo "Use table-stats tool for database statistics\n";
        echo "Use search tool to find data across tables\n\n";

        $server->connect($transport)->await();
    } catch (\Throwable $e) {
        error_log('Server error: ' . $e->getMessage());
        exit(1);
    }
})->await();
