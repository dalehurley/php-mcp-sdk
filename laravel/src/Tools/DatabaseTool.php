<?php

declare(strict_types=1);

namespace MCP\Laravel\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseTool extends BaseTool
{
    private const SAFE_OPERATIONS = ['select', 'show', 'describe', 'explain'];
    private const UNSAFE_OPERATIONS = ['drop', 'truncate', 'delete', 'update', 'insert'];

    public function name(): string
    {
        return 'laravel_database';
    }

    public function description(): string
    {
        return 'Query Laravel database safely - supports SELECT operations and schema inspection';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['query', 'tables', 'columns', 'indexes', 'schema', 'connection'],
                    'description' => 'Database operation to perform',
                ],
                'sql' => [
                    'type' => 'string',
                    'description' => 'SQL query to execute (for query operation)',
                ],
                'table' => [
                    'type' => 'string',
                    'description' => 'Table name (for columns, indexes operations)',
                ],
                'connection' => [
                    'type' => 'string',
                    'description' => 'Database connection name (optional)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of rows to return',
                    'minimum' => 1,
                    'maximum' => 1000,
                    'default' => 100,
                ],
                'allow_write' => [
                    'type' => 'boolean',
                    'description' => 'Allow write operations (dangerous, requires special permission)',
                    'default' => false,
                ],
            ],
            'required' => ['operation'],
        ];
    }

    public function handle(array $params): array
    {
        $operation = $params['operation'];
        $connection = $params['connection'] ?? null;
        $db = $connection ? DB::connection($connection) : DB::connection();

        return match ($operation) {
            'query' => $this->handleQuery($db, $params),
            'tables' => $this->handleTables($db),
            'columns' => $this->handleColumns($params),
            'indexes' => $this->handleIndexes($params),
            'schema' => $this->handleSchema($params),
            'connection' => $this->handleConnectionInfo($db),
            default => throw new \InvalidArgumentException("Unsupported operation: {$operation}"),
        };
    }

    private function handleQuery($db, array $params): array
    {
        $sql = $params['sql'] ?? throw new \InvalidArgumentException('SQL query is required');
        $limit = $params['limit'] ?? 100;
        $allowWrite = $params['allow_write'] ?? false;

        // Security check
        $this->validateSqlSafety($sql, $allowWrite);

        try {
            $startTime = microtime(true);
            
            // Add limit to SELECT queries if not present
            if ($this->isSelectQuery($sql) && !$this->hasLimitClause($sql)) {
                $sql .= " LIMIT {$limit}";
            }

            $results = $db->select($sql);
            $executionTime = microtime(true) - $startTime;

            return [
                'operation' => 'query',
                'sql' => $sql,
                'results' => $results,
                'row_count' => count($results),
                'execution_time' => round($executionTime * 1000, 2), // ms
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Database query failed: {$e->getMessage()}");
        }
    }

    private function handleTables($db): array
    {
        try {
            $tables = Schema::connection($db->getName())->getAllTables();
            
            return [
                'operation' => 'tables',
                'tables' => array_map(function ($table) {
                    return array_values((array) $table)[0]; // Extract table name
                }, $tables),
                'count' => count($tables),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to list tables: {$e->getMessage()}");
        }
    }

    private function handleColumns(array $params): array
    {
        $table = $params['table'] ?? throw new \InvalidArgumentException('Table name is required');
        $connection = $params['connection'] ?? null;

        try {
            $columns = Schema::connection($connection)->getColumnListing($table);
            $columnDetails = [];

            foreach ($columns as $column) {
                $columnDetails[$column] = [
                    'name' => $column,
                    'type' => Schema::connection($connection)->getColumnType($table, $column),
                ];
            }

            return [
                'operation' => 'columns',
                'table' => $table,
                'columns' => $columnDetails,
                'count' => count($columns),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to get columns for table {$table}: {$e->getMessage()}");
        }
    }

    private function handleIndexes(array $params): array
    {
        $table = $params['table'] ?? throw new \InvalidArgumentException('Table name is required');
        $connection = $params['connection'] ?? null;

        try {
            $indexes = Schema::connection($connection)->getIndexes($table);

            return [
                'operation' => 'indexes',
                'table' => $table,
                'indexes' => $indexes,
                'count' => count($indexes),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to get indexes for table {$table}: {$e->getMessage()}");
        }
    }

    private function handleSchema(array $params): array
    {
        $table = $params['table'] ?? throw new \InvalidArgumentException('Table name is required');
        $connection = $params['connection'] ?? null;

        try {
            $columns = $this->handleColumns($params);
            $indexes = $this->handleIndexes($params);

            return [
                'operation' => 'schema',
                'table' => $table,
                'schema' => [
                    'columns' => $columns['columns'],
                    'indexes' => $indexes['indexes'],
                ],
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to get schema for table {$table}: {$e->getMessage()}");
        }
    }

    private function handleConnectionInfo($db): array
    {
        try {
            $config = config("database.connections.{$db->getName()}");

            return [
                'operation' => 'connection',
                'connection' => $db->getName(),
                'driver' => $config['driver'] ?? 'unknown',
                'database' => $config['database'] ?? null,
                'host' => $config['host'] ?? null,
                'port' => $config['port'] ?? null,
                'connected' => true,
            ];
        } catch (\Exception $e) {
            return [
                'operation' => 'connection',
                'connection' => $db->getName(),
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function validateSqlSafety(string $sql, bool $allowWrite): void
    {
        $sql = strtolower(trim($sql));
        
        if (!$allowWrite) {
            foreach (self::UNSAFE_OPERATIONS as $operation) {
                if (str_starts_with($sql, $operation)) {
                    throw new \SecurityException("Unsafe operation '{$operation}' not allowed. Set allow_write=true to enable.");
                }
            }
        }

        // Additional security checks
        if (str_contains($sql, 'information_schema') && !str_starts_with($sql, 'select')) {
            throw new \SecurityException("Non-SELECT operations on information_schema are not allowed");
        }
    }

    private function isSelectQuery(string $sql): bool
    {
        return str_starts_with(strtolower(trim($sql)), 'select');
    }

    private function hasLimitClause(string $sql): bool
    {
        return str_contains(strtolower($sql), 'limit');
    }

    public function cacheable(): bool
    {
        return false; // Database operations shouldn't be cached by default
    }

    public function requiresAuth(): bool
    {
        return true;
    }

    public function requiredScopes(): array
    {
        return ['mcp:tools', 'laravel:database'];
    }

    public function maxExecutionTime(): int
    {
        return 30; // Shorter timeout for database operations
    }
}