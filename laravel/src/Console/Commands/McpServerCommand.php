<?php

declare(strict_types=1);

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\Command;
use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Server\Transport\StreamableHttpServerTransport;
use MCP\Server\Transport\StreamableHttpServerTransportOptions;
use Amp\Http\Server\HttpServer;
use Amp\Socket\InternetAddress;
use Amp\Socket\BindContext;
use function Amp\delay;
use function Amp\async;

class McpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:server 
        {--transport=stdio : Transport to use (stdio, http)}
        {--port=3000 : Port for HTTP transport}
        {--host=127.0.0.1 : Host for HTTP transport}
        {--session-driver=cache : Session driver (cache, database, redis)}
        {--debug : Enable debug mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the MCP server';

    /**
     * Execute the console command.
     */
    public function handle(McpServer $server): int
    {
        $transport = $this->option('transport');
        $debug = $this->option('debug');

        if ($debug) {
            $this->info('MCP Server Debug Mode Enabled');
            $this->displayServerInfo($server);
        }

        try {
            switch ($transport) {
                case 'stdio':
                    return $this->startStdioServer($server);
                    
                case 'http':
                    return $this->startHttpServer($server);
                    
                default:
                    $this->error("Invalid transport: {$transport}");
                    return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error("Failed to start MCP server: {$e->getMessage()}");
            
            if ($debug) {
                $this->error($e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
    }

    /**
     * Start STDIO server.
     */
    protected function startStdioServer(McpServer $server): int
    {
        $this->info('Starting MCP Server with STDIO transport...');
        
        $transport = new StdioServerTransport();
        
        async(function () use ($server, $transport) {
            try {
                $server->connect($transport);
                $this->info('MCP Server connected via STDIO');
                
                // Keep the server running
                while (true) {
                    delay(1);
                }
            } catch (\Throwable $e) {
                $this->error("STDIO server error: {$e->getMessage()}");
            }
        });

        return self::SUCCESS;
    }

    /**
     * Start HTTP server.
     */
    protected function startHttpServer(McpServer $server): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');
        $sessionDriver = $this->option('session-driver');
        
        $this->info("Starting MCP Server with HTTP transport on {$host}:{$port}...");
        
        try {
            $options = new StreamableHttpServerTransportOptions(
                sessionManagement: [
                    'driver' => $sessionDriver,
                    'lifetime' => config('mcp.transports.http.session.lifetime', 3600),
                ],
                security: [
                    'allowedHosts' => config('mcp.transports.http.security.allowed_hosts', ['localhost', '127.0.0.1']),
                    'maxRequestSize' => config('mcp.transports.http.security.max_request_size', 10 * 1024 * 1024),
                ],
                sse: [
                    'enabled' => config('mcp.transports.http.sse.enabled', true),
                    'keepaliveInterval' => config('mcp.transports.http.sse.keepalive_interval', 30),
                ]
            );

            $transport = new StreamableHttpServerTransport($options);
            
            async(function () use ($server, $transport, $host, $port) {
                try {
                    $server->connect($transport);
                    $this->info('MCP Server connected via HTTP');
                    
                    // Start HTTP server
                    $bindContext = new BindContext();
                    $socket = \Amp\Socket\listen(new InternetAddress($host, $port), $bindContext);
                    
                    $httpServer = new HttpServer($socket, $transport, logger: logger());
                    $httpServer->start();
                    
                    $this->info("MCP HTTP Server listening on http://{$host}:{$port}");
                    $this->displayEndpoints($host, $port);
                    
                    // Keep the server running
                    while (true) {
                        delay(1);
                    }
                } catch (\Throwable $e) {
                    $this->error("HTTP server error: {$e->getMessage()}");
                }
            });

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to start HTTP server: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Display server information.
     */
    protected function displayServerInfo(McpServer $server): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['Server Name', config('mcp.server.name')],
                ['Server Version', config('mcp.server.version')],
                ['Auto Discovery', config('mcp.server.auto_discover.enabled') ? 'Enabled' : 'Disabled'],
                ['Auth Enabled', config('mcp.auth.enabled') ? 'Yes' : 'No'],
                ['Cache Enabled', config('mcp.cache.enabled') ? 'Yes' : 'No'],
                ['Queue Enabled', config('mcp.queue.enabled') ? 'Yes' : 'No'],
            ]
        );

        // Display registered components count (if available)
        $this->info('Use --debug for detailed component information');
    }

    /**
     * Display HTTP endpoints.
     */
    protected function displayEndpoints(string $host, int $port): void
    {
        $baseUrl = "http://{$host}:{$port}";
        $prefix = config('mcp.routes.prefix', 'mcp');
        
        $this->info('Available endpoints:');
        $this->line("  • Main endpoint: {$baseUrl}/{$prefix}");
        $this->line("  • SSE endpoint:  {$baseUrl}/{$prefix}/sse");
        
        if (config('mcp.auth.enabled')) {
            $this->line("  • OAuth authorize: {$baseUrl}/{$prefix}/oauth/authorize");
            $this->line("  • OAuth token:     {$baseUrl}/{$prefix}/oauth/token");
        }

        if (config('mcp.ui.enabled')) {
            $this->line("  • Dashboard:       {$baseUrl}/{$prefix}/dashboard");
        }
    }
}