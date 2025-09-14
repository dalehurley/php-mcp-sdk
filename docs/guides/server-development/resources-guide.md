# Managing Resources Effectively

Resources in MCP provide AI models with access to data that they can read and reference. Unlike tools that perform actions, resources offer information - from static content to dynamic data streams. This guide teaches you how to create powerful, efficient resources.

## 🎯 What You'll Learn

- 📚 **Resource Fundamentals** - Understanding resource concepts
- 🔗 **URI Design** - Creating effective resource URIs
- 📊 **Dynamic Resources** - Real-time and computed data
- 🗂️ **Resource Templates** - Parameterized resources
- 🔄 **Caching Strategies** - Performance optimization
- 🛡️ **Security Patterns** - Safe resource access

## 📚 Resource Fundamentals

### Basic Resource Structure

```php
$server->resource(
    'Resource Name',        // Human-readable name
    'scheme://path',       // Unique URI
    [                      // Metadata
        'title' => 'Resource Title',
        'description' => 'What this resource provides',
        'mimeType' => 'text/plain'
    ],
    function (): string {  // Handler function
        return 'Resource content';
    }
);
```

### Simple Example

```php
$server->resource(
    'Server Information',
    'server://info',
    [
        'title' => 'Server Information',
        'description' => 'Basic information about this MCP server',
        'mimeType' => 'application/json'
    ],
    function (): string {
        return json_encode([
            'name' => 'My MCP Server',
            'version' => '1.0.0',
            'uptime' => time() - $_SERVER['REQUEST_TIME'],
            'tools_count' => 5,
            'status' => 'healthy'
        ], JSON_PRETTY_PRINT);
    }
);
```

## 🔗 URI Design Best Practices

### URI Schemes

Use descriptive schemes that indicate the type of resource:

```php
// ✅ Good: Clear, descriptive schemes
'file://path/to/document.txt'     // File system resources
'db://users/123'                  // Database resources
'api://weather/london'            // External API resources
'config://database/settings'      // Configuration resources
'metrics://performance/cpu'       // Metrics and monitoring
'cache://session/user-123'        // Cached data resources

// ❌ Bad: Generic or unclear schemes
'resource://thing'
'data://stuff'
'info://whatever'
```

### Hierarchical Organization

```php
// User management resources
'users://profile/123'
'users://permissions/123'
'users://activity/123'

// Project resources
'projects://metadata/456'
'projects://tasks/456'
'projects://timeline/456'

// System resources
'system://health'
'system://metrics'
'system://configuration'
```

## 📊 Dynamic Resources

### Real-Time Data

```php
$server->resource(
    'Live System Metrics',
    'system://metrics/live',
    [
        'title' => 'Live System Metrics',
        'description' => 'Real-time system performance metrics',
        'mimeType' => 'application/json'
    ],
    function (): string {
        return json_encode([
            'timestamp' => time(),
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => memory_get_usage(true),
            'active_connections' => $this->getActiveConnections(),
            'request_rate' => $this->getRequestRate(),
            'error_rate' => $this->getErrorRate()
        ], JSON_PRETTY_PRINT);
    }
);
```

### Database-Driven Resources

```php
$server->resource(
    'User Directory',
    'users://directory',
    [
        'title' => 'User Directory',
        'description' => 'Complete list of all users with their profiles',
        'mimeType' => 'application/json'
    ],
    function () use ($database): string {
        $users = $database->getAllUsers();

        $directory = [
            'total_users' => count($users),
            'last_updated' => date('c'),
            'users' => array_map(function($user) {
                return [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'last_active' => $user['last_active']
                ];
            }, $users)
        ];

        return json_encode($directory, JSON_PRETTY_PRINT);
    }
);
```

### Computed Resources

```php
$server->resource(
    'Analytics Dashboard',
    'analytics://dashboard',
    [
        'title' => 'Analytics Dashboard Data',
        'description' => 'Computed analytics and insights',
        'mimeType' => 'application/json'
    ],
    function () use ($analyticsEngine): string {
        $dashboard = [
            'computed_at' => date('c'),
            'metrics' => [
                'daily_active_users' => $analyticsEngine->getDailyActiveUsers(),
                'conversion_rate' => $analyticsEngine->getConversionRate(),
                'revenue_trend' => $analyticsEngine->getRevenueTrend(30), // 30 days
                'top_features' => $analyticsEngine->getTopFeatures(10)
            ],
            'insights' => [
                'growth_rate' => $analyticsEngine->calculateGrowthRate(),
                'churn_prediction' => $analyticsEngine->predictChurn(),
                'recommendations' => $analyticsEngine->getRecommendations()
            ]
        ];

        return json_encode($dashboard, JSON_PRETTY_PRINT);
    }
);
```

## 🗂️ Resource Templates

### Parameterized Resources

```php
use MCP\Server\ResourceTemplate;

// Create a template for user profiles
$userTemplate = new ResourceTemplate('users://profile/{user_id}');

$server->resource(
    'User Profile',
    $userTemplate,
    [
        'title' => 'User Profile',
        'description' => 'Individual user profile information'
    ],
    function (string $uri, array $variables) use ($database): string {
        $userId = $variables['user_id'];

        if (!is_numeric($userId)) {
            throw new McpError(-32602, 'Invalid user ID format');
        }

        $user = $database->getUser((int)$userId);

        if (!$user) {
            throw new McpError(-32602, "User {$userId} not found");
        }

        return json_encode([
            'user_id' => $user['id'],
            'profile' => [
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'created_at' => $user['created_at'],
                'last_login' => $user['last_login']
            ],
            'statistics' => [
                'total_logins' => $user['login_count'],
                'projects_count' => $database->getUserProjectCount($user['id']),
                'tasks_completed' => $database->getUserCompletedTasks($user['id'])
            ]
        ], JSON_PRETTY_PRINT);
    }
);
```

### Multiple Templates

```php
// Project resources with different views
$projectTemplates = [
    new ResourceTemplate('projects://overview/{project_id}'),
    new ResourceTemplate('projects://tasks/{project_id}'),
    new ResourceTemplate('projects://timeline/{project_id}'),
    new ResourceTemplate('projects://team/{project_id}')
];

foreach ($projectTemplates as $template) {
    $resourceName = ucwords(str_replace(['projects://', '/{project_id}'], ['', ''], $template->getPattern()));

    $server->resource(
        "Project {$resourceName}",
        $template,
        [
            'title' => "Project {$resourceName}",
            'description' => "Project {$resourceName} information"
        ],
        function (string $uri, array $variables) use ($resourceName): string {
            $projectId = $variables['project_id'];

            return match($resourceName) {
                'Overview' => $this->getProjectOverview($projectId),
                'Tasks' => $this->getProjectTasks($projectId),
                'Timeline' => $this->getProjectTimeline($projectId),
                'Team' => $this->getProjectTeam($projectId),
                default => '{"error": "Unknown resource type"}'
            };
        }
    );
}
```

## 🔄 Caching Strategies

### Simple Caching

```php
class ResourceCache
{
    private array $cache = [];
    private array $ttl = [];

    public function get(string $key): ?string
    {
        if (!isset($this->cache[$key])) {
            return null;
        }

        if (isset($this->ttl[$key]) && $this->ttl[$key] < time()) {
            unset($this->cache[$key], $this->ttl[$key]);
            return null;
        }

        return $this->cache[$key];
    }

    public function set(string $key, string $value, int $ttlSeconds = 300): void
    {
        $this->cache[$key] = $value;
        $this->ttl[$key] = time() + $ttlSeconds;
    }
}

$cache = new ResourceCache();

$server->resource(
    'Cached Analytics',
    'analytics://cached',
    [
        'title' => 'Cached Analytics Data',
        'description' => 'Analytics data with 5-minute cache',
        'mimeType' => 'application/json'
    ],
    function () use ($cache, $analyticsService): string {
        $cacheKey = 'analytics_' . date('Y-m-d-H-i');

        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Expensive computation
        $data = $analyticsService->generateReport();
        $json = json_encode($data, JSON_PRETTY_PRINT);

        // Cache for 5 minutes
        $cache->set($cacheKey, $json, 300);

        return $json;
    }
);
```

### Cache Invalidation

```php
class SmartResourceCache
{
    private array $cache = [];
    private array $dependencies = [];

    public function invalidateByTag(string $tag): void
    {
        foreach ($this->dependencies as $key => $tags) {
            if (in_array($tag, $tags)) {
                unset($this->cache[$key], $this->dependencies[$key]);
            }
        }
    }

    public function setWithTags(string $key, string $value, array $tags): void
    {
        $this->cache[$key] = $value;
        $this->dependencies[$key] = $tags;
    }
}

$smartCache = new SmartResourceCache();

// When user data changes, invalidate related caches
$server->tool('update_user', 'Update user', $schema, function($args) use ($smartCache) {
    $result = $this->updateUser($args);

    // Invalidate all user-related caches
    $smartCache->invalidateByTag("user_{$args['user_id']}");
    $smartCache->invalidateByTag('user_directory');

    return $result;
});
```

## 🛡️ Security Patterns

### Access Control

```php
$server->resource(
    'Sensitive Data',
    'data://sensitive',
    [
        'title' => 'Sensitive Information',
        'description' => 'Access-controlled sensitive data'
    ],
    function (string $uri, array $variables, RequestHandlerExtra $extra): string {
        // Check authentication
        $authInfo = $extra->getAuthInfo();
        if (!$authInfo || !$authInfo->isAuthenticated()) {
            throw new McpError(-32604, 'Authentication required');
        }

        // Check authorization
        if (!$authInfo->hasPermission('read:sensitive_data')) {
            throw new McpError(-32605, 'Insufficient permissions');
        }

        // Return sensitive data
        return json_encode([
            'data' => 'This is sensitive information',
            'accessed_by' => $authInfo->getUserId(),
            'access_time' => date('c')
        ]);
    }
);
```

### Data Sanitization

```php
$server->resource(
    'User Data Export',
    'users://export',
    [
        'title' => 'User Data Export',
        'description' => 'Sanitized user data for export'
    ],
    function () use ($database): string {
        $users = $database->getAllUsers();

        // Sanitize sensitive information
        $sanitizedUsers = array_map(function($user) {
            return [
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
                'created_at' => $user['created_at'],
                // Remove sensitive fields
                // 'email' => $user['email'],  // Removed
                // 'password_hash' => $user['password_hash'],  // Removed
                // 'api_tokens' => $user['api_tokens']  // Removed
            ];
        }, $users);

        return json_encode([
            'export_date' => date('c'),
            'user_count' => count($sanitizedUsers),
            'users' => $sanitizedUsers
        ], JSON_PRETTY_PRINT);
    }
);
```

## 📈 Performance Optimization

### Lazy Loading

```php
class LazyDataLoader
{
    private ?array $expensiveData = null;

    public function getExpensiveData(): array
    {
        if ($this->expensiveData === null) {
            echo "Loading expensive data...\n";
            $this->expensiveData = $this->computeExpensiveData();
        }

        return $this->expensiveData;
    }

    private function computeExpensiveData(): array
    {
        // Simulate expensive computation
        sleep(2);
        return ['computed' => 'expensive data'];
    }
}

$loader = new LazyDataLoader();

$server->resource(
    'Expensive Report',
    'reports://expensive',
    [
        'title' => 'Expensive Computation Report',
        'description' => 'Report that requires expensive computation'
    ],
    function () use ($loader): string {
        $data = $loader->getExpensiveData();
        return json_encode($data, JSON_PRETTY_PRINT);
    }
);
```

### Streaming Large Resources

```php
$server->resource(
    'Large Dataset',
    'data://large-dataset',
    [
        'title' => 'Large Dataset',
        'description' => 'Large dataset with streaming support'
    ],
    function (): string {
        // For very large resources, consider streaming
        ob_start();

        echo "{\n";
        echo "  \"metadata\": {\n";
        echo "    \"total_records\": 1000000,\n";
        echo "    \"generated_at\": \"" . date('c') . "\"\n";
        echo "  },\n";
        echo "  \"data\": [\n";

        for ($i = 0; $i < 1000; $i++) {
            $record = [
                'id' => $i,
                'value' => rand(1, 100),
                'timestamp' => time() - rand(0, 86400)
            ];

            echo "    " . json_encode($record);
            if ($i < 999) echo ",";
            echo "\n";

            // Flush output periodically
            if ($i % 100 === 0) {
                ob_flush();
                flush();
            }
        }

        echo "  ]\n";
        echo "}";

        return ob_get_clean();
    }
);
```

## 🔄 Resource Updates and Notifications

### Change Notifications

```php
class ResourceNotifier
{
    private array $subscribers = [];

    public function subscribe(string $resourceUri, callable $callback): void
    {
        if (!isset($this->subscribers[$resourceUri])) {
            $this->subscribers[$resourceUri] = [];
        }
        $this->subscribers[$resourceUri][] = $callback;
    }

    public function notifyChange(string $resourceUri, array $changeInfo): void
    {
        if (isset($this->subscribers[$resourceUri])) {
            foreach ($this->subscribers[$resourceUri] as $callback) {
                $callback($resourceUri, $changeInfo);
            }
        }
    }
}

$notifier = new ResourceNotifier();

$server->resource(
    'Live Data Feed',
    'data://live-feed',
    [
        'title' => 'Live Data Feed',
        'description' => 'Data that updates in real-time'
    ],
    function () use ($notifier): string {
        // Register for change notifications
        $notifier->subscribe('data://live-feed', function($uri, $change) {
            echo "Resource {$uri} changed: {$change['description']}\n";
        });

        return json_encode([
            'data' => $this->getCurrentData(),
            'last_update' => date('c'),
            'next_update' => date('c', time() + 60)
        ], JSON_PRETTY_PRINT);
    }
);

// Trigger updates
$server->tool('trigger_update', 'Trigger data update', [], function() use ($notifier) {
    $notifier->notifyChange('data://live-feed', [
        'description' => 'Data manually updated',
        'timestamp' => time()
    ]);

    return [
        'content' => [
            ['type' => 'text', 'text' => 'Update triggered']
        ]
    ];
});
```

## 📄 Content Type Handling

### Different MIME Types

```php
// Plain text resource
$server->resource(
    'Plain Text Log',
    'logs://application.log',
    [
        'title' => 'Application Log',
        'mimeType' => 'text/plain'
    ],
    function (): string {
        return file_get_contents('/var/log/application.log');
    }
);

// CSV data resource
$server->resource(
    'Sales Data CSV',
    'data://sales.csv',
    [
        'title' => 'Sales Data',
        'mimeType' => 'text/csv'
    ],
    function (): string {
        $data = $this->getSalesData();

        $csv = "Date,Product,Amount,Customer\n";
        foreach ($data as $row) {
            $csv .= "{$row['date']},{$row['product']},{$row['amount']},{$row['customer']}\n";
        }

        return $csv;
    }
);

// XML resource
$server->resource(
    'Configuration XML',
    'config://settings.xml',
    [
        'title' => 'Server Configuration',
        'mimeType' => 'application/xml'
    ],
    function (): string {
        $config = $this->getConfiguration();

        $xml = new SimpleXMLElement('<configuration/>');
        foreach ($config as $key => $value) {
            $xml->addChild($key, htmlspecialchars($value));
        }

        return $xml->asXML();
    }
);
```

## 🔍 Resource Discovery

### Resource Listing

```php
$server->resource(
    'Resource Catalog',
    'meta://resources',
    [
        'title' => 'Available Resources',
        'description' => 'Catalog of all available resources on this server'
    ],
    function () use ($server): string {
        $resources = $server->getRegisteredResources();

        $catalog = [
            'total_resources' => count($resources),
            'categories' => [],
            'resources' => []
        ];

        foreach ($resources as $resource) {
            $scheme = parse_url($resource['uri'], PHP_URL_SCHEME);
            $catalog['categories'][$scheme] = ($catalog['categories'][$scheme] ?? 0) + 1;

            $catalog['resources'][] = [
                'name' => $resource['name'],
                'uri' => $resource['uri'],
                'description' => $resource['description'],
                'mime_type' => $resource['mimeType'],
                'category' => $scheme
            ];
        }

        return json_encode($catalog, JSON_PRETTY_PRINT);
    }
);
```

## 🎯 Best Practices

### ✅ Resource Design Do's

- **Use descriptive URIs** that clearly indicate content
- **Include comprehensive metadata** (title, description, MIME type)
- **Handle errors gracefully** with appropriate error codes
- **Implement caching** for expensive computations
- **Validate access permissions** for sensitive resources
- **Use appropriate MIME types** for different content formats
- **Document resource updates** and change patterns

### ❌ Resource Design Don'ts

- **Don't use generic URIs** like 'resource://data'
- **Don't skip error handling** - always plan for failures
- **Don't return inconsistent formats** from the same resource
- **Don't expose sensitive information** without proper access control
- **Don't ignore performance** - cache expensive operations
- **Don't forget MIME types** - they help clients understand content

## 🧪 Testing Resources

### Unit Testing

```php
class ResourceTest extends TestCase
{
    public function testUserProfileResource(): void
    {
        $mockDatabase = $this->createMock(Database::class);
        $mockDatabase->method('getUser')
                    ->with(123)
                    ->willReturn([
                        'id' => 123,
                        'name' => 'Test User',
                        'email' => 'test@example.com'
                    ]);

        $server = new McpServer(new Implementation('test', '1.0.0'));

        // Register resource with mock database
        $this->registerUserProfileResource($server, $mockDatabase);

        // Test resource access
        $result = $server->readResourceDirectly('users://profile/123');
        $data = json_decode($result, true);

        $this->assertEquals(123, $data['user_id']);
        $this->assertEquals('Test User', $data['profile']['name']);
    }
}
```

## 📚 Related Guides

- [Tools Guide](tools-guide.md) - Building powerful tools
- [Prompts Guide](prompts-guide.md) - Creating helpful prompts
- [Server API Reference](../../api/server.md) - Complete API documentation
- [Working Examples](../../../examples/real-world/) - Real-world resource examples

---

**Master these resource patterns and you'll be able to provide AI models with rich, dynamic data that makes your MCP server incredibly valuable!** 🚀
