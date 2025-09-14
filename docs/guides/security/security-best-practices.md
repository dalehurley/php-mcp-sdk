# Security Best Practices for MCP Applications

Security is paramount when building MCP applications, especially those handling sensitive data or integrating with external systems. This guide provides comprehensive security best practices for both MCP servers and clients.

## 🛡️ Core Security Principles

### 1. Defense in Depth

Implement multiple layers of security:

- **Authentication** - Verify identity
- **Authorization** - Control access
- **Input Validation** - Sanitize all inputs
- **Output Encoding** - Prevent injection attacks
- **Transport Security** - Encrypt communications
- **Audit Logging** - Track all activities

### 2. Principle of Least Privilege

Grant minimal necessary permissions:

- Tools should only access required resources
- Users should only have necessary permissions
- Processes should run with minimal privileges
- Network access should be restricted

### 3. Fail Securely

When errors occur, fail to a secure state:

- Don't expose sensitive information in error messages
- Log security events for monitoring
- Gracefully handle authentication failures
- Maintain security even during system failures

## 🔐 Authentication Patterns

### OAuth 2.0 Implementation

```php
use MCP\Server\Auth\OAuth2Provider;

$authProvider = new OAuth2Provider([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'token_endpoint' => 'https://auth.example.com/token',
    'jwks_uri' => 'https://auth.example.com/.well-known/jwks.json',
    'audience' => 'mcp-server'
]);

$server = new McpServer(
    new Implementation('secure-server', '1.0.0'),
    new ServerOptions(
        authProvider: $authProvider
    )
);
```

### Custom Authentication

```php
use MCP\Server\Auth\AuthProvider;

class CustomAuthProvider implements AuthProvider
{
    public function authenticate(array $credentials): ?AuthInfo
    {
        $token = $credentials['bearer_token'] ?? null;

        if (!$token) {
            return null;
        }

        // Validate token (implement your logic)
        $user = $this->validateToken($token);

        if (!$user) {
            return null;
        }

        return new AuthInfo(
            userId: $user['id'],
            permissions: $user['permissions'],
            metadata: ['role' => $user['role']]
        );
    }

    private function validateToken(string $token): ?array
    {
        // Implement token validation
        // - Verify signature
        // - Check expiration
        // - Validate audience
        // - Check revocation status

        try {
            $jwt = JWT::decode($token, $this->getPublicKey(), ['RS256']);

            return [
                'id' => $jwt->sub,
                'permissions' => $jwt->permissions ?? [],
                'role' => $jwt->role ?? 'user'
            ];
        } catch (Exception $e) {
            error_log("Token validation failed: " . $e->getMessage());
            return null;
        }
    }
}
```

## 🔒 Authorization Patterns

### Role-Based Access Control (RBAC)

```php
class RBACAuthorizer
{
    private array $rolePermissions = [
        'admin' => ['read:*', 'write:*', 'delete:*', 'manage:*'],
        'editor' => ['read:*', 'write:content', 'write:media'],
        'author' => ['read:own', 'write:own'],
        'viewer' => ['read:public']
    ];

    public function hasPermission(string $role, string $permission): bool
    {
        $permissions = $this->rolePermissions[$role] ?? [];

        foreach ($permissions as $granted) {
            if ($this->matchesPermission($granted, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPermission(string $granted, string $required): bool
    {
        // Handle wildcards
        if ($granted === $required) {
            return true;
        }

        if (str_ends_with($granted, '*')) {
            $prefix = substr($granted, 0, -1);
            return str_starts_with($required, $prefix);
        }

        return false;
    }
}

$authorizer = new RBACAuthorizer();

$server->tool(
    'delete_user',
    'Delete a user account',
    $schema,
    function (array $args, RequestHandlerExtra $extra) use ($authorizer): array {
        $authInfo = $extra->getAuthInfo();

        if (!$authInfo || !$authInfo->isAuthenticated()) {
            throw new McpError(-32604, 'Authentication required');
        }

        $userRole = $authInfo->getMetadata()['role'] ?? 'viewer';

        if (!$authorizer->hasPermission($userRole, 'delete:users')) {
            throw new McpError(-32605, 'Insufficient permissions to delete users');
        }

        // Proceed with deletion
        return $this->deleteUser($args['user_id']);
    }
);
```

### Resource-Level Authorization

```php
$server->resource(
    'Sensitive Reports',
    'reports://sensitive/{report_id}',
    [
        'title' => 'Sensitive Business Reports',
        'description' => 'Access-controlled business reports'
    ],
    function (string $uri, array $variables, RequestHandlerExtra $extra): string {
        $authInfo = $extra->getAuthInfo();

        // Check authentication
        if (!$authInfo || !$authInfo->isAuthenticated()) {
            throw new McpError(-32604, 'Authentication required');
        }

        $reportId = $variables['report_id'];
        $userRole = $authInfo->getMetadata()['role'] ?? 'viewer';

        // Check if user can access this specific report
        if (!$this->canAccessReport($reportId, $userRole, $authInfo->getUserId())) {
            throw new McpError(-32605, 'Access denied to this report');
        }

        // Audit log the access
        $this->auditLog('resource_access', [
            'user_id' => $authInfo->getUserId(),
            'resource' => $uri,
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        return $this->getReport($reportId);
    }
);
```

## 🔍 Input Validation & Sanitization

### Comprehensive Input Validation

```php
class InputValidator
{
    public function validateUserInput(array $input): array
    {
        $errors = [];

        // Required field validation
        if (empty($input['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        // String length validation
        if (isset($input['name'])) {
            if (strlen($input['name']) < 2) {
                $errors[] = 'Name must be at least 2 characters';
            }
            if (strlen($input['name']) > 100) {
                $errors[] = 'Name cannot exceed 100 characters';
            }
        }

        // Pattern validation
        if (isset($input['phone']) && !preg_match('/^\+?[\d\s\-\(\)]+$/', $input['phone'])) {
            $errors[] = 'Invalid phone number format';
        }

        // Business rule validation
        if (isset($input['age']) && ($input['age'] < 13 || $input['age'] > 120)) {
            $errors[] = 'Age must be between 13 and 120';
        }

        return $errors;
    }

    public function sanitizeInput(array $input): array
    {
        $sanitized = [];

        foreach ($input as $key => $value) {
            if (is_string($value)) {
                // Remove potentially dangerous characters
                $value = strip_tags($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $value = trim($value);
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
```

### SQL Injection Prevention

```php
class SecureDatabase
{
    private PDO $pdo;

    public function findUser(int $userId): ?array
    {
        // ✅ Good: Use prepared statements
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function searchUsers(string $query): array
    {
        // ✅ Good: Parameterized query
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE name LIKE ? OR email LIKE ?');
        $searchTerm = "%{$query}%";
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ❌ Bad: Never do this!
    public function badSearchUsers(string $query): array
    {
        // This is vulnerable to SQL injection!
        $sql = "SELECT * FROM users WHERE name LIKE '%{$query}%'";
        return $this->pdo->query($sql)->fetchAll();
    }
}
```

## 🔐 Secure File Operations

### Path Traversal Prevention

```php
class SecureFileHandler
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = realpath($basePath);
    }

    public function readFile(string $relativePath): string
    {
        // Prevent path traversal attacks
        $fullPath = $this->basePath . DIRECTORY_SEPARATOR . $relativePath;
        $realPath = realpath($fullPath);

        // Ensure the file is within the allowed directory
        if (!$realPath || !str_starts_with($realPath, $this->basePath)) {
            throw new McpError(-32602, 'Access denied: Path outside allowed directory');
        }

        if (!file_exists($realPath)) {
            throw new McpError(-32602, 'File not found');
        }

        if (!is_readable($realPath)) {
            throw new McpError(-32602, 'File not readable');
        }

        return file_get_contents($realPath);
    }

    public function isValidPath(string $path): bool
    {
        // Additional path validation
        $forbidden = ['..', './', '\\', null, chr(0)];

        foreach ($forbidden as $pattern) {
            if (str_contains($path, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
```

## 🔒 Secure Communication

### Transport Security

```php
// HTTPS Transport with TLS
$transport = new HttpServerTransport([
    'host' => '0.0.0.0',
    'port' => 443,
    'tls' => [
        'cert_file' => '/path/to/certificate.pem',
        'key_file' => '/path/to/private-key.pem',
        'verify_peer' => true,
        'verify_peer_name' => true,
        'min_version' => 'TLSv1.2'
    ]
]);

// WebSocket with WSS
$transport = new WebSocketServerTransport([
    'host' => '0.0.0.0',
    'port' => 443,
    'tls' => true,
    'cert_file' => '/path/to/certificate.pem',
    'key_file' => '/path/to/private-key.pem'
]);
```

### Request Rate Limiting

```php
class RateLimiter
{
    private array $requests = [];

    public function isAllowed(string $clientId, int $maxRequests = 100, int $windowSeconds = 60): bool
    {
        $now = time();
        $windowStart = $now - $windowSeconds;

        // Clean old requests
        $this->requests[$clientId] = array_filter(
            $this->requests[$clientId] ?? [],
            fn($timestamp) => $timestamp > $windowStart
        );

        // Check limit
        if (count($this->requests[$clientId]) >= $maxRequests) {
            return false;
        }

        // Record this request
        $this->requests[$clientId][] = $now;
        return true;
    }
}

$rateLimiter = new RateLimiter();

// Apply rate limiting to tools
$server->tool(
    'expensive_operation',
    'Perform expensive operation',
    $schema,
    function (array $args, RequestHandlerExtra $extra) use ($rateLimiter): array {
        $clientId = $extra->getClientId() ?? 'anonymous';

        if (!$rateLimiter->isAllowed($clientId, 10, 60)) { // 10 requests per minute
            throw new McpError(-32603, 'Rate limit exceeded');
        }

        return $this->performExpensiveOperation($args);
    }
);
```

## 🔍 Audit Logging

### Comprehensive Audit Trail

```php
class AuditLogger
{
    private string $logFile;

    public function __construct(string $logFile = '/var/log/mcp-audit.log')
    {
        $this->logFile = $logFile;
    }

    public function log(string $event, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'event' => $event,
            'context' => $context,
            'server_info' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'request_id' => uniqid()
            ]
        ];

        $logLine = json_encode($logEntry) . "\n";

        // Atomic write to log file
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);

        // Also send to syslog for centralized logging
        syslog(LOG_INFO, "MCP_AUDIT: {$event} " . json_encode($context));
    }
}

$auditLogger = new AuditLogger();

// Log authentication events
$server->onAuthentication(function(AuthInfo $authInfo) use ($auditLogger) {
    $auditLogger->log('authentication_success', [
        'user_id' => $authInfo->getUserId(),
        'permissions' => $authInfo->getPermissions(),
        'timestamp' => time()
    ]);
});

// Log tool executions
$server->onToolExecution(function(string $toolName, array $args, $result) use ($auditLogger) {
    $auditLogger->log('tool_execution', [
        'tool' => $toolName,
        'args_hash' => md5(json_encode($args)), // Don't log sensitive args
        'success' => !($result instanceof Exception),
        'execution_time' => microtime(true)
    ]);
});
```

## 🛡️ Data Protection

### Sensitive Data Handling

```php
class SensitiveDataHandler
{
    private string $encryptionKey;

    public function __construct(string $encryptionKey)
    {
        $this->encryptionKey = $encryptionKey;
    }

    public function encryptSensitiveData(array $data): array
    {
        $sensitiveFields = ['ssn', 'credit_card', 'password', 'api_key'];

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitiveFields) && is_string($value)) {
                $data[$key] = $this->encrypt($value);
            }
        }

        return $data;
    }

    public function redactSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'api_key', 'secret', 'token'];

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitiveFields)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    private function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $encryptedData): string
    {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
}
```

### PII (Personally Identifiable Information) Protection

```php
$server->tool(
    'get_user_profile',
    'Get user profile information',
    $schema,
    function (array $args, RequestHandlerExtra $extra) use ($dataHandler): array {
        $authInfo = $extra->getAuthInfo();
        $requestedUserId = $args['user_id'];

        // Users can only access their own profile unless they're admin
        if ($authInfo->getUserId() !== $requestedUserId) {
            $userRole = $authInfo->getMetadata()['role'] ?? 'user';
            if ($userRole !== 'admin') {
                throw new McpError(-32605, 'Can only access your own profile');
            }
        }

        $user = $this->getUser($requestedUserId);

        // Redact sensitive information based on permissions
        if ($userRole !== 'admin' && $authInfo->getUserId() !== $requestedUserId) {
            $user = $dataHandler->redactSensitiveData($user);
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($user, JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
);
```

## 🔐 Environment Security

### Secure Configuration

```php
class SecureConfig
{
    private array $config;

    public function __construct()
    {
        $this->loadConfiguration();
        $this->validateConfiguration();
    }

    private function loadConfiguration(): void
    {
        // Load from environment variables (12-factor app)
        $this->config = [
            'database_url' => $this->getEnvVar('DATABASE_URL'),
            'encryption_key' => $this->getEnvVar('ENCRYPTION_KEY'),
            'oauth_client_secret' => $this->getEnvVar('OAUTH_CLIENT_SECRET'),
            'api_keys' => [
                'openai' => $this->getEnvVar('OPENAI_API_KEY'),
                'stripe' => $this->getEnvVar('STRIPE_SECRET_KEY')
            ]
        ];
    }

    private function getEnvVar(string $name): string
    {
        $value = $_ENV[$name] ?? getenv($name);

        if ($value === false || $value === '') {
            throw new Exception("Required environment variable {$name} is not set");
        }

        return $value;
    }

    private function validateConfiguration(): void
    {
        // Validate encryption key strength
        if (strlen($this->config['encryption_key']) < 32) {
            throw new Exception('Encryption key must be at least 32 characters');
        }

        // Validate database URL format
        if (!filter_var($this->config['database_url'], FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid database URL format');
        }

        // Validate API keys are not default/example values
        foreach ($this->config['api_keys'] as $service => $key) {
            if (in_array($key, ['your-key-here', 'example-key', 'test-key'])) {
                throw new Exception("API key for {$service} appears to be a placeholder");
            }
        }
    }
}
```

### Secret Management

```php
class SecretManager
{
    private array $secrets = [];

    public function loadSecrets(): void
    {
        // Load secrets from secure storage (not from code!)
        $secretsFile = '/etc/mcp/secrets.json';

        if (!file_exists($secretsFile)) {
            throw new Exception('Secrets file not found');
        }

        $this->secrets = json_decode(file_get_contents($secretsFile), true);

        // Validate secrets are properly encrypted/encoded
        foreach ($this->secrets as $key => $secret) {
            if (!$this->isValidSecret($secret)) {
                throw new Exception("Invalid secret format for {$key}");
            }
        }
    }

    public function getSecret(string $name): string
    {
        if (!isset($this->secrets[$name])) {
            throw new Exception("Secret {$name} not found");
        }

        return $this->decrypt($this->secrets[$name]);
    }

    private function isValidSecret(string $secret): bool
    {
        // Secrets should be encrypted and base64 encoded
        return base64_decode($secret, true) !== false;
    }
}
```

## 🚨 Security Monitoring

### Intrusion Detection

```php
class SecurityMonitor
{
    private array $suspiciousPatterns = [
        'sql_injection' => '/(\bUNION\b|\bSELECT\b.*\bFROM\b|\bDROP\b|\bDELETE\b)/i',
        'xss_attempt' => '/<script|javascript:|vbscript:|onload=|onerror=/i',
        'path_traversal' => '/\.\.[\/\\]|\.\.%2f|\.\.%5c/i',
        'command_injection' => '/[;&|`$(){}]/i'
    ];

    public function scanInput(array $input): array
    {
        $threats = [];

        foreach ($input as $field => $value) {
            if (!is_string($value)) continue;

            foreach ($this->suspiciousPatterns as $threatType => $pattern) {
                if (preg_match($pattern, $value)) {
                    $threats[] = [
                        'type' => $threatType,
                        'field' => $field,
                        'pattern_matched' => $pattern,
                        'severity' => 'high'
                    ];
                }
            }
        }

        return $threats;
    }

    public function blockSuspiciousRequest(array $threats): void
    {
        if (!empty($threats)) {
            $this->logSecurityEvent('suspicious_request_blocked', [
                'threats' => $threats,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp' => time()
            ]);

            throw new McpError(-32603, 'Request blocked due to security policy');
        }
    }
}

$securityMonitor = new SecurityMonitor();

$server->tool(
    'secure_search',
    'Perform secure search',
    $schema,
    function (array $args) use ($securityMonitor): array {
        // Scan for threats
        $threats = $securityMonitor->scanInput($args);
        $securityMonitor->blockSuspiciousRequest($threats);

        // Proceed with safe request
        return $this->performSearch($args);
    }
);
```

## 🔐 Production Security Checklist

### ✅ Server Security

- [ ] **Authentication implemented** and tested
- [ ] **Authorization controls** in place for all tools/resources
- [ ] **Input validation** on all parameters
- [ ] **Output encoding** to prevent injection
- [ ] **Rate limiting** configured appropriately
- [ ] **Audit logging** enabled and monitored
- [ ] **Error messages** don't expose sensitive information
- [ ] **Transport encryption** (TLS/SSL) enabled
- [ ] **Environment variables** used for secrets
- [ ] **File access** restricted to safe directories

### ✅ Infrastructure Security

- [ ] **Firewall rules** restrict network access
- [ ] **Process isolation** (containers, user accounts)
- [ ] **Log monitoring** and alerting configured
- [ ] **Secret management** system in place
- [ ] **Backup encryption** and access controls
- [ ] **Dependency scanning** for vulnerabilities
- [ ] **Security headers** configured (if using HTTP)
- [ ] **Certificate management** and rotation

### ✅ Operational Security

- [ ] **Security incident response** plan
- [ ] **Regular security audits** scheduled
- [ ] **Penetration testing** performed
- [ ] **Vulnerability management** process
- [ ] **Security training** for development team
- [ ] **Compliance requirements** met (GDPR, SOC2, etc.)

## 🚨 Common Vulnerabilities

### 1. Injection Attacks

**Prevention:**

- Use parameterized queries
- Validate and sanitize all inputs
- Use allowlists for dynamic content

### 2. Authentication Bypass

**Prevention:**

- Implement proper session management
- Use strong authentication tokens
- Validate tokens on every request

### 3. Authorization Flaws

**Prevention:**

- Implement proper access controls
- Check permissions on every operation
- Use role-based access control

### 4. Information Disclosure

**Prevention:**

- Sanitize error messages
- Implement proper logging
- Control resource access

### 5. Denial of Service

**Prevention:**

- Implement rate limiting
- Set timeouts on operations
- Monitor resource usage

## 📚 Related Security Resources

- [Authentication Guide](authentication.md)
- [Authorization Guide](authorization.md)
- [Audit Logging Guide](audit-logging.md)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)

---

**Security is not optional - it's essential for any production MCP application. Follow these practices to build secure, trustworthy systems!** 🛡️
