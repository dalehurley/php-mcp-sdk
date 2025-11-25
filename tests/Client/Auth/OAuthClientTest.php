<?php

declare(strict_types=1);

namespace Tests\Client\Auth;

use MCP\Client\Auth\InMemoryTokenStorage;
use MCP\Client\Auth\OAuthClient;
use MCP\Shared\OAuthClientInformation;
use MCP\Shared\OAuthTokens;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class OAuthClientTest extends TestCase
{
    private OAuthClient $oauthClient;

    private InMemoryTokenStorage $tokenStorage;

    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->tokenStorage = new InMemoryTokenStorage();

        $this->oauthClient = new OAuthClient(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            $this->tokenStorage
        );
    }

    public function testGenerateCodeVerifier(): void
    {
        $verifier = $this->oauthClient->generateCodeVerifier();

        $this->assertIsString($verifier);
        $this->assertGreaterThan(32, strlen($verifier));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $verifier);
    }

    public function testGenerateCodeVerifierUniqueness(): void
    {
        $verifiers = [];
        for ($i = 0; $i < 10; $i++) {
            $verifiers[] = $this->oauthClient->generateCodeVerifier();
        }

        // All verifiers should be unique
        $this->assertCount(10, array_unique($verifiers));
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

    public function testGenerateCodeChallengeDifferentVerifiers(): void
    {
        $challenge1 = $this->oauthClient->generateCodeChallenge('verifier_1');
        $challenge2 = $this->oauthClient->generateCodeChallenge('verifier_2');

        $this->assertNotEquals($challenge1, $challenge2);
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

    public function testBuildAuthorizationUrlWithSingleScope(): void
    {
        $client = new OAuthClientInformation('client_123');
        $url = $this->oauthClient->buildAuthorizationUrl(
            'https://auth.example.com/oauth2/authorize',
            $client,
            'https://app.example.com/callback',
            'challenge_123',
            null,
            ['openid']
        );

        $this->assertStringContainsString('scope=openid', $url);
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

    public function testExchangeAuthorizationCode(): void
    {
        // Setup mock request
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('withHeader')->willReturnSelf();
        $mockRequest->method('withBody')->willReturnSelf();

        $this->requestFactory->method('createRequest')
            ->with('POST', 'https://auth.example.com/oauth2/token')
            ->willReturn($mockRequest);

        // Setup mock stream
        $mockStream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStream')->willReturn($mockStream);

        // Setup mock response
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($this->createStreamWithContent(json_encode([
            'access_token' => 'access_token_123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'refresh_token_456',
            'scope' => 'read write',
        ])));

        $this->httpClient->method('sendRequest')->willReturn($mockResponse);

        $client = new OAuthClientInformation('client_123', 'secret');
        $tokens = $this->oauthClient->exchangeAuthorizationCode(
            'https://auth.example.com/oauth2/token',
            $client,
            'auth_code_789',
            'code_verifier_xyz',
            'https://app.example.com/callback'
        )->await();

        $this->assertInstanceOf(OAuthTokens::class, $tokens);
        $this->assertEquals('access_token_123', $tokens->getAccessToken());
        $this->assertEquals('Bearer', $tokens->getTokenType());
        $this->assertEquals(3600, $tokens->getExpiresIn());
        $this->assertEquals('refresh_token_456', $tokens->getRefreshToken());
        $this->assertEquals('read write', $tokens->getScope());

        // Verify tokens were stored
        $storedTokens = $this->tokenStorage->getTokens('client_123');
        $this->assertNotNull($storedTokens);
        $this->assertEquals('access_token_123', $storedTokens->getAccessToken());
    }

    public function testRefreshToken(): void
    {
        // Setup mock request
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('withHeader')->willReturnSelf();
        $mockRequest->method('withBody')->willReturnSelf();

        $this->requestFactory->method('createRequest')
            ->with('POST', 'https://auth.example.com/oauth2/token')
            ->willReturn($mockRequest);

        // Setup mock stream
        $mockStream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStream')->willReturn($mockStream);

        // Setup mock response
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($this->createStreamWithContent(json_encode([
            'access_token' => 'new_access_token',
            'token_type' => 'Bearer',
            'expires_in' => 7200,
        ])));

        $this->httpClient->method('sendRequest')->willReturn($mockResponse);

        $client = new OAuthClientInformation('client_123');
        $tokens = $this->oauthClient->refreshToken(
            'https://auth.example.com/oauth2/token',
            $client,
            'refresh_token_456'
        )->await();

        $this->assertInstanceOf(OAuthTokens::class, $tokens);
        $this->assertEquals('new_access_token', $tokens->getAccessToken());
        $this->assertEquals(7200, $tokens->getExpiresIn());
        // Original refresh token should be preserved if not returned
        $this->assertEquals('refresh_token_456', $tokens->getRefreshToken());
    }

    public function testRefreshTokenWithScopes(): void
    {
        // Setup mock request
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('withHeader')->willReturnSelf();
        $mockRequest->method('withBody')->willReturnSelf();

        $this->requestFactory->method('createRequest')->willReturn($mockRequest);
        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        // Setup mock response
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($this->createStreamWithContent(json_encode([
            'access_token' => 'new_access_token',
            'token_type' => 'Bearer',
            'scope' => 'read',
        ])));

        $this->httpClient->method('sendRequest')->willReturn($mockResponse);

        $client = new OAuthClientInformation('client_123');
        $tokens = $this->oauthClient->refreshToken(
            'https://auth.example.com/oauth2/token',
            $client,
            'refresh_token_456',
            ['read']
        )->await();

        $this->assertEquals('read', $tokens->getScope());
    }

    public function testRevokeToken(): void
    {
        // First store some tokens
        $this->tokenStorage->storeTokens('client_123', new OAuthTokens(
            'access_token_123',
            'Bearer',
            null,
            3600,
            null,
            'refresh_token_456'
        ));

        // Setup mock request
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('withHeader')->willReturnSelf();
        $mockRequest->method('withBody')->willReturnSelf();

        $this->requestFactory->method('createRequest')->willReturn($mockRequest);
        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        // Setup mock response
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $this->httpClient->method('sendRequest')->willReturn($mockResponse);

        $client = new OAuthClientInformation('client_123');
        $this->oauthClient->revokeToken(
            'https://auth.example.com/oauth2/revoke',
            $client,
            'access_token_123'
        )->await();

        // Verify tokens were cleared from storage
        $this->assertNull($this->tokenStorage->getTokens('client_123'));
    }

    public function testGetStoredTokens(): void
    {
        $this->assertNull($this->oauthClient->getStoredTokens('nonexistent'));

        $tokens = new OAuthTokens('access_token', 'Bearer');
        $this->tokenStorage->storeTokens('client_123', $tokens);

        $retrieved = $this->oauthClient->getStoredTokens('client_123');
        $this->assertNotNull($retrieved);
        $this->assertEquals('access_token', $retrieved->getAccessToken());
    }

    public function testIsAuthorizedNoTokens(): void
    {
        $this->assertFalse($this->oauthClient->isAuthorized('client_123'));
    }

    public function testIsAuthorizedWithValidTokens(): void
    {
        $tokens = new OAuthTokens('access_token', 'Bearer', null, 3600);
        $this->tokenStorage->storeTokens('client_123', $tokens);

        $this->assertTrue($this->oauthClient->isAuthorized('client_123'));
    }

    public function testIsAuthorizedWithExpiredTokens(): void
    {
        $tokens = new OAuthTokens('access_token', 'Bearer', null, 0);
        $this->tokenStorage->storeTokens('client_123', $tokens);

        $this->assertFalse($this->oauthClient->isAuthorized('client_123'));
    }

    public function testIsAuthorizedWithNullExpiresIn(): void
    {
        // Token without expiration should be considered valid
        $tokens = new OAuthTokens('access_token', 'Bearer', null, null);
        $this->tokenStorage->storeTokens('client_123', $tokens);

        $this->assertTrue($this->oauthClient->isAuthorized('client_123'));
    }

    public function testEnsureValidTokenWithNoTokens(): void
    {
        $client = new OAuthClientInformation('client_123');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No tokens found for client');

        $this->oauthClient->ensureValidToken(
            'https://auth.example.com/oauth2/token',
            $client
        )->await();
    }

    public function testEnsureValidTokenReturnsValidToken(): void
    {
        // Store a valid token (not expired)
        $tokens = new OAuthTokens('access_token', 'Bearer', null, 3600);
        $this->tokenStorage->storeTokens('client_123', $tokens);

        $client = new OAuthClientInformation('client_123');
        $result = $this->oauthClient->ensureValidToken(
            'https://auth.example.com/oauth2/token',
            $client
        )->await();

        $this->assertEquals('access_token', $result->getAccessToken());
    }

    public function testEnsureValidTokenRefreshesExpiredToken(): void
    {
        // Store an almost-expired token
        $tokens = new OAuthTokens('old_access_token', 'Bearer', null, 10, null, 'refresh_token');
        $this->tokenStorage->storeTokens('client_123', $tokens);

        // Setup mock for refresh
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('withHeader')->willReturnSelf();
        $mockRequest->method('withBody')->willReturnSelf();

        $this->requestFactory->method('createRequest')->willReturn($mockRequest);
        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($this->createStreamWithContent(json_encode([
            'access_token' => 'new_access_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ])));

        $this->httpClient->method('sendRequest')->willReturn($mockResponse);

        $client = new OAuthClientInformation('client_123');
        $result = $this->oauthClient->ensureValidToken(
            'https://auth.example.com/oauth2/token',
            $client
        )->await();

        $this->assertEquals('new_access_token', $result->getAccessToken());
    }

    public function testEnsureValidTokenFailsWithoutRefreshToken(): void
    {
        // Store an expired token without refresh token
        $tokens = new OAuthTokens('old_access_token', 'Bearer', null, 10);
        $this->tokenStorage->storeTokens('client_123', $tokens);

        $client = new OAuthClientInformation('client_123');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token expired and no refresh token available');

        $this->oauthClient->ensureValidToken(
            'https://auth.example.com/oauth2/token',
            $client
        )->await();
    }

    public function testExchangeAuthorizationCodeWithResource(): void
    {
        // Setup mock request
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('withHeader')->willReturnSelf();
        $mockRequest->method('withBody')->willReturnSelf();

        $this->requestFactory->method('createRequest')->willReturn($mockRequest);
        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($this->createStreamWithContent(json_encode([
            'access_token' => 'access_token_123',
            'token_type' => 'Bearer',
        ])));

        $this->httpClient->method('sendRequest')->willReturn($mockResponse);

        $client = new OAuthClientInformation('client_123');
        $tokens = $this->oauthClient->exchangeAuthorizationCode(
            'https://auth.example.com/oauth2/token',
            $client,
            'auth_code_789',
            'code_verifier_xyz',
            'https://app.example.com/callback',
            'https://api.example.com'
        )->await();

        $this->assertEquals('access_token_123', $tokens->getAccessToken());
    }

    public function testRevokeTokenWithTypeHint(): void
    {
        // Setup mock request
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('withHeader')->willReturnSelf();
        $mockRequest->method('withBody')->willReturnSelf();

        $this->requestFactory->method('createRequest')->willReturn($mockRequest);
        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $this->httpClient->method('sendRequest')->willReturn($mockResponse);

        $client = new OAuthClientInformation('client_123');
        // Should not throw
        $this->oauthClient->revokeToken(
            'https://auth.example.com/oauth2/revoke',
            $client,
            'access_token_123',
            'access_token'
        )->await();

        $this->assertTrue(true);
    }

    /**
     * Helper to create a stream with content
     */
    private function createStreamWithContent(string $content): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($content);

        return $stream;
    }
}
