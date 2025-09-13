<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

/**
 * Parameters for starting a stdio server process
 */
class StdioServerParameters
{
    /**
     * @param string $command The executable to run to start the server
     * @param array<string>|null $args Command line arguments to pass to the executable
     * @param array<string, string>|null $env Environment variables (if not specified, default environment will be used)
     * @param string|null $cwd The working directory to use when spawning the process
     * @param bool $inheritStderr Whether to inherit stderr (default: true)
     */
    public function __construct(
        public readonly string $command,
        public readonly ?array $args = null,
        public readonly ?array $env = null,
        public readonly ?string $cwd = null,
        public readonly bool $inheritStderr = true
    ) {
    }
}
