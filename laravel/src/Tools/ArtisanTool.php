<?php

declare(strict_types=1);

namespace MCP\Laravel\Tools;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class ArtisanTool extends BaseTool
{
    private const SAFE_COMMANDS = [
        'list',
        'help',
        'about',
        'env',
        'config:show',
        'route:list',
        'event:list',
        'schedule:list',
        'queue:monitor',
        'cache:clear',
        'config:clear',
        'route:clear',
        'view:clear',
        'optimize:clear',
        'model:show',
        'db:show',
        'db:table',
    ];

    private const DANGEROUS_COMMANDS = [
        'migrate',
        'migrate:rollback',
        'migrate:reset',
        'migrate:fresh',
        'db:wipe',
        'queue:restart',
        'down',
        'up',
        'key:generate',
        'storage:link',
        'vendor:publish',
    ];

    public function name(): string
    {
        return 'laravel_artisan';
    }

    public function description(): string
    {
        return 'Execute Laravel Artisan commands safely - supports read-only and maintenance commands';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => [
                    'type' => 'string',
                    'description' => 'Artisan command to execute (without "php artisan" prefix)',
                ],
                'arguments' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Command arguments',
                    'default' => [],
                ],
                'options' => [
                    'type' => 'object',
                    'description' => 'Command options as key-value pairs',
                    'default' => [],
                ],
                'allow_dangerous' => [
                    'type' => 'boolean',
                    'description' => 'Allow potentially dangerous commands (requires special permission)',
                    'default' => false,
                ],
            ],
            'required' => ['command'],
        ];
    }

    public function handle(array $params): array
    {
        $command = $params['command'];
        $arguments = $params['arguments'] ?? [];
        $options = $params['options'] ?? [];
        $allowDangerous = $params['allow_dangerous'] ?? false;

        // Security check
        $this->validateCommandSafety($command, $allowDangerous);

        try {
            $output = new BufferedOutput();
            
            // Build command with arguments and options
            $commandLine = $this->buildCommandLine($command, $arguments, $options);
            
            $startTime = microtime(true);
            $exitCode = Artisan::call($command, array_merge($arguments, $options), $output);
            $executionTime = microtime(true) - $startTime;

            return [
                'command' => $commandLine,
                'exit_code' => $exitCode,
                'output' => $output->fetch(),
                'success' => $exitCode === 0,
                'execution_time' => round($executionTime * 1000, 2), // ms
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Artisan command failed: {$e->getMessage()}");
        }
    }

    private function validateCommandSafety(string $command, bool $allowDangerous): void
    {
        // Check if it's a known dangerous command
        foreach (self::DANGEROUS_COMMANDS as $dangerousCommand) {
            if (str_starts_with($command, $dangerousCommand)) {
                if (!$allowDangerous) {
                    throw new \SecurityException("Dangerous command '{$dangerousCommand}' not allowed. Set allow_dangerous=true to enable.");
                }
                break;
            }
        }

        // Additional security checks
        if (str_contains($command, '&&') || str_contains($command, '||') || str_contains($command, ';')) {
            throw new \SecurityException("Command chaining is not allowed");
        }

        if (str_contains($command, '`') || str_contains($command, '$')) {
            throw new \SecurityException("Shell execution is not allowed");
        }
    }

    private function buildCommandLine(string $command, array $arguments, array $options): string
    {
        $commandLine = "php artisan {$command}";

        foreach ($arguments as $arg) {
            $commandLine .= " " . escapeshellarg($arg);
        }

        foreach ($options as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $commandLine .= " --{$key}";
                }
            } else {
                $commandLine .= " --{$key}=" . escapeshellarg($value);
            }
        }

        return $commandLine;
    }

    public function getSafeCommands(): array
    {
        return self::SAFE_COMMANDS;
    }

    public function getDangerousCommands(): array
    {
        return self::DANGEROUS_COMMANDS;
    }

    public function cacheable(): bool
    {
        return false; // Artisan commands shouldn't be cached
    }

    public function requiresAuth(): bool
    {
        return true;
    }

    public function requiredScopes(): array
    {
        return ['mcp:tools', 'laravel:artisan'];
    }

    public function maxExecutionTime(): int
    {
        return 60; // Artisan commands might take longer
    }
}