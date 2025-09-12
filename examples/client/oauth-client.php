#!/usr/bin/env php
<?php

/**
 * OAuth Client Example
 * 
 * This example demonstrates how to:
 * - Implement OAuth 2.0 authentication flow
 * - Manage access tokens and refresh tokens
 * - Make authenticated requests to protected MCP servers
 * - Handle token expiration and renewal
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Client\Transport\StdioServerParameters;
use MCP\Client\Auth\OAuthAuthProvider;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ClientCapabilities;
use GuzzleHttp\Client as HttpClient;
use function Amp\async;
use function Amp\await;
use function Amp\delay;

// OAuth Configuration
$oauthConfig = [
    'client_id' => $_ENV['OAUTH_CLIENT_ID'] ?? 'demo_client',
    'client_secret' => $_ENV['OAUTH_CLIENT_SECRET'] ?? 'demo_secret',
    'authorization_url' => $_ENV['OAUTH_AUTH_URL'] ?? 'https://auth.example.com/oauth/authorize',
    'token_url' => $_ENV['OAUTH_TOKEN_URL'] ?? 'https://auth.example.com/oauth/token',
    'redirect_uri' => $_ENV['OAUTH_REDIRECT_URI'] ?? 'http://localhost:8080/callback',
    'scopes' => ['read', 'write']
];

// Token storage (in production, use secure storage)
$tokenStorage = [
    'access_token' => null,
    'refresh_token' => null,
    'expires_at' => null,
    'token_type' => 'Bearer'
];

// Create HTTP client for OAuth requests
$httpClient = new HttpClient([
    'timeout' => 30,
    'headers' => [
        'User-Agent' => 'PHP-MCP-OAuth-Client/1.0.0',
        'Accept' => 'application/json'
    ]
]);

async(function () use ($oauthConfig, &$tokenStorage, $httpClient) {
    try {
        echo "🔐 OAuth MCP Client Example\n";
        echo "===========================\n\n";

        // Check if we have a stored token
        $tokenFile = __DIR__ . '/oauth_tokens.json';
        if (file_exists($tokenFile)) {
            $storedTokens = json_decode(file_get_contents($tokenFile), true);
            if ($storedTokens && isset($storedTokens['access_token'])) {
                $tokenStorage = array_merge($tokenStorage, $storedTokens);
                echo "📁 Loaded stored tokens\n";
            }
        }

        // Check if we need to authenticate
        if (!$tokenStorage['access_token'] || isTokenExpired($tokenStorage)) {
            echo "🔑 Authentication required\n";

            if ($tokenStorage['refresh_token'] && isTokenExpired($tokenStorage)) {
                echo "🔄 Attempting to refresh token...\n";
                refreshAccessToken($httpClient, $oauthConfig, $tokenStorage)->await();
            } else {
                echo "🌐 Starting OAuth flow...\n";
                performOAuthFlow($httpClient, $oauthConfig, $tokenStorage)->await();
            }

            // Save tokens
            file_put_contents($tokenFile, json_encode($tokenStorage, JSON_PRETTY_PRINT));
            echo "💾 Tokens saved\n\n";
        } else {
            echo "✅ Using existing valid token\n\n";
        }

        // Create authenticated MCP client
        $client = createAuthenticatedClient($tokenStorage);

        // Connect to OAuth-protected server
        echo "🔌 Connecting to OAuth-protected MCP server...\n";
        $serverParams = new StdioServerParameters(
            command: 'php',
            args: [__DIR__ . '/../server/oauth-server.php'],
            cwd: dirname(__DIR__, 2)
        );

        $transport = new StdioClientTransport($serverParams);
        $client->connect($transport)->await();

        echo "✅ Connected! Server info: " . json_encode($client->getServerVersion()) . "\n\n";

        // Demonstrate authenticated operations
        demonstrateAuthenticatedOperations($client, $tokenStorage)->await();

        // Test token validation
        testTokenValidation($client, $tokenStorage)->await();

        // Test scope-based access
        testScopedAccess($client, $tokenStorage)->await();

        // Close connection
        echo "\n🔌 Closing connection...\n";
        $client->close()->await();
        echo "✅ OAuth client demo completed!\n";
    } catch (\Throwable $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
})->await();

/**
 * Check if token is expired
 */
function isTokenExpired(array $tokenStorage): bool
{
    if (!$tokenStorage['expires_at']) {
        return true;
    }

    // Add 5-minute buffer
    return time() >= ($tokenStorage['expires_at'] - 300);
}

/**
 * Perform OAuth 2.0 authorization code flow
 */
function performOAuthFlow(HttpClient $httpClient, array $config, array &$tokenStorage): \Amp\Future
{
    return async(function () use ($httpClient, $config, &$tokenStorage) {
        // For demo purposes, we'll simulate the OAuth flow
        // In a real application, you would:
        // 1. Redirect user to authorization URL
        // 2. Handle callback with authorization code
        // 3. Exchange code for access token

        echo "🔄 Simulating OAuth flow (in production, this would redirect to browser)...\n";

        // Generate state for CSRF protection
        $state = bin2hex(random_bytes(16));

        // Build authorization URL
        $authParams = [
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'scope' => implode(' ', $config['scopes']),
            'state' => $state
        ];

        $authUrl = $config['authorization_url'] . '?' . http_build_query($authParams);
        echo "📍 Authorization URL: $authUrl\n";

        // For demo, simulate getting authorization code
        echo "⏳ Simulating user authorization...\n";
        \Amp\delay(1000)->await(); // Simulate delay

        $authCode = 'demo_auth_code_' . bin2hex(random_bytes(8));
        echo "✅ Received authorization code: $authCode\n";

        // Exchange authorization code for access token
        echo "🔄 Exchanging authorization code for access token...\n";

        try {
            // In demo mode, simulate token response since we don't have a real OAuth server
            $tokenResponse = [
                'access_token' => 'demo_' . bin2hex(random_bytes(16)),
                'refresh_token' => 'refresh_' . bin2hex(random_bytes(16)),
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => implode(' ', $config['scopes'])
            ];

            // In a real implementation, you would make this request:
            /*
        $response = $httpClient->post($config['token_url'], [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $authCode,
                'redirect_uri' => $config['redirect_uri']
            ]
        ]);
        
        $tokenResponse = json_decode($response->getBody(), true);
        */

            // Update token storage
            $tokenStorage['access_token'] = $tokenResponse['access_token'];
            $tokenStorage['refresh_token'] = $tokenResponse['refresh_token'] ?? null;
            $tokenStorage['token_type'] = $tokenResponse['token_type'] ?? 'Bearer';
            $tokenStorage['expires_at'] = time() + ($tokenResponse['expires_in'] ?? 3600);
            $tokenStorage['scope'] = $tokenResponse['scope'] ?? implode(' ', $config['scopes']);

            echo "✅ Access token obtained successfully!\n";
            echo "🕒 Token expires at: " . date('Y-m-d H:i:s', $tokenStorage['expires_at']) . "\n";
        } catch (\Exception $e) {
            throw new \Exception("Failed to exchange authorization code: " . $e->getMessage());
        }
    });
}

/**
 * Refresh access token using refresh token
 */
function refreshAccessToken(HttpClient $httpClient, array $config, array &$tokenStorage): \Amp\Future
{
    return async(function () use ($httpClient, $config, &$tokenStorage) {
        if (!$tokenStorage['refresh_token']) {
            throw new \Exception("No refresh token available");
        }

        try {
            // For demo, simulate refresh token response
            $tokenResponse = [
                'access_token' => 'refreshed_' . bin2hex(random_bytes(16)),
                'refresh_token' => $tokenStorage['refresh_token'], // Usually stays the same
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => $tokenStorage['scope'] ?? implode(' ', $config['scopes'])
            ];

            // In a real implementation:
            /*
        $response = $httpClient->post($config['token_url'], [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'refresh_token' => $tokenStorage['refresh_token']
            ]
        ]);
        
        $tokenResponse = json_decode($response->getBody(), true);
        */

            // Update token storage
            $tokenStorage['access_token'] = $tokenResponse['access_token'];
            if (isset($tokenResponse['refresh_token'])) {
                $tokenStorage['refresh_token'] = $tokenResponse['refresh_token'];
            }
            $tokenStorage['expires_at'] = time() + ($tokenResponse['expires_in'] ?? 3600);

            echo "✅ Access token refreshed successfully!\n";
        } catch (\Exception $e) {
            throw new \Exception("Failed to refresh access token: " . $e->getMessage());
        }
    });
}

/**
 * Create MCP client with OAuth authentication
 */
function createAuthenticatedClient(array $tokenStorage): Client
{
    // Create client with OAuth capabilities
    $client = new Client(
        new Implementation('oauth-client', '1.0.0', 'OAuth Authenticated MCP Client'),
        new ClientOptions(
            capabilities: new ClientCapabilities()
        )
    );

    // Set up authentication header injection
    $client->setRequestInterceptor(function ($request) use ($tokenStorage) {
        // Add Authorization header to all requests
        if ($tokenStorage['access_token']) {
            $request['headers'] = $request['headers'] ?? [];
            $request['headers']['Authorization'] = $tokenStorage['token_type'] . ' ' . $tokenStorage['access_token'];
        }
        return $request;
    });

    return $client;
}

/**
 * Demonstrate authenticated operations
 */
function demonstrateAuthenticatedOperations(Client $client, array $tokenStorage): \Amp\Future
{
    return async(function () use ($client, $tokenStorage) {
        echo "🔐 Demonstrating Authenticated Operations\n";
        echo "========================================\n";

        // List available tools (should include protected tools)
        echo "📋 Listing available tools...\n";
        try {
            $tools = $client->listTools()->await();
            foreach ($tools->getTools() as $tool) {
                echo "  - {$tool->getName()}: {$tool->getDescription()}\n";
            }
            echo "\n";
        } catch (\Exception $e) {
            echo "❌ Failed to list tools: " . $e->getMessage() . "\n\n";
        }

        // List resources (including protected ones)
        echo "📁 Listing available resources...\n";
        try {
            $resources = $client->listResources()->await();
            foreach ($resources->getResources() as $resource) {
                echo "  - {$resource->getName()}: {$resource->getUri()}\n";
            }
            echo "\n";
        } catch (\Exception $e) {
            echo "❌ Failed to list resources: " . $e->getMessage() . "\n\n";
        }

        // Read user profile (requires authentication)
        echo "👤 Reading user profile...\n";
        try {
            $profile = $client->readResourceByUri('user://profile')->await();
            foreach ($profile->getContents() as $content) {
                if ($content->getType() === 'text') {
                    echo "Profile data:\n" . $content->getText() . "\n\n";
                }
            }
        } catch (\Exception $e) {
            echo "❌ Failed to read profile: " . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Test token validation
 */
function testTokenValidation(Client $client, array $tokenStorage): \Amp\Future
{
    return async(function () use ($client, $tokenStorage) {
        echo "🔍 Testing Token Validation\n";
        echo "===========================\n";

        try {
            $result = $client->callToolByName('validate-token', [
                'token' => $tokenStorage['access_token']
            ])->await();

            if ($result->isError()) {
                echo "❌ Token validation failed: " . getResultText($result) . "\n";
            } else {
                echo "✅ Token validation successful:\n";
                echo getResultText($result) . "\n";
            }
            echo "\n";
        } catch (\Exception $e) {
            echo "❌ Token validation error: " . $e->getMessage() . "\n\n";
        }
    });
}

/**
 * Test scope-based access control
 */
function testScopedAccess(Client $client, array $tokenStorage): \Amp\Future
{
    return async(function () use ($client, $tokenStorage) {
        echo "🎯 Testing Scope-Based Access Control\n";
        echo "====================================\n";

        $scopeTests = [
            [
                'tool' => 'list-users',
                'params' => ['include_profiles' => true],
                'required_scope' => 'read',
                'description' => 'List users (requires read scope)'
            ],
            [
                'tool' => 'update-profile',
                'params' => ['field' => 'role', 'value' => 'Senior Developer'],
                'required_scope' => 'write',
                'description' => 'Update profile (requires write scope)'
            ],
            [
                'tool' => 'admin-stats',
                'params' => [],
                'required_scope' => 'admin',
                'description' => 'Get admin statistics (requires admin scope)'
            ]
        ];

        $userScopes = explode(' ', $tokenStorage['scope'] ?? '');
        echo "🏷️  User scopes: " . implode(', ', $userScopes) . "\n\n";

        foreach ($scopeTests as $test) {
            echo "🧪 Testing: {$test['description']}\n";
            echo "   Required scope: {$test['required_scope']}\n";
            echo "   User has scope: " . (in_array($test['required_scope'], $userScopes) ? '✅ Yes' : '❌ No') . "\n";

            try {
                $result = $client->callToolByName($test['tool'], $test['params'])->await();

                if ($result->isError()) {
                    echo "   Result: ❌ " . getResultText($result) . "\n";
                } else {
                    echo "   Result: ✅ Access granted\n";
                    // Show first line of result
                    $resultText = getResultText($result);
                    $firstLine = explode("\n", $resultText)[0] ?? '';
                    if (strlen($firstLine) > 80) {
                        $firstLine = substr($firstLine, 0, 77) . '...';
                    }
                    echo "   Data: $firstLine\n";
                }
            } catch (\Exception $e) {
                echo "   Result: ❌ Exception: " . $e->getMessage() . "\n";
            }

            echo "\n";
        }
    });
}

/**
 * Extract text content from a result
 */
function getResultText($result): string
{
    if (!$result || !method_exists($result, 'getContent')) {
        return 'No result';
    }

    $content = $result->getContent();
    if (empty($content)) {
        return 'Empty result';
    }

    $texts = [];
    foreach ($content as $item) {
        if (method_exists($item, 'getText')) {
            $texts[] = $item->getText();
        } elseif (is_array($item) && isset($item['text'])) {
            $texts[] = $item['text'];
        }
    }

    return implode(' ', $texts);
}

/**
 * Clean up function to revoke tokens on exit
 */
function cleanupTokens(array $config, array $tokenStorage): void
{
    if ($tokenStorage['access_token']) {
        echo "🧹 Cleaning up: Revoking access token...\n";

        // In a real implementation, you would revoke the token:
        /*
        try {
            $httpClient = new HttpClient();
            $httpClient->post($config['token_url'] . '/revoke', [
                'form_params' => [
                    'token' => $tokenStorage['access_token'],
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret']
                ]
            ]);
            echo "✅ Token revoked successfully\n";
        } catch (\Exception $e) {
            echo "⚠️  Failed to revoke token: " . $e->getMessage() . "\n";
        }
        */

        // Remove stored tokens
        $tokenFile = __DIR__ . '/oauth_tokens.json';
        if (file_exists($tokenFile)) {
            unlink($tokenFile);
            echo "🗑️  Removed stored tokens\n";
        }
    }
}

// Register cleanup function
register_shutdown_function(function () use ($oauthConfig, $tokenStorage) {
    if (isset($GLOBALS['cleanup_tokens']) && $GLOBALS['cleanup_tokens']) {
        cleanupTokens($oauthConfig, $tokenStorage);
    }
});

// Enable cleanup on normal exit
$GLOBALS['cleanup_tokens'] = true;
