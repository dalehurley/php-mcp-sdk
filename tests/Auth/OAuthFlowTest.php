<?php

declare(strict_types=1);

namespace MCP\Tests\Auth;

use MCP\Client\Auth\InMemoryTokenStorage;
use MCP\Client\Auth\OAuthClient;
use PHPUnit\Framework\TestCase;

class OAuthFlowTest extends TestCase
{
    public function testOAuthClientExists(): void
    {
        $this->assertTrue(class_exists(OAuthClient::class));
    }

    public function testInMemoryTokenStorageExists(): void
    {
        $this->assertTrue(class_exists(InMemoryTokenStorage::class));
    }

    public function testInMemoryTokenStorage(): void
    {
        $storage = new InMemoryTokenStorage();

        // Test storing a token
        $tokens = new \MCP\Shared\OAuthTokens(
            accessToken: 'test-access-token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            refreshToken: 'test-refresh-token'
        );

        $storage->storeTokens('test-client', $tokens);

        // Test retrieving the token
        $retrievedTokens = $storage->getTokens('test-client');

        $this->assertEquals($tokens, $retrievedTokens);
    }

    public function testTokenStorageNonExistent(): void
    {
        $storage = new InMemoryTokenStorage();

        $tokens = $storage->getTokens('non-existent-client');

        $this->assertNull($tokens);
    }

    public function testTokenStorageUpdate(): void
    {
        $storage = new InMemoryTokenStorage();

        $tokens1 = new \MCP\Shared\OAuthTokens(
            accessToken: 'token-1',
            tokenType: 'Bearer',
            expiresIn: 3600
        );

        $tokens2 = new \MCP\Shared\OAuthTokens(
            accessToken: 'token-2',
            tokenType: 'Bearer',
            expiresIn: 7200
        );

        $storage->storeTokens('client', $tokens1);
        $storage->storeTokens('client', $tokens2); // Update

        $retrievedTokens = $storage->getTokens('client');

        $this->assertEquals($tokens2, $retrievedTokens);
    }

    public function testTokenStorageClear(): void
    {
        $storage = new InMemoryTokenStorage();

        $tokens = new \MCP\Shared\OAuthTokens(
            accessToken: 'test-token',
            tokenType: 'Bearer'
        );

        $storage->storeTokens('client', $tokens);
        $this->assertNotNull($storage->getTokens('client'));

        $storage->clearTokens('client');
        $this->assertNull($storage->getTokens('client'));
    }

    public function testClearAllTokens(): void
    {
        $storage = new InMemoryTokenStorage();

        $tokens1 = new \MCP\Shared\OAuthTokens(
            accessToken: 'token-1',
            tokenType: 'Bearer'
        );

        $tokens2 = new \MCP\Shared\OAuthTokens(
            accessToken: 'token-2',
            tokenType: 'Bearer'
        );

        $storage->storeTokens('client1', $tokens1);
        $storage->storeTokens('client2', $tokens2);

        $this->assertNotNull($storage->getTokens('client1'));
        $this->assertNotNull($storage->getTokens('client2'));

        $storage->clearAllTokens();

        $this->assertNull($storage->getTokens('client1'));
        $this->assertNull($storage->getTokens('client2'));
    }
}
