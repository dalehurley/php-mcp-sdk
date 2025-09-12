<?php

declare(strict_types=1);

namespace Tests\Server\Auth;

use MCP\Server\Auth\DefaultAuthInfo;
use PHPUnit\Framework\TestCase;

class AuthTypesTest extends TestCase
{
    public function testDefaultAuthInfoCreation(): void
    {
        $authInfo = new DefaultAuthInfo(
            token: 'access_token_123',
            clientId: 'client_123',
            scopes: ['read', 'write'],
            expiresAt: time() + 3600,
            resource: 'https://api.example.com',
            extra: ['custom' => 'data']
        );

        $this->assertEquals('access_token_123', $authInfo->getToken());
        $this->assertEquals('client_123', $authInfo->getClientId());
        $this->assertEquals(['read', 'write'], $authInfo->getScopes());
        $this->assertNotNull($authInfo->getExpiresAt());
        $this->assertEquals('https://api.example.com', $authInfo->getResource());
        $this->assertEquals(['custom' => 'data'], $authInfo->getExtra());
    }

    public function testDefaultAuthInfoWithMinimalData(): void
    {
        $authInfo = new DefaultAuthInfo(
            token: 'token',
            clientId: 'client',
            scopes: []
        );

        $this->assertEquals('token', $authInfo->getToken());
        $this->assertEquals('client', $authInfo->getClientId());
        $this->assertEquals([], $authInfo->getScopes());
        $this->assertNull($authInfo->getExpiresAt());
        $this->assertNull($authInfo->getResource());
        $this->assertEquals([], $authInfo->getExtra());
    }
}
