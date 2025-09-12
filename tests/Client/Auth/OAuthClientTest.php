<?php

declare(strict_types=1);

namespace Tests\Client\Auth;

use MCP\Client\Auth\OAuthClient;
use MCP\Client\Auth\InMemoryTokenStorage;
use MCP\Shared\OAuthClientInformation;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class OAuthClientTest extends TestCase
{
    private OAuthClient $oauthClient;

    protected function setUp(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $tokenStorage = new InMemoryTokenStorage();

        $this->oauthClient = new OAuthClient(
            $httpClient,
            $requestFactory,
            $streamFactory,
            $tokenStorage
        );
    }

    public function testGenerateCodeVerifier(): void
    {
        $verifier = $this->oauthClient->generateCodeVerifier();

        $this->assertIsString($verifier);
        $this->assertGreaterThan(32, strlen($verifier));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $verifier);
    }

    public function testGenerateCodeChallenge(): void
    {
        $verifier = 'test_verifier_123';
        $challenge = $this->oauthClient->generateCodeChallenge($verifier);

        $this->assertIsString($challenge);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $challenge);

        // Verify it's reproducible
        $challenge2 = $this->oauthClient->generateCodeChallenge($verifier);
        $this->assertEquals($challenge, $challenge2);
    }

    public function testBuildAuthorizationUrl(): void
    {
        $client = new OAuthClientInformation('client_123', 'secret');
        $url = $this->oauthClient->buildAuthorizationUrl(
            'https://auth.example.com/oauth2/authorize',
            $client,
            'https://app.example.com/callback',
            'challenge_123',
            'state_456',
            ['read', 'write'],
            'https://api.example.com'
        );

        $this->assertStringStartsWith('https://auth.example.com/oauth2/authorize?', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('client_id=client_123', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fapp.example.com%2Fcallback', $url);
        $this->assertStringContainsString('code_challenge=challenge_123', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('state=state_456', $url);
        $this->assertStringContainsString('scope=read+write', $url);
        $this->assertStringContainsString('resource=https%3A%2F%2Fapi.example.com', $url);
    }

    public function testBuildAuthorizationUrlMinimal(): void
    {
        $client = new OAuthClientInformation('client_123');
        $url = $this->oauthClient->buildAuthorizationUrl(
            'https://auth.example.com/oauth2/authorize',
            $client,
            'https://app.example.com/callback',
            'challenge_123'
        );

        $this->assertStringStartsWith('https://auth.example.com/oauth2/authorize?', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('client_id=client_123', $url);
        $this->assertStringContainsString('code_challenge=challenge_123', $url);
        $this->assertStringNotContainsString('state=', $url);
        $this->assertStringNotContainsString('scope=', $url);
        $this->assertStringNotContainsString('resource=', $url);
    }

    public function testPkceFlow(): void
    {
        // Test the full PKCE flow
        $verifier = $this->oauthClient->generateCodeVerifier();
        $challenge = $this->oauthClient->generateCodeChallenge($verifier);

        // Verify challenge is different from verifier
        $this->assertNotEquals($verifier, $challenge);

        // Verify challenge is URL-safe base64
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $challenge);

        // Verify length constraints (RFC 7636)
        $this->assertGreaterThanOrEqual(43, strlen($verifier));
        $this->assertLessThanOrEqual(128, strlen($verifier));
        $this->assertEquals(43, strlen($challenge)); // SHA256 hash base64url encoded
    }
}
