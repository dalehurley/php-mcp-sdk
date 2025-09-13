#!/usr/bin/env php
<?php

/**
 * OAuth Protected Server Example
 * 
 * This example demonstrates how to create an MCP server with OAuth authentication:
 * - OAuth 2.0 flow implementation
 * - Token validation and scope checking
 * - Protected tools and resources
 * - User session management
 * - Secure API endpoints
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load required files to ensure all classes are available
require_once __DIR__ . '/../../src/Shared/Protocol.php';
require_once __DIR__ . '/../../src/Server/RegisteredItems.php';
require_once __DIR__ . '/../../src/Server/ResourceTemplate.php';
require_once __DIR__ . '/../../src/Server/ServerOptions.php';
require_once __DIR__ . '/../../src/Server/Server.php';

use MCP\Server\McpServer;
use MCP\Server\ServerOptions;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Server\Auth\OAuthProvider;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\Resources\TextResourceContents;
use MCP\Types\Content\TextContent;
use function Amp\async;

// Create the server with OAuth capabilities
$server = new McpServer(
    new Implementation('oauth-server', '1.0.0', 'OAuth Protected Server'),
    new ServerOptions(
        capabilities: new ServerCapabilities(
            tools: ['listChanged' => true],
            resources: ['listChanged' => true]
        ),
        instructions: "This server demonstrates OAuth 2.0 authentication with protected resources and scoped access."
    )
);

// OAuth configuration
$oauthConfig = [
    'client_id' => $_ENV['OAUTH_CLIENT_ID'] ?? 'demo_client',
    'client_secret' => $_ENV['OAUTH_CLIENT_SECRET'] ?? 'demo_secret',
    'issuer' => $_ENV['OAUTH_ISSUER'] ?? 'https://auth.example.com',
    'scopes' => [
        'read' => 'Read access to resources',
        'write' => 'Write access to resources',
        'admin' => 'Administrative access'
    ]
];

// In-memory storage for demo (use proper database in production)
$users = [
    'user1' => [
        'id' => 'user1',
        'username' => 'alice',
        'email' => 'alice@example.com',
        'scopes' => ['read', 'write'],
        'profile' => [
            'name' => 'Alice Johnson',
            'role' => 'Developer',
            'department' => 'Engineering'
        ]
    ],
    'user2' => [
        'id' => 'user2',
        'username' => 'bob',
        'email' => 'bob@example.com',
        'scopes' => ['read'],
        'profile' => [
            'name' => 'Bob Smith',
            'role' => 'Analyst',
            'department' => 'Data Science'
        ]
    ],
    'admin' => [
        'id' => 'admin',
        'username' => 'admin',
        'email' => 'admin@example.com',
        'scopes' => ['read', 'write', 'admin'],
        'profile' => [
            'name' => 'System Administrator',
            'role' => 'Admin',
            'department' => 'IT'
        ]
    ]
];

$sessions = [];
$accessTokens = [];

/**
 * Validate access token and return user info
 */
function validateToken(string $token): ?array
{
    global $accessTokens, $users;

    if (!isset($accessTokens[$token])) {
        return null;
    }

    $tokenInfo = $accessTokens[$token];

    // Check if token is expired
    if (time() > $tokenInfo['expires_at']) {
        unset($accessTokens[$token]);
        return null;
    }

    $userId = $tokenInfo['user_id'];
    if (!isset($users[$userId])) {
        return null;
    }

    return [
        'user' => $users[$userId],
        'token_info' => $tokenInfo
    ];
}

/**
 * Check if user has required scope
 */
function hasScope(array $userScopes, string $requiredScope): bool
{
    return in_array($requiredScope, $userScopes);
}

/**
 * Generate a demo access token
 */
function generateAccessToken(string $userId, array $scopes): string
{
    global $accessTokens;

    $token = 'demo_' . bin2hex(random_bytes(16));
    $accessTokens[$token] = [
        'user_id' => $userId,
        'scopes' => $scopes,
        'created_at' => time(),
        'expires_at' => time() + 3600, // 1 hour
        'token_type' => 'Bearer'
    ];

    return $token;
}

// Register OAuth info resource
$server->resource(
    'oauth-info',
    'oauth://info',
    [
        'title' => 'OAuth Configuration',
        'description' => 'OAuth 2.0 configuration and endpoints',
        'mimeType' => 'application/json'
    ],
    function ($uri, $extra) use ($oauthConfig) {
        $info = [
            'oauth_version' => '2.0',
            'client_id' => $oauthConfig['client_id'],
            'issuer' => $oauthConfig['issuer'],
            'supported_scopes' => $oauthConfig['scopes'],
            'endpoints' => [
                'authorization' => $oauthConfig['issuer'] . '/oauth/authorize',
                'token' => $oauthConfig['issuer'] . '/oauth/token',
                'userinfo' => $oauthConfig['issuer'] . '/oauth/userinfo'
            ],
            'demo_note' => 'This is a demonstration server. Use the demo-login tool to get access tokens.'
        ];

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: $uri,
                    text: json_encode($info, JSON_PRETTY_PRINT),
                    mimeType: 'application/json'
                )
            ]
        );
    }
);

// Register user profile resource (requires authentication)
$server->resource(
    'profile',
    'user://profile',
    [
        'title' => 'User Profile',
        'description' => 'Current user profile information (requires authentication)',
        'mimeType' => 'application/json'
    ],
    function ($uri, $extra) use ($users) {
        // Check for authorization header
        $authHeader = $extra['headers']['authorization'] ?? '';
        if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            throw new \Exception('Missing or invalid authorization header');
        }

        $token = $matches[1];
        $auth = validateToken($token);

        if (!$auth) {
            throw new \Exception('Invalid or expired access token');
        }

        $user = $auth['user'];
        $tokenInfo = $auth['token_info'];

        // Check read scope
        if (!hasScope($user['scopes'], 'read')) {
            throw new \Exception('Insufficient permissions: read scope required');
        }

        $profile = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'profile' => $user['profile'],
            'scopes' => $user['scopes'],
            'token_expires_at' => date('c', $tokenInfo['expires_at'])
        ];

        return new ReadResourceResult(
            contents: [
                new TextResourceContents(
                    uri: $uri,
                    text: json_encode($profile, JSON_PRETTY_PRINT),
                    mimeType: 'application/json'
                )
            ]
        );
    }
);

// Demo login tool (for testing - not for production)
$server->tool(
    'demo-login',
    'Get a demo access token for testing (development only)',
    [
        'username' => [
            'type' => 'string',
            'description' => 'Username (alice, bob, or admin)',
            'enum' => ['alice', 'bob', 'admin']
        ]
    ],
    function (array $args) use ($users) {
        $username = $args['username'] ?? '';

        // Find user by username
        $user = null;
        $userId = null;
        foreach ($users as $id => $userData) {
            if ($userData['username'] === $username) {
                $user = $userData;
                $userId = $id;
                break;
            }
        }

        if (!$user) {
            return new CallToolResult(
                content: [new TextContent('Invalid username. Use: alice, bob, or admin')],
                isError: true
            );
        }

        $token = generateAccessToken($userId, $user['scopes']);

        $response = [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => implode(' ', $user['scopes']),
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ],
            'usage' => "Use this token in the Authorization header: 'Bearer $token'"
        ];

        return new CallToolResult(
            content: [new TextContent(
                "Demo Access Token Generated:\n" .
                    json_encode($response, JSON_PRETTY_PRINT)
            )]
        );
    }
);

// Protected tool - requires read scope
$server->tool(
    'list-users',
    'List all users (requires read scope)',
    [
        'include_profiles' => [
            'type' => 'boolean',
            'description' => 'Include full profile information',
            'default' => false
        ]
    ],
    function (array $args, array $context = []) use ($users) {
        // Extract auth from context (this would be set by middleware in a real implementation)
        $authHeader = $context['headers']['authorization'] ?? '';
        if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return new CallToolResult(
                content: [new TextContent('Authentication required: Missing authorization header')],
                isError: true
            );
        }

        $token = $matches[1];
        $auth = validateToken($token);

        if (!$auth) {
            return new CallToolResult(
                content: [new TextContent('Authentication failed: Invalid or expired token')],
                isError: true
            );
        }

        $currentUser = $auth['user'];

        // Check read scope
        if (!hasScope($currentUser['scopes'], 'read')) {
            return new CallToolResult(
                content: [new TextContent('Access denied: read scope required')],
                isError: true
            );
        }

        $includeProfiles = $args['include_profiles'] ?? false;
        $userList = [];

        foreach ($users as $user) {
            $userInfo = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ];

            if ($includeProfiles) {
                $userInfo['profile'] = $user['profile'];
                $userInfo['scopes'] = $user['scopes'];
            }

            $userList[] = $userInfo;
        }

        return new CallToolResult(
            content: [new TextContent(
                "User List (requested by {$currentUser['username']}):\n" .
                    json_encode($userList, JSON_PRETTY_PRINT)
            )]
        );
    }
);

// Protected tool - requires write scope
$server->tool(
    'update-profile',
    'Update user profile (requires write scope)',
    [
        'field' => [
            'type' => 'string',
            'description' => 'Profile field to update',
            'enum' => ['name', 'role', 'department']
        ],
        'value' => [
            'type' => 'string',
            'description' => 'New value for the field'
        ]
    ],
    function (array $args, array $context = []) use (&$users) {
        $authHeader = $context['headers']['authorization'] ?? '';
        if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return new CallToolResult(
                content: [new TextContent('Authentication required')],
                isError: true
            );
        }

        $token = $matches[1];
        $auth = validateToken($token);

        if (!$auth) {
            return new CallToolResult(
                content: [new TextContent('Authentication failed')],
                isError: true
            );
        }

        $currentUser = $auth['user'];
        $userId = $currentUser['id'];

        // Check write scope
        if (!hasScope($currentUser['scopes'], 'write')) {
            return new CallToolResult(
                content: [new TextContent('Access denied: write scope required')],
                isError: true
            );
        }

        $field = $args['field'] ?? '';
        $value = $args['value'] ?? '';

        if (empty($field) || empty($value)) {
            return new CallToolResult(
                content: [new TextContent('Both field and value are required')],
                isError: true
            );
        }

        // Update user profile
        $oldValue = $users[$userId]['profile'][$field] ?? 'not set';
        $users[$userId]['profile'][$field] = $value;

        return new CallToolResult(
            content: [new TextContent(
                "Profile updated successfully!\n" .
                    "Field: $field\n" .
                    "Old value: $oldValue\n" .
                    "New value: $value"
            )]
        );
    }
);

// Admin tool - requires admin scope
$server->tool(
    'admin-stats',
    'Get server statistics (requires admin scope)',
    [],
    function (array $args, array $context = []) use ($users, $sessions, $accessTokens) {
        $authHeader = $context['headers']['authorization'] ?? '';
        if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return new CallToolResult(
                content: [new TextContent('Authentication required')],
                isError: true
            );
        }

        $token = $matches[1];
        $auth = validateToken($token);

        if (!$auth) {
            return new CallToolResult(
                content: [new TextContent('Authentication failed')],
                isError: true
            );
        }

        $currentUser = $auth['user'];

        // Check admin scope
        if (!hasScope($currentUser['scopes'], 'admin')) {
            return new CallToolResult(
                content: [new TextContent('Access denied: admin scope required')],
                isError: true
            );
        }

        // Calculate stats
        $stats = [
            'server_info' => [
                'uptime' => 'Demo server',
                'version' => '1.0.0'
            ],
            'users' => [
                'total_users' => count($users),
                'active_tokens' => count($accessTokens),
                'active_sessions' => count($sessions)
            ],
            'tokens' => []
        ];

        // Token statistics (anonymized)
        foreach ($accessTokens as $tokenData) {
            $stats['tokens'][] = [
                'user_id' => substr($tokenData['user_id'], 0, 4) . '***',
                'scopes' => $tokenData['scopes'],
                'created_at' => date('c', $tokenData['created_at']),
                'expires_at' => date('c', $tokenData['expires_at']),
                'expires_in_seconds' => max(0, $tokenData['expires_at'] - time())
            ];
        }

        return new CallToolResult(
            content: [new TextContent(
                "Admin Statistics:\n" .
                    json_encode($stats, JSON_PRETTY_PRINT)
            )]
        );
    }
);

// Token validation tool
$server->tool(
    'validate-token',
    'Validate an access token',
    [
        'token' => [
            'type' => 'string',
            'description' => 'Access token to validate'
        ]
    ],
    function (array $args) {
        $token = $args['token'] ?? '';

        if (empty($token)) {
            return new CallToolResult(
                content: [new TextContent('Token is required')],
                isError: true
            );
        }

        $auth = validateToken($token);

        if (!$auth) {
            return new CallToolResult(
                content: [new TextContent('Token is invalid or expired')],
                isError: true
            );
        }

        $user = $auth['user'];
        $tokenInfo = $auth['token_info'];

        $validation = [
            'valid' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ],
            'scopes' => $tokenInfo['scopes'],
            'expires_at' => date('c', $tokenInfo['expires_at']),
            'expires_in_seconds' => max(0, $tokenInfo['expires_at'] - time())
        ];

        return new CallToolResult(
            content: [new TextContent(
                "Token Validation Result:\n" .
                    json_encode($validation, JSON_PRETTY_PRINT)
            )]
        );
    }
);

// Set up the transport and start the server
async(function () use ($server, $oauthConfig) {
    try {
        $transport = new StdioServerTransport();

        echo "Starting OAuth Protected Server on stdio...\n";
        echo "OAuth Configuration:\n";
        echo "- Client ID: {$oauthConfig['client_id']}\n";
        echo "- Issuer: {$oauthConfig['issuer']}\n";
        echo "- Supported scopes: " . implode(', ', array_keys($oauthConfig['scopes'])) . "\n\n";

        echo "Demo Users:\n";
        echo "- alice (scopes: read, write)\n";
        echo "- bob (scopes: read)\n";
        echo "- admin (scopes: read, write, admin)\n\n";

        echo "Usage:\n";
        echo "1. Use 'demo-login' tool to get an access token\n";
        echo "2. Use the token in Authorization header: 'Bearer <token>'\n";
        echo "3. Access protected tools and resources\n\n";

        $server->connect($transport)->await();
    } catch (\Throwable $e) {
        error_log("Server error: " . $e->getMessage());
        exit(1);
    }
})->await();
