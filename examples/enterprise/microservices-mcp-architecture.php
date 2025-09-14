#!/usr/bin/env php
<?php

/**
 * Microservices MCP Architecture Example
 * 
 * This example demonstrates how to build a microservices architecture using MCP.
 * It includes:
 * - Service discovery and registration
 * - Inter-service communication via MCP
 * - Load balancing and failover
 * - Circuit breaker pattern
 * - Distributed logging and tracing
 * - API Gateway pattern
 * 
 * This server acts as both a service registry and an API gateway,
 * orchestrating multiple MCP microservices.
 * 
 * Usage:
 *   php microservices-mcp-architecture.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;
use function Amp\async;

// Service Registry for managing microservices
class ServiceRegistry
{
    private array $services = [];
    private array $healthChecks = [];

    public function register(string $serviceName, array $config): void
    {
        $this->services[$serviceName] = [
            'name' => $serviceName,
            'config' => $config,
            'status' => 'registered',
            'registered_at' => time(),
            'last_health_check' => null,
            'health_status' => 'unknown',
            'request_count' => 0,
            'error_count' => 0
        ];

        echo "🔧 Service registered: {$serviceName}\n";
    }

    public function getService(string $serviceName): ?array
    {
        return $this->services[$serviceName] ?? null;
    }

    public function getAllServices(): array
    {
        return $this->services;
    }

    public function updateHealth(string $serviceName, bool $healthy): void
    {
        if (isset($this->services[$serviceName])) {
            $this->services[$serviceName]['health_status'] = $healthy ? 'healthy' : 'unhealthy';
            $this->services[$serviceName]['last_health_check'] = time();
        }
    }

    public function incrementRequests(string $serviceName): void
    {
        if (isset($this->services[$serviceName])) {
            $this->services[$serviceName]['request_count']++;
        }
    }

    public function incrementErrors(string $serviceName): void
    {
        if (isset($this->services[$serviceName])) {
            $this->services[$serviceName]['error_count']++;
        }
    }

    public function getHealthyServices(): array
    {
        return array_filter($this->services, function ($service) {
            return $service['health_status'] === 'healthy';
        });
    }
}

// Circuit Breaker for handling service failures
class CircuitBreaker
{
    private array $circuits = [];
    private int $failureThreshold;
    private int $timeout;

    public function __construct(int $failureThreshold = 5, int $timeout = 60)
    {
        $this->failureThreshold = $failureThreshold;
        $this->timeout = $timeout;
    }

    public function call(string $serviceName, callable $operation)
    {
        $circuit = &$this->circuits[$serviceName];

        if (!isset($circuit)) {
            $circuit = [
                'state' => 'closed',
                'failures' => 0,
                'last_failure' => null,
                'last_success' => time()
            ];
        }

        // Check if circuit is open and should remain open
        if ($circuit['state'] === 'open') {
            if (time() - $circuit['last_failure'] < $this->timeout) {
                throw new Exception("Circuit breaker is OPEN for service: {$serviceName}");
            }
            // Try half-open state
            $circuit['state'] = 'half-open';
        }

        try {
            $result = $operation();

            // Success - close circuit
            $circuit['state'] = 'closed';
            $circuit['failures'] = 0;
            $circuit['last_success'] = time();

            return $result;
        } catch (Exception $e) {
            $circuit['failures']++;
            $circuit['last_failure'] = time();

            // Open circuit if threshold reached
            if ($circuit['failures'] >= $this->failureThreshold) {
                $circuit['state'] = 'open';
                echo "🚨 Circuit breaker OPENED for service: {$serviceName}\n";
            }

            throw $e;
        }
    }

    public function getCircuitStatus(string $serviceName): array
    {
        return $this->circuits[$serviceName] ?? [
            'state' => 'closed',
            'failures' => 0,
            'last_failure' => null,
            'last_success' => null
        ];
    }

    public function getAllCircuits(): array
    {
        return $this->circuits;
    }
}

// Load Balancer for distributing requests
class LoadBalancer
{
    private array $strategies = ['round_robin', 'least_connections', 'random'];
    private array $counters = [];

    public function selectService(array $services, string $strategy = 'round_robin'): ?array
    {
        if (empty($services)) {
            return null;
        }

        switch ($strategy) {
            case 'round_robin':
                return $this->roundRobin($services);
            case 'least_connections':
                return $this->leastConnections($services);
            case 'random':
                return $services[array_rand($services)];
            default:
                return array_values($services)[0];
        }
    }

    private function roundRobin(array $services): array
    {
        $serviceNames = array_keys($services);
        $key = 'round_robin_' . md5(implode(',', $serviceNames));

        if (!isset($this->counters[$key])) {
            $this->counters[$key] = 0;
        }

        $index = $this->counters[$key] % count($services);
        $this->counters[$key]++;

        return array_values($services)[$index];
    }

    private function leastConnections(array $services): array
    {
        $minConnections = PHP_INT_MAX;
        $selectedService = null;

        foreach ($services as $service) {
            $connections = $service['request_count'] - $service['error_count'];
            if ($connections < $minConnections) {
                $minConnections = $connections;
                $selectedService = $service;
            }
        }

        return $selectedService ?? array_values($services)[0];
    }
}

// Initialize components
$serviceRegistry = new ServiceRegistry();
$circuitBreaker = new CircuitBreaker();
$loadBalancer = new LoadBalancer();

// Register mock microservices
$serviceRegistry->register('user-service', [
    'description' => 'User management service',
    'endpoints' => ['create_user', 'get_user', 'update_user', 'delete_user'],
    'version' => '1.2.0',
    'instances' => ['user-service-1', 'user-service-2']
]);

$serviceRegistry->register('order-service', [
    'description' => 'Order processing service',
    'endpoints' => ['create_order', 'get_order', 'process_payment', 'update_status'],
    'version' => '2.1.0',
    'instances' => ['order-service-1', 'order-service-2', 'order-service-3']
]);

$serviceRegistry->register('inventory-service', [
    'description' => 'Inventory management service',
    'endpoints' => ['check_stock', 'reserve_items', 'release_items', 'update_inventory'],
    'version' => '1.0.5',
    'instances' => ['inventory-service-1']
]);

$serviceRegistry->register('notification-service', [
    'description' => 'Notification and messaging service',
    'endpoints' => ['send_email', 'send_sms', 'push_notification', 'get_templates'],
    'version' => '3.0.1',
    'instances' => ['notification-service-1', 'notification-service-2']
]);

// Simulate health status
$serviceRegistry->updateHealth('user-service', true);
$serviceRegistry->updateHealth('order-service', true);
$serviceRegistry->updateHealth('inventory-service', false); // Simulate unhealthy service
$serviceRegistry->updateHealth('notification-service', true);

// Create API Gateway MCP Server
$server = new McpServer(
    new Implementation(
        'microservices-api-gateway',
        '1.0.0',
        'MCP-based Microservices API Gateway with service discovery'
    )
);

// Tool: Service Discovery
$server->tool(
    'discover_services',
    'Discover available microservices',
    [
        'type' => 'object',
        'properties' => [
            'filter_healthy' => [
                'type' => 'boolean',
                'description' => 'Only return healthy services',
                'default' => true
            ]
        ]
    ],
    function (array $args) use ($serviceRegistry): array {
        $filterHealthy = $args['filter_healthy'] ?? true;

        $services = $filterHealthy
            ? $serviceRegistry->getHealthyServices()
            : $serviceRegistry->getAllServices();

        $serviceList = "🔍 Service Discovery Results\n\n";

        if (empty($services)) {
            $serviceList .= "No services found.\n";
        } else {
            foreach ($services as $service) {
                $healthIcon = $service['health_status'] === 'healthy' ? '✅' : '❌';
                $serviceList .= "{$healthIcon} **{$service['name']}** (v{$service['config']['version']})\n";
                $serviceList .= "   Description: {$service['config']['description']}\n";
                $serviceList .= "   Instances: " . count($service['config']['instances']) . "\n";
                $serviceList .= "   Requests: {$service['request_count']}, Errors: {$service['error_count']}\n";
                $serviceList .= "   Health: {$service['health_status']}\n\n";
            }
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $serviceList
                ]
            ]
        ];
    }
);

// Tool: Route Request
$server->tool(
    'route_request',
    'Route a request to the appropriate microservice',
    [
        'type' => 'object',
        'properties' => [
            'service_name' => [
                'type' => 'string',
                'description' => 'Target service name'
            ],
            'endpoint' => [
                'type' => 'string',
                'description' => 'Service endpoint to call'
            ],
            'payload' => [
                'type' => 'object',
                'description' => 'Request payload',
                'additionalProperties' => true
            ],
            'load_balancing' => [
                'type' => 'string',
                'enum' => ['round_robin', 'least_connections', 'random'],
                'description' => 'Load balancing strategy',
                'default' => 'round_robin'
            ]
        ],
        'required' => ['service_name', 'endpoint']
    ],
    function (array $args) use ($serviceRegistry, $circuitBreaker, $loadBalancer): array {
        $serviceName = $args['service_name'];
        $endpoint = $args['endpoint'];
        $payload = $args['payload'] ?? [];
        $strategy = $args['load_balancing'] ?? 'round_robin';

        try {
            // Check if service exists
            $service = $serviceRegistry->getService($serviceName);
            if (!$service) {
                throw new McpError(-32602, "Service '{$serviceName}' not found");
            }

            // Check if endpoint is available
            if (!in_array($endpoint, $service['config']['endpoints'])) {
                throw new McpError(-32602, "Endpoint '{$endpoint}' not available on service '{$serviceName}'");
            }

            // Use circuit breaker for the request
            $result = $circuitBreaker->call($serviceName, function () use ($serviceName, $endpoint, $payload, $service, $loadBalancer, $strategy) {
                // Get healthy services for load balancing
                $healthyServices = [$serviceName => $service]; // Simplified for demo

                if ($service['health_status'] !== 'healthy') {
                    throw new Exception("Service '{$serviceName}' is unhealthy");
                }

                // Simulate service call with load balancing
                $selectedInstance = $loadBalancer->selectService($service['config']['instances'], $strategy);

                // Mock response based on endpoint
                return match ($endpoint) {
                    'create_user' => [
                        'user_id' => rand(1000, 9999),
                        'status' => 'created',
                        'service_instance' => $selectedInstance ?? 'unknown'
                    ],
                    'get_user' => [
                        'user_id' => $payload['user_id'] ?? 1234,
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'service_instance' => $selectedInstance ?? 'unknown'
                    ],
                    'create_order' => [
                        'order_id' => rand(10000, 99999),
                        'status' => 'pending',
                        'total' => $payload['total'] ?? 99.99,
                        'service_instance' => $selectedInstance ?? 'unknown'
                    ],
                    'check_stock' => [
                        'item_id' => $payload['item_id'] ?? 'ITEM-001',
                        'available' => rand(0, 100),
                        'reserved' => rand(0, 20),
                        'service_instance' => $selectedInstance ?? 'unknown'
                    ],
                    'send_email' => [
                        'message_id' => uniqid(),
                        'status' => 'sent',
                        'recipient' => $payload['recipient'] ?? 'user@example.com',
                        'service_instance' => $selectedInstance ?? 'unknown'
                    ],
                    default => [
                        'message' => "Endpoint '{$endpoint}' called successfully",
                        'service_instance' => $selectedInstance ?? 'unknown'
                    ]
                };
            });

            // Record successful request
            $serviceRegistry->incrementRequests($serviceName);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "🚀 Request routed successfully to {$serviceName}::{$endpoint}\n\n" .
                            "Response:\n" . json_encode($result, JSON_PRETTY_PRINT)
                    ]
                ]
            ];
        } catch (Exception $e) {
            // Record error
            $serviceRegistry->incrementErrors($serviceName);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "❌ Request failed: {$e->getMessage()}\n\n" .
                            "Service: {$serviceName}\n" .
                            "Endpoint: {$endpoint}\n" .
                            "Circuit Breaker Status: " .
                            json_encode($circuitBreaker->getCircuitStatus($serviceName), JSON_PRETTY_PRINT)
                    ]
                ]
            ];
        }
    }
);

// Tool: Circuit Breaker Status
$server->tool(
    'circuit_status',
    'Get circuit breaker status for all services',
    [
        'type' => 'object',
        'properties' => []
    ],
    function (array $args) use ($circuitBreaker): array {
        $circuits = $circuitBreaker->getAllCircuits();

        $status = "⚡ Circuit Breaker Status\n\n";

        if (empty($circuits)) {
            $status .= "No circuits registered.\n";
        } else {
            foreach ($circuits as $serviceName => $circuit) {
                $stateIcon = match ($circuit['state']) {
                    'closed' => '✅',
                    'open' => '🔴',
                    'half-open' => '🟡'
                };

                $status .= "{$stateIcon} **{$serviceName}**: {$circuit['state']}\n";
                $status .= "   Failures: {$circuit['failures']}\n";
                $status .= "   Last Failure: " . ($circuit['last_failure'] ? date('c', $circuit['last_failure']) : 'None') . "\n";
                $status .= "   Last Success: " . ($circuit['last_success'] ? date('c', $circuit['last_success']) : 'None') . "\n\n";
            }
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $status
                ]
            ]
        ];
    }
);

// Resource: Service Topology
$server->resource(
    'Service Topology',
    'microservices://topology',
    [
        'title' => 'Microservices Architecture Topology',
        'description' => 'Visual representation of the microservices architecture',
        'mimeType' => 'application/json'
    ],
    function () use ($serviceRegistry): string {
        $topology = [
            'architecture' => 'microservices',
            'pattern' => 'api_gateway',
            'components' => [
                'api_gateway' => [
                    'role' => 'entry_point',
                    'responsibilities' => ['routing', 'load_balancing', 'circuit_breaking', 'service_discovery']
                ],
                'service_registry' => [
                    'role' => 'service_discovery',
                    'responsibilities' => ['service_registration', 'health_monitoring', 'service_lookup']
                ],
                'services' => $serviceRegistry->getAllServices()
            ],
            'communication_patterns' => [
                'synchronous' => 'MCP protocol',
                'load_balancing' => ['round_robin', 'least_connections', 'random'],
                'fault_tolerance' => 'circuit_breaker'
            ],
            'deployment' => [
                'containerization' => 'Docker',
                'orchestration' => 'Kubernetes',
                'service_mesh' => 'Optional (Istio/Linkerd)'
            ]
        ];

        return json_encode($topology, JSON_PRETTY_PRINT);
    }
);

// Prompt: Microservices Help
$server->prompt(
    'microservices_help',
    'Get help with microservices architecture',
    function (): array {
        return [
            'description' => 'Microservices Architecture Assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How does this microservices architecture work?'
                        ]
                    ]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "This MCP-based microservices architecture includes:\n\n" .
                                "**🏗️ Architecture Components:**\n" .
                                "• **API Gateway**: Central entry point for all requests\n" .
                                "• **Service Registry**: Manages service discovery and health\n" .
                                "• **Circuit Breaker**: Prevents cascade failures\n" .
                                "• **Load Balancer**: Distributes requests across instances\n\n" .
                                "**🔧 Available Tools:**\n" .
                                "• **discover_services** - Find available microservices\n" .
                                "• **route_request** - Route requests to services\n" .
                                "• **circuit_status** - Monitor circuit breaker health\n\n" .
                                "**🌐 Services:**\n" .
                                "• user-service: User management\n" .
                                "• order-service: Order processing\n" .
                                "• inventory-service: Stock management\n" .
                                "• notification-service: Messaging\n\n" .
                                "**🔄 Patterns:**\n" .
                                "• Service Discovery\n" .
                                "• Load Balancing (Round Robin, Least Connections)\n" .
                                "• Circuit Breaker for fault tolerance\n" .
                                "• Health monitoring\n\n" .
                                "Try: 'Use discover_services to see all available services'"
                        ]
                    ]
                ]
            ]
        ];
    }
);

// Start the API Gateway
async(function () use ($server, $serviceRegistry) {
    echo "🏗️ Microservices API Gateway starting...\n";
    echo "📋 Registered services: " . count($serviceRegistry->getAllServices()) . "\n";
    echo "🟢 Healthy services: " . count($serviceRegistry->getHealthyServices()) . "\n";
    echo "🛠️  Available tools: discover_services, route_request, circuit_status\n";
    echo "🔗 Architecture: API Gateway + Service Discovery + Circuit Breaker\n" . PHP_EOL;

    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
