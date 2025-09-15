<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use GuzzleHttp\Psr7\Uri;
use MCP\Shared\AuthUtils;
use PHPUnit\Framework\TestCase;

class AuthUtilsTest extends TestCase
{
    public function testResourceUrlFromServerUrl(): void
    {
        // Test with string URL
        $url = 'https://example.com/path?query=1#fragment';
        $resourceUrl = AuthUtils::resourceUrlFromServerUrl($url);
        $this->assertEquals('https://example.com/path?query=1', (string) $resourceUrl);
        $this->assertEquals('', $resourceUrl->getFragment());

        // Test with Uri object
        $uri = new Uri('https://example.com/api#section');
        $resourceUrl = AuthUtils::resourceUrlFromServerUrl($uri);
        $this->assertEquals('https://example.com/api', (string) $resourceUrl);
        $this->assertEquals('', $resourceUrl->getFragment());
    }

    public function testCheckResourceAllowed(): void
    {
        // Test exact match
        $params = [
            'requestedResource' => 'https://api.example.com/v1',
            'configuredResource' => 'https://api.example.com/v1',
        ];
        $this->assertTrue(AuthUtils::checkResourceAllowed($params));

        // Test subpath match
        $params = [
            'requestedResource' => 'https://api.example.com/v1/users',
            'configuredResource' => 'https://api.example.com/v1',
        ];
        $this->assertTrue(AuthUtils::checkResourceAllowed($params));

        // Test different domain
        $params = [
            'requestedResource' => 'https://other.example.com/v1',
            'configuredResource' => 'https://api.example.com/v1',
        ];
        $this->assertFalse(AuthUtils::checkResourceAllowed($params));

        // Test different scheme
        $params = [
            'requestedResource' => 'http://api.example.com/v1',
            'configuredResource' => 'https://api.example.com/v1',
        ];
        $this->assertFalse(AuthUtils::checkResourceAllowed($params));

        // Test shorter requested path
        $params = [
            'requestedResource' => 'https://api.example.com/v',
            'configuredResource' => 'https://api.example.com/v1',
        ];
        $this->assertFalse(AuthUtils::checkResourceAllowed($params));

        // Test with Uri objects
        $params = [
            'requestedResource' => new Uri('https://api.example.com/v1/users'),
            'configuredResource' => new Uri('https://api.example.com/v1'),
        ];
        $this->assertTrue(AuthUtils::checkResourceAllowed($params));
    }

    public function testCheckResourceAllowedWithTrailingSlash(): void
    {
        // Test that trailing slashes are handled correctly
        $params = [
            'requestedResource' => 'https://api.example.com/api',
            'configuredResource' => 'https://api.example.com/api/',
        ];
        $this->assertTrue(AuthUtils::checkResourceAllowed($params)); // With trailing slash normalization, /api/ matches /api/

        $params = [
            'requestedResource' => 'https://api.example.com/api/',
            'configuredResource' => 'https://api.example.com/api',
        ];
        $this->assertTrue(AuthUtils::checkResourceAllowed($params));

        // Test that /api123 doesn't match /api
        $params = [
            'requestedResource' => 'https://api.example.com/api123',
            'configuredResource' => 'https://api.example.com/api',
        ];
        $this->assertFalse(AuthUtils::checkResourceAllowed($params)); // /api123/ does NOT start with /api/
    }

    public function testGenerateRandomString(): void
    {
        $str1 = AuthUtils::generateRandomString(32);
        $str2 = AuthUtils::generateRandomString(32);

        // Check length (base64url encoding of 32 bytes = 43 chars)
        $this->assertEquals(43, strlen($str1));
        $this->assertEquals(43, strlen($str2));

        // Check uniqueness
        $this->assertNotEquals($str1, $str2);

        // Check base64url format (no +, /, or = characters)
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $str1);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $str2);
    }

    public function testGenerateCodeVerifier(): void
    {
        $verifier1 = AuthUtils::generateCodeVerifier();
        $verifier2 = AuthUtils::generateCodeVerifier();

        // Check length (43 characters for 32 bytes base64url encoded)
        $this->assertEquals(43, strlen($verifier1));
        $this->assertEquals(43, strlen($verifier2));

        // Check uniqueness
        $this->assertNotEquals($verifier1, $verifier2);

        // Check format
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $verifier1);
    }

    public function testGenerateCodeChallenge(): void
    {
        $verifier = 'test-verifier-string';
        $challenge = AuthUtils::generateCodeChallenge($verifier);

        // Check that it's base64url encoded
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $challenge);

        // Check that it's deterministic
        $challenge2 = AuthUtils::generateCodeChallenge($verifier);
        $this->assertEquals($challenge, $challenge2);

        // Check with different verifier
        $challenge3 = AuthUtils::generateCodeChallenge('different-verifier');
        $this->assertNotEquals($challenge, $challenge3);
    }

    public function testGenerateState(): void
    {
        $state1 = AuthUtils::generateState();
        $state2 = AuthUtils::generateState();

        // Check length (16 bytes = 22 chars base64url encoded)
        $this->assertEquals(22, strlen($state1));
        $this->assertEquals(22, strlen($state2));

        // Check uniqueness
        $this->assertNotEquals($state1, $state2);

        // Check format
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $state1);
    }

    public function testParseJwtWithoutVerification(): void
    {
        // Create a test JWT (header.payload.signature)
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'sub' => '1234567890',
            'name' => 'John Doe',
            'iat' => 1516239022,
            'exp' => time() + 3600,
        ]));
        $signature = 'fake-signature';
        $token = "$header.$payload.$signature";

        $parsed = AuthUtils::parseJwtWithoutVerification($token);

        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('header', $parsed);
        $this->assertArrayHasKey('payload', $parsed);
        $this->assertEquals('HS256', $parsed['header']['alg']);
        $this->assertEquals('JWT', $parsed['header']['typ']);
        $this->assertEquals('1234567890', $parsed['payload']['sub']);
        $this->assertEquals('John Doe', $parsed['payload']['name']);
    }

    public function testParseJwtWithoutVerificationInvalidToken(): void
    {
        // Test with invalid format
        $this->assertNull(AuthUtils::parseJwtWithoutVerification('invalid-token'));
        $this->assertNull(AuthUtils::parseJwtWithoutVerification('only.two'));
        $this->assertNull(AuthUtils::parseJwtWithoutVerification(''));

        // Test with invalid base64
        $this->assertNull(AuthUtils::parseJwtWithoutVerification('invalid!.base64!.data!'));
    }

    public function testExtractJwtClaims(): void
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'sub' => 'user123',
            'email' => 'user@example.com',
            'exp' => time() + 3600,
        ]));
        $token = "$header.$payload.signature";

        $claims = AuthUtils::extractJwtClaims($token);

        $this->assertIsArray($claims);
        $this->assertEquals('user123', $claims['sub']);
        $this->assertEquals('user@example.com', $claims['email']);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testIsJwtExpired(): void
    {
        // Create expired token
        $header = base64_encode(json_encode(['alg' => 'HS256']));
        $expiredPayload = base64_encode(json_encode([
            'exp' => time() - 3600, // Expired 1 hour ago
        ]));
        $expiredToken = "$header.$expiredPayload.signature";

        $this->assertTrue(AuthUtils::isJwtExpired($expiredToken));

        // Create valid token
        $validPayload = base64_encode(json_encode([
            'exp' => time() + 3600, // Expires in 1 hour
        ]));
        $validToken = "$header.$validPayload.signature";

        $this->assertFalse(AuthUtils::isJwtExpired($validToken));

        // Token without exp claim
        $noExpPayload = base64_encode(json_encode(['sub' => 'user123']));
        $noExpToken = "$header.$noExpPayload.signature";

        $this->assertFalse(AuthUtils::isJwtExpired($noExpToken));
    }

    public function testBuildAuthorizationUrl(): void
    {
        $endpoint = 'https://auth.example.com/authorize';
        $params = [
            'client_id' => 'test-client',
            'redirect_uri' => 'https://app.example.com/callback',
            'response_type' => 'code',
            'scope' => 'read write',
            'state' => 'random-state',
        ];

        $url = AuthUtils::buildAuthorizationUrl($endpoint, $params);

        $this->assertStringStartsWith('https://auth.example.com/authorize?', $url);
        $this->assertStringContainsString('client_id=test-client', $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode('https://app.example.com/callback'), $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=read+write', $url);
        $this->assertStringContainsString('state=random-state', $url);
    }

    public function testBuildAuthorizationUrlWithExistingParams(): void
    {
        $endpoint = 'https://auth.example.com/authorize?foo=bar';
        $params = [
            'client_id' => 'test-client',
            'state' => 'random-state',
        ];

        $url = AuthUtils::buildAuthorizationUrl($endpoint, $params);

        $this->assertStringContainsString('foo=bar', $url);
        $this->assertStringContainsString('client_id=test-client', $url);
        $this->assertStringContainsString('state=random-state', $url);
    }
}
