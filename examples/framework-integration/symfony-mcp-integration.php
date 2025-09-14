<?php

/**
 * Symfony MCP Integration Example
 * 
 * This example demonstrates how to integrate the PHP MCP SDK with Symfony.
 * It shows patterns for:
 * - Using Symfony's dependency injection container
 * - Integrating with Symfony's console component
 * - Using Symfony's validator component
 * - Leveraging Symfony's event dispatcher
 * 
 * This is a standalone example that demonstrates Symfony patterns
 * without requiring a full Symfony installation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use function Amp\async;

// Mock Symfony-style Dependency Injection Container
class MockSymfonyContainer
{
    private array $services = [];
    private array $parameters = [];

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    public function get(string $id): object
    {
        if (!isset($this->services[$id])) {
            throw new Exception("Service '{$id}' not found");
        }
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function setParameter(string $name, $value): void
    {
        $this->parameters[$name] = $value;
    }

    public function getParameter(string $name)
    {
        if (!isset($this->parameters[$name])) {
            throw new Exception("Parameter '{$name}' not found");
        }
        return $this->parameters[$name];
    }
}

// Mock Symfony-style Console Application
class MockConsoleApplication
{
    private array $commands = [];

    public function add(MockCommand $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function find(string $name): ?MockCommand
    {
        return $this->commands[$name] ?? null;
    }

    public function all(): array
    {
        return $this->commands;
    }

    public function run(string $commandName, array $arguments = []): array
    {
        $command = $this->find($commandName);
        if (!$command) {
            return ['error' => "Command '{$commandName}' not found"];
        }

        return $command->execute($arguments);
    }
}

// Mock Symfony-style Command
class MockCommand
{
    private string $name;
    private string $description;
    private $executor;

    public function __construct(string $name, string $description, callable $executor)
    {
        $this->name = $name;
        $this->description = $description;
        $this->executor = $executor;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function execute(array $arguments = []): array
    {
        return ($this->executor)($arguments);
    }
}

// Mock Symfony-style Event Dispatcher
class MockEventDispatcher
{
    private array $listeners = [];

    public function addListener(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(string $eventName, array $data = []): array
    {
        $results = [];
        if (isset($this->listeners[$eventName])) {
            foreach ($this->listeners[$eventName] as $listener) {
                $results[] = $listener($data);
            }
        }
        return $results;
    }
}

// Mock Symfony-style Validator
class MockSymfonyValidator
{
    public function validate(array $data, array $constraints): array
    {
        $violations = [];

        foreach ($constraints as $field => $rules) {
            foreach ($rules as $rule => $ruleValue) {
                switch ($rule) {
                    case 'NotBlank':
                        if (empty($data[$field] ?? '')) {
                            $violations[] = "Field '{$field}' should not be blank";
                        }
                        break;
                    case 'Email':
                        if (isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                            $violations[] = "Field '{$field}' is not a valid email";
                        }
                        break;
                    case 'Length':
                        if (isset($data[$field])) {
                            $length = strlen($data[$field]);
                            if (isset($ruleValue['min']) && $length < $ruleValue['min']) {
                                $violations[] = "Field '{$field}' is too short (minimum {$ruleValue['min']})";
                            }
                            if (isset($ruleValue['max']) && $length > $ruleValue['max']) {
                                $violations[] = "Field '{$field}' is too long (maximum {$ruleValue['max']})";
                            }
                        }
                        break;
                    case 'Choice':
                        if (isset($data[$field]) && !in_array($data[$field], $ruleValue['choices'])) {
                            $violations[] = "Field '{$field}' has invalid choice";
                        }
                        break;
                }
            }
        }

        return $violations;
    }
}

// Set up Symfony-style services
$container = new MockSymfonyContainer();

// Configure parameters
$container->setParameter('app.name', 'Symfony MCP Server');
$container->setParameter('app.version', '1.0.0');
$container->setParameter('app.environment', 'development');

// Register services
$container->set('console', new MockConsoleApplication());
$container->set('event_dispatcher', new MockEventDispatcher());
$container->set('validator', new MockSymfonyValidator());

// Set up console commands
$console = $container->get('console');

$console->add(new MockCommand(
    'user:create',
    'Create a new user',
    function (array $args) {
        return [
            'status' => 'success',
            'message' => "User '{$args['name']}' created successfully",
            'user' => [
                'id' => rand(1000, 9999),
                'name' => $args['name'],
                'email' => $args['email'],
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
    }
));

$console->add(new MockCommand(
    'cache:clear',
    'Clear application cache',
    function (array $args) {
        return [
            'status' => 'success',
            'message' => 'Cache cleared successfully',
            'cleared_items' => rand(50, 200)
        ];
    }
));

$console->add(new MockCommand(
    'debug:container',
    'Display container services',
    function (array $args) use ($container) {
        $services = [];
        foreach (['console', 'event_dispatcher', 'validator'] as $serviceId) {
            if ($container->has($serviceId)) {
                $services[] = $serviceId;
            }
        }

        return [
            'status' => 'success',
            'services' => $services,
            'count' => count($services)
        ];
    }
));

// Set up event listeners
$eventDispatcher = $container->get('event_dispatcher');

$eventDispatcher->addListener('user.created', function (array $data) {
    return [
        'listener' => 'email_notification',
        'message' => "Welcome email sent to {$data['email']}"
    ];
});

$eventDispatcher->addListener('user.created', function (array $data) {
    return [
        'listener' => 'audit_log',
        'message' => "User creation logged: {$data['name']}"
    ];
});

// Create MCP Server with Symfony integration
$server = new McpServer(
    new Implementation(
        'symfony-mcp-server',
        '1.0.0',
        'Symfony MCP Integration Example'
    )
);

// Tool: Run Console Command
$server->tool(
    'console_command',
    'Execute a Symfony console command',
    [
        'type' => 'object',
        'properties' => [
            'command' => [
                'type' => 'string',
                'description' => 'Console command to execute'
            ],
            'arguments' => [
                'type' => 'object',
                'description' => 'Command arguments',
                'additionalProperties' => true
            ]
        ],
        'required' => ['command']
    ],
    function (array $args) use ($container): array {
        $console = $container->get('console');
        $commandName = $args['command'];
        $arguments = $args['arguments'] ?? [];

        $result = $console->run($commandName, $arguments);

        if (isset($result['error'])) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "❌ Command failed: {$result['error']}"
                    ]
                ]
            ];
        }

        $output = "✅ Command '{$commandName}' executed successfully\n\n";

        if (isset($result['message'])) {
            $output .= "Message: {$result['message']}\n";
        }

        if (isset($result['user'])) {
            $user = $result['user'];
            $output .= "Created User:\n";
            $output .= "  ID: {$user['id']}\n";
            $output .= "  Name: {$user['name']}\n";
            $output .= "  Email: {$user['email']}\n";
            $output .= "  Created: {$user['created_at']}\n";
        }

        if (isset($result['services'])) {
            $output .= "Services ({$result['count']}):\n";
            foreach ($result['services'] as $service) {
                $output .= "  • {$service}\n";
            }
        }

        if (isset($result['cleared_items'])) {
            $output .= "Cleared {$result['cleared_items']} cache items\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $output
                ]
            ]
        ];
    }
);

// Tool: List Available Commands
$server->tool(
    'list_commands',
    'List all available console commands',
    [
        'type' => 'object',
        'properties' => []
    ],
    function (array $args) use ($container): array {
        $console = $container->get('console');
        $commands = $console->all();

        $commandList = "📋 Available Console Commands:\n\n";
        foreach ($commands as $name => $command) {
            $commandList .= "• **{$name}**: {$command->getDescription()}\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $commandList
                ]
            ]
        ];
    }
);

// Tool: Validate Data
$server->tool(
    'validate_data',
    'Validate data using Symfony validator',
    [
        'type' => 'object',
        'properties' => [
            'data' => [
                'type' => 'object',
                'description' => 'Data to validate',
                'additionalProperties' => true
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Validation type',
                'enum' => ['user', 'email', 'custom']
            ]
        ],
        'required' => ['data', 'type']
    ],
    function (array $args) use ($container): array {
        $validator = $container->get('validator');
        $data = $args['data'];
        $type = $args['type'];

        $constraints = match ($type) {
            'user' => [
                'name' => ['NotBlank' => true, 'Length' => ['min' => 2, 'max' => 50]],
                'email' => ['NotBlank' => true, 'Email' => true],
                'role' => ['Choice' => ['choices' => ['admin', 'user', 'moderator']]]
            ],
            'email' => [
                'email' => ['NotBlank' => true, 'Email' => true]
            ],
            'custom' => [
                'value' => ['NotBlank' => true]
            ]
        };

        $violations = $validator->validate($data, $constraints);

        if (empty($violations)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "✅ Validation passed!\n\nData is valid according to {$type} constraints."
                    ]
                ]
            ];
        }

        $errorText = "❌ Validation failed:\n\n";
        foreach ($violations as $violation) {
            $errorText .= "• {$violation}\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $errorText
                ]
            ]
        ];
    }
);

// Tool: Dispatch Event
$server->tool(
    'dispatch_event',
    'Dispatch an event through the event system',
    [
        'type' => 'object',
        'properties' => [
            'event_name' => [
                'type' => 'string',
                'description' => 'Name of the event to dispatch'
            ],
            'data' => [
                'type' => 'object',
                'description' => 'Event data',
                'additionalProperties' => true
            ]
        ],
        'required' => ['event_name']
    ],
    function (array $args) use ($container): array {
        $eventDispatcher = $container->get('event_dispatcher');
        $eventName = $args['event_name'];
        $data = $args['data'] ?? [];

        $results = $eventDispatcher->dispatch($eventName, $data);

        $output = "🎯 Event '{$eventName}' dispatched\n\n";

        if (empty($results)) {
            $output .= "No listeners responded to this event.";
        } else {
            $output .= "Listener responses:\n";
            foreach ($results as $result) {
                if (isset($result['listener']) && isset($result['message'])) {
                    $output .= "• {$result['listener']}: {$result['message']}\n";
                }
            }
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $output
                ]
            ]
        ];
    }
);

// Resource: Symfony Application Info
$server->resource(
    'Symfony Application Info',
    'symfony://app-info',
    [
        'title' => 'Symfony Application Information',
        'description' => 'Information about the Symfony MCP integration',
        'mimeType' => 'application/json'
    ],
    function () use ($container): string {
        return json_encode([
            'framework' => 'Symfony',
            'mcp_integration' => 'PHP MCP SDK',
            'app_name' => $container->getParameter('app.name'),
            'version' => $container->getParameter('app.version'),
            'environment' => $container->getParameter('app.environment'),
            'features' => [
                'Dependency Injection Container',
                'Console Commands',
                'Event Dispatcher',
                'Validator Component',
                'Service Registration'
            ],
            'registered_services' => ['console', 'event_dispatcher', 'validator'],
            'available_commands' => array_keys($container->get('console')->all()),
            'event_listeners' => ['user.created']
        ], JSON_PRETTY_PRINT);
    }
);

// Resource: Container Services
$server->resource(
    'Container Services',
    'symfony://services',
    [
        'title' => 'Dependency Injection Container Services',
        'description' => 'List of registered services in the DI container',
        'mimeType' => 'text/plain'
    ],
    function () use ($container): string {
        $output = "Symfony DI Container Services\n";
        $output .= "============================\n\n";

        $services = ['console', 'event_dispatcher', 'validator'];

        foreach ($services as $serviceId) {
            if ($container->has($serviceId)) {
                $service = $container->get($serviceId);
                $className = get_class($service);
                $output .= "Service: {$serviceId}\n";
                $output .= "Class: {$className}\n";

                if ($serviceId === 'console') {
                    $commands = $service->all();
                    $output .= "Commands: " . implode(', ', array_keys($commands)) . "\n";
                }

                $output .= "\n";
            }
        }

        $output .= "Parameters:\n";
        $output .= "- app.name: " . $container->getParameter('app.name') . "\n";
        $output .= "- app.version: " . $container->getParameter('app.version') . "\n";
        $output .= "- app.environment: " . $container->getParameter('app.environment') . "\n";

        return $output;
    }
);

// Prompt: Symfony Development Help
$server->prompt(
    'symfony_help',
    'Get help with Symfony MCP development',
    function (): array {
        return [
            'description' => 'Symfony MCP Development Assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I integrate MCP with Symfony?'
                        ]
                    ]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Integrating MCP with Symfony follows these patterns:\n\n" .
                                "**1. Dependency Injection:**\n" .
                                "• Register MCP services in services.yaml\n" .
                                "• Use autowiring for automatic dependency resolution\n" .
                                "• Create service aliases for easy access\n\n" .
                                "**2. Console Integration:**\n" .
                                "• Create MCP tools that execute console commands\n" .
                                "• Use the Console component for CLI operations\n" .
                                "• Leverage existing Symfony commands\n\n" .
                                "**3. Event System:**\n" .
                                "• Dispatch events from MCP tools\n" .
                                "• Create event listeners for MCP operations\n" .
                                "• Use Symfony's EventDispatcher component\n\n" .
                                "**4. Validation:**\n" .
                                "• Use Symfony's Validator component in MCP tools\n" .
                                "• Define validation constraints\n" .
                                "• Return structured validation errors\n\n" .
                                "**Available Tools:**\n" .
                                "• console_command - Execute Symfony console commands\n" .
                                "• list_commands - List available console commands\n" .
                                "• validate_data - Validate data with Symfony validator\n" .
                                "• dispatch_event - Dispatch events through event system\n\n" .
                                "Try: 'Use console_command to run debug:container'"
                        ]
                    ]
                ]
            ]
        ];
    }
);

// Start the server
async(function () use ($server, $container) {
    echo "🚀 Symfony MCP Integration Server starting...\n";
    echo "🏗️  Framework: Symfony " . $container->getParameter('app.version') . "\n";
    echo "🛠️  Available tools: console_command, list_commands, validate_data, dispatch_event\n";
    echo "📚 Resources: app-info, services\n";
    echo "⚡ Services: " . implode(', ', ['console', 'event_dispatcher', 'validator']) . "\n" . PHP_EOL;

    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
