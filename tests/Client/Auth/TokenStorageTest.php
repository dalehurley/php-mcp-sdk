<?php

declare(strict_types=1);

namespace Tests\Client\Auth;

use MCP\Client\Auth\InMemoryTokenStorage;
use MCP\Client\Auth\FileTokenStorage;
use MCP\Shared\OAuthTokens;
use PHPUnit\Framework\TestCase;

class TokenStorageTest extends TestCase
{
    public function testInMemoryTokenStorage(): void
    {
        $storage = new InMemoryTokenStorage();
        $clientId = 'client_123';
        $tokens = new OAuthTokens('access_token', 'Bearer', null, 3600, 'read write', 'refresh_token');

        // Initially no tokens
        $this->assertNull($storage->getTokens($clientId));

        // Store tokens
        $storage->storeTokens($clientId, $tokens);

        // Retrieve tokens
        $retrieved = $storage->getTokens($clientId);
        $this->assertNotNull($retrieved);
        $this->assertEquals('access_token', $retrieved->getAccessToken());
        $this->assertEquals('Bearer', $retrieved->getTokenType());
        $this->assertEquals(3600, $retrieved->getExpiresIn());
        $this->assertEquals('read write', $retrieved->getScope());
        $this->assertEquals('refresh_token', $retrieved->getRefreshToken());

        // Clear tokens
        $storage->clearTokens($clientId);
        $this->assertNull($storage->getTokens($clientId));
    }

    public function testInMemoryTokenStorageClearAll(): void
    {
        $storage = new InMemoryTokenStorage();
        $tokens = new OAuthTokens('access_token', 'Bearer');

        $storage->storeTokens('client1', $tokens);
        $storage->storeTokens('client2', $tokens);

        $this->assertNotNull($storage->getTokens('client1'));
        $this->assertNotNull($storage->getTokens('client2'));

        $storage->clearAllTokens();

        $this->assertNull($storage->getTokens('client1'));
        $this->assertNull($storage->getTokens('client2'));
    }

    public function testFileTokenStorage(): void
    {
        $tempDir = sys_get_temp_dir() . '/mcp_oauth_test_' . uniqid();
        $storage = new FileTokenStorage($tempDir);
        $clientId = 'client_123';
        $tokens = new OAuthTokens('access_token', 'Bearer', null, 3600, 'read write', 'refresh_token');

        try {
            // Initially no tokens
            $this->assertNull($storage->getTokens($clientId));

            // Store tokens
            $storage->storeTokens($clientId, $tokens);

            // Retrieve tokens
            $retrieved = $storage->getTokens($clientId);
            $this->assertNotNull($retrieved);
            $this->assertEquals('access_token', $retrieved->getAccessToken());
            $this->assertEquals('Bearer', $retrieved->getTokenType());
            $this->assertEquals(3600, $retrieved->getExpiresIn());
            $this->assertEquals('read write', $retrieved->getScope());
            $this->assertEquals('refresh_token', $retrieved->getRefreshToken());

            // Clear tokens
            $storage->clearTokens($clientId);
            $this->assertNull($storage->getTokens($clientId));
        } finally {
            // Clean up temp directory
            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/*');
                if ($files) {
                    foreach ($files as $file) {
                        unlink($file);
                    }
                }
                rmdir($tempDir);
            }
        }
    }

    public function testFileTokenStorageInvalidJson(): void
    {
        $tempDir = sys_get_temp_dir() . '/mcp_oauth_test_' . uniqid();
        $storage = new FileTokenStorage($tempDir);
        $clientId = 'client_123';

        try {
            // Create a file with invalid JSON
            $filename = $tempDir . '/tokens_client_123.json';
            file_put_contents($filename, 'invalid json');

            // Should return null for invalid JSON
            $this->assertNull($storage->getTokens($clientId));
        } finally {
            // Clean up
            if (file_exists($filename)) {
                unlink($filename);
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }
}
