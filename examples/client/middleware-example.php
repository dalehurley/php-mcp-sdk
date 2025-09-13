<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Amp\Future;
use MCP\Client\Client;
use MCP\Client\ClientBuilder;
use MCP\Client\ClientOptions;
use MCP\Client\Middleware\LoggingMiddleware;
use MCP\Client\Middleware\MiddlewareInterface;
use MCP\Client\Middleware\RetryMiddleware;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Validation\CompiledValidator;
use MCP\Client\Validation\JsonSchemaCompiler;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Implementation;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;

use function Amp\async;

/**
 * Custom middleware example for request/response modification.
 */
class CustomHeaderMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $headerName,
        private readonly string $headerValue
    ) {}

    public function process(RequestInterface $request, callable $next): Future
    {
        return async(function () use ($request, $next) {
            // Add custom header to request
            $modifiedRequest = $request->withHeader($this->headerName, $this->headerValue);
            
            echo "Added custom header: {$this->headerName}: {$this->headerValue}\n";
            
            // Process request through the chain
            $response = $next($modifiedRequest)->await();
            
            // Could modify response here if needed
            return $response;
        });
    }
}

/**
 * Request timing middleware.
 */
class TimingMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): Future
    {
        return async(function () use ($request, $next) {
            $startTime = microtime(true);
            $method = $request->getMethod();
            $uri = (string)$request->getUri();
            
            echo "Starting request: {$method} {$uri}\n";
            
            try {
                $response = $next($request)->await();
                $duration = (microtime(true) - $startTime) * 1000;
                
                $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 'N/A';
                echo "Request completed: {$method} {$uri} - {$status} (" . number_format($duration, 2) . "ms)\n";
                
                return $response;
            } catch (\Throwable $e) {
                $duration = (microtime(true) - $startTime) * 1000;
                echo "Request failed: {$method} {$uri} - {$e->getMessage()} (" . number_format($duration, 2) . "ms)\n";
                throw $e;
            }
        });
    }
}

/**
 * Demonstrate middleware usage with MCP client.
 */
function demonstrateMiddleware(): void
{
    echo "Middleware Demonstration\n";
    echo "========================\n\n";

    // Create logger
    $logger = new Logger('middleware-demo');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

    // Create client info
    $clientInfo = new Implementation(
        name: 'Middleware Demo Client',
        version: '1.0.0'
    );

    $capabilities = new ClientCapabilities();
    $options = new ClientOptions($capabilities);

    // Method 1: Using the fluent builder
    echo "Creating client with fluent builder...\n";
    $client = ClientBuilder::create($clientInfo, $options)
        ->withMiddleware(new TimingMiddleware())
        ->withMiddleware(new CustomHeaderMiddleware('X-Client-Version', '1.0.0'))
        ->withRetry(3, 1.0)
        ->withLogging($logger)
        ->build();

    echo "Client created with {$client->getMiddlewareCount()} middleware\n\n";

    // Method 2: Adding middleware directly
    echo "Creating client with direct middleware addition...\n";
    $client2 = new Client($clientInfo, $options);
    $client2->addMiddleware(new TimingMiddleware());
    $client2->addMiddleware(new CustomHeaderMiddleware('X-Custom-Header', 'custom-value'));
    $client2->withRetry(2, 0.5);
    $client2->withLogging($logger, 'debug');

    echo "Client 2 created with {$client2->getMiddlewareCount()} middleware\n\n";

    // Demonstrate middleware clearing
    echo "Clearing middleware from client 2...\n";
    $client2->clearMiddleware();
    echo "Client 2 now has {$client2->getMiddlewareCount()} middleware\n\n";
}

/**
 * Demonstrate compiled validators for performance.
 */
function demonstrateCompiledValidators(): void
{
    echo "Compiled Validators Demonstration\n";
    echo "=================================\n\n";

    // Create a JSON schema compiler
    $compiler = new JsonSchemaCompiler();

    // Example schema for a tool definition
    $toolSchema = [
        'type' => 'object',
        'required' => ['name', 'description'],
        'properties' => [
            'name' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 100
            ],
            'description' => [
                'type' => 'string',
                'minLength' => 1
            ],
            'parameters' => [
                'type' => 'object'
            ]
        ]
    ];

    echo "Compiling tool schema...\n";
    $validator = $compiler->compile($toolSchema);
    
    echo "Schema compiled with hash: {$validator->getSchemaHash()}\n";
    echo "Validator is valid: " . ($validator->isValid() ? 'Yes' : 'No') . "\n\n";

    // Test valid data
    $validTool = [
        'name' => 'calculate',
        'description' => 'Performs mathematical calculations',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'expression' => ['type' => 'string']
            ]
        ]
    ];

    echo "Validating valid tool data...\n";
    $isValid = $validator->validate($validTool);
    echo "Valid: " . ($isValid ? 'Yes' : 'No') . "\n";
    if (!$isValid) {
        echo "Errors: " . implode(', ', $validator->getErrors()) . "\n";
    }
    echo "\n";

    // Test invalid data
    $invalidTool = [
        'name' => '', // Empty name (violates minLength)
        'description' => 'A tool with invalid name'
    ];

    echo "Validating invalid tool data...\n";
    $isValid = $validator->validate($invalidTool);
    echo "Valid: " . ($isValid ? 'Yes' : 'No') . "\n";
    if (!$isValid) {
        echo "Errors:\n";
        foreach ($validator->getErrors() as $error) {
            echo "  - {$error}\n";
        }
    }
    echo "\n";

    // Compiler statistics
    echo "Compiler statistics:\n";
    $stats = $compiler->getStats();
    foreach ($stats as $key => $value) {
        echo "  {$key}: " . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . "\n";
    }
    echo "\n";
}

/**
 * Demonstrate advanced client features.
 */
function demonstrateAdvancedFeatures(): void
{
    echo "Advanced Features Demonstration\n";
    echo "===============================\n\n";

    $clientInfo = new Implementation('Advanced Client', '1.0.0');
    $client = new Client($clientInfo);

    // Demonstrate capability registration
    echo "Registering additional capabilities...\n";
    $additionalCapabilities = new ClientCapabilities(
        sampling: ['temperature' => 0.7],
        elicitation: ['max_tokens' => 1000]
    );

    $client->registerCapabilities($additionalCapabilities);
    echo "Additional capabilities registered\n\n";

    // Demonstrate server request handlers
    echo "Setting up server request handlers...\n";
    
    $client->setSamplingHandler(function ($request) {
        echo "Sampling request received: " . json_encode($request->getParams()) . "\n";
        return ['response' => 'Sampling handled'];
    });

    $client->setElicitationHandler(function ($request) {
        echo "Elicitation request received\n";
        return ['response' => 'Elicitation handled'];
    });

    echo "Request handlers configured\n\n";

    // Show client configuration
    echo "Client configuration:\n";
    echo "- Has middleware: " . ($client->hasMiddleware() ? 'Yes' : 'No') . "\n";
    echo "- Middleware count: {$client->getMiddlewareCount()}\n";
    echo "- Server capabilities: " . ($client->getServerCapabilities() ? 'Available' : 'Not available') . "\n";
    echo "- Server version: " . ($client->getServerVersion()?->getName() ?? 'Unknown') . "\n";
    echo "- Instructions: " . ($client->getInstructions() ?? 'None') . "\n";
}

/**
 * Performance comparison between runtime and compiled validation.
 */
function performanceComparison(): void
{
    echo "Performance Comparison\n";
    echo "======================\n\n";

    $schema = [
        'type' => 'object',
        'required' => ['id', 'name', 'email'],
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 50],
            'email' => ['type' => 'string', 'pattern' => '^[^@]+@[^@]+\.[^@]+$'],
            'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 150],
            'active' => ['type' => 'boolean']
        ]
    ];

    $testData = [
        'id' => 123,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
        'active' => true
    ];

    $iterations = 1000;

    // Compiled validator performance
    $compiler = new JsonSchemaCompiler();
    $compiledValidator = $compiler->compile($schema);

    echo "Testing compiled validator performance...\n";
    $startTime = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $compiledValidator->validate($testData);
    }
    
    $compiledTime = microtime(true) - $startTime;
    echo "Compiled validator: {$iterations} validations in " . number_format($compiledTime, 4) . "s\n";
    echo "Average: " . ($compiledTime / $iterations * 1000) . "ms per validation\n\n";

    // Note: In a real implementation, you would compare with a runtime validator
    echo "Note: Runtime validator comparison would require a JSON schema validation library\n";
    echo "like 'justinrainbow/json-schema' or 'opis/json-schema' for accurate benchmarking.\n\n";

    echo "Performance benefits of compiled validators:\n";
    echo "- Pre-compiled validation rules\n";
    echo "- Optimized execution paths\n";
    echo "- Reduced parsing overhead\n";
    echo "- Better memory usage patterns\n";
}

// Run the examples
if (php_sapi_name() === 'cli') {
    echo "MCP Client Middleware and Advanced Features Examples\n";
    echo "=====================================================\n\n";

    try {
        // Demonstrate middleware
        demonstrateMiddleware();
        
        // Demonstrate compiled validators
        demonstrateCompiledValidators();
        
        // Demonstrate advanced features
        demonstrateAdvancedFeatures();
        
        // Performance comparison
        performanceComparison();
        
    } catch (\Throwable $e) {
        echo "Example failed: {$e->getMessage()}\n";
        echo "Stack trace:\n{$e->getTraceAsString()}\n";
        exit(1);
    }
    
    echo "All examples completed successfully!\n";
} else {
    echo "This example must be run from the command line.\n";
}
