<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use MCP\Shared\OAuthProtectedResourceMetadata;
use MCP\Shared\OAuthMetadata;
use MCP\Shared\OAuthTokens;
use MCP\Shared\OAuthErrorResponse;
use MCP\Shared\OAuthClientMetadata;
use MCP\Shared\OAuthClientInformation;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testOAuthProtectedResourceMetadata(): void
    {
        $metadata = new OAuthProtectedResourceMetadata(
            resource: 'https://api.example.com/v1',
            authorizationServers: ['https://auth.example.com'],
            jwksUri: 'https://auth.example.com/.well-known/jwks.json',
            scopesSupported: ['read', 'write'],
            resourceName: 'Example API'
        );

        $json = $metadata->jsonSerialize();

        $this->assertEquals('https://api.example.com/v1', $json['resource']);
        $this->assertEquals(['https://auth.example.com'], $json['authorization_servers']);
        $this->assertEquals('https://auth.example.com/.well-known/jwks.json', $json['jwks_uri']);
        $this->assertEquals(['read', 'write'], $json['scopes_supported']);
        $this->assertEquals('Example API', $json['resource_name']);
    }

    public function testOAuthProtectedResourceMetadataUrlValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('jwksUri cannot use javascript:, data:, or vbscript: scheme');

        new OAuthProtectedResourceMetadata(
            resource: 'https://api.example.com',
            jwksUri: 'javascript:alert(1)'
        );
    }

    public function testOAuthProtectedResourceMetadataInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('authorizationServers must be a valid URL');

        new OAuthProtectedResourceMetadata(
            resource: 'https://api.example.com',
            authorizationServers: ['not-a-url']
        );
    }

    public function testOAuthMetadata(): void
    {
        $metadata = new OAuthMetadata(
            issuer: 'https://auth.example.com',
            authorizationEndpoint: 'https://auth.example.com/authorize',
            tokenEndpoint: 'https://auth.example.com/token',
            responseTypesSupported: ['code', 'token'],
            scopesSupported: ['openid', 'profile', 'email'],
            grantTypesSupported: ['authorization_code', 'refresh_token'],
            codeChallengeMethodsSupported: ['S256', 'plain']
        );

        $json = $metadata->jsonSerialize();

        $this->assertEquals('https://auth.example.com', $json['issuer']);
        $this->assertEquals('https://auth.example.com/authorize', $json['authorization_endpoint']);
        $this->assertEquals('https://auth.example.com/token', $json['token_endpoint']);
        $this->assertEquals(['code', 'token'], $json['response_types_supported']);
        $this->assertEquals(['openid', 'profile', 'email'], $json['scopes_supported']);
        $this->assertEquals(['authorization_code', 'refresh_token'], $json['grant_types_supported']);
        $this->assertEquals(['S256', 'plain'], $json['code_challenge_methods_supported']);
    }

    public function testOAuthMetadataUrlValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('authorizationEndpoint cannot use javascript:, data:, or vbscript: scheme');

        new OAuthMetadata(
            issuer: 'https://auth.example.com',
            authorizationEndpoint: 'data:text/html,<script>alert(1)</script>',
            tokenEndpoint: 'https://auth.example.com/token',
            responseTypesSupported: ['code']
        );
    }

    public function testOAuthTokens(): void
    {
        $tokens = new OAuthTokens(
            accessToken: 'access-token-value',
            tokenType: 'Bearer',
            idToken: 'id-token-value',
            expiresIn: 3600,
            scope: 'openid profile',
            refreshToken: 'refresh-token-value'
        );

        $this->assertEquals('access-token-value', $tokens->getAccessToken());
        $this->assertEquals('Bearer', $tokens->getTokenType());
        $this->assertEquals('id-token-value', $tokens->getIdToken());
        $this->assertEquals(3600, $tokens->getExpiresIn());
        $this->assertEquals('openid profile', $tokens->getScope());
        $this->assertEquals('refresh-token-value', $tokens->getRefreshToken());

        $json = $tokens->jsonSerialize();
        $this->assertEquals('access-token-value', $json['access_token']);
        $this->assertEquals('Bearer', $json['token_type']);
        $this->assertEquals('id-token-value', $json['id_token']);
        $this->assertEquals(3600, $json['expires_in']);
        $this->assertEquals('openid profile', $json['scope']);
        $this->assertEquals('refresh-token-value', $json['refresh_token']);
    }

    public function testOAuthTokensMinimal(): void
    {
        $tokens = new OAuthTokens(
            accessToken: 'access-token-value',
            tokenType: 'Bearer'
        );

        $json = $tokens->jsonSerialize();
        $this->assertEquals(['access_token' => 'access-token-value', 'token_type' => 'Bearer'], $json);
    }

    public function testOAuthErrorResponse(): void
    {
        $error = new OAuthErrorResponse(
            error: 'invalid_request',
            errorDescription: 'The request is missing a required parameter',
            errorUri: 'https://example.com/docs/errors#invalid_request'
        );

        $this->assertEquals('invalid_request', $error->getError());
        $this->assertEquals('The request is missing a required parameter', $error->getErrorDescription());
        $this->assertEquals('https://example.com/docs/errors#invalid_request', $error->getErrorUri());

        $json = $error->jsonSerialize();
        $this->assertEquals('invalid_request', $json['error']);
        $this->assertEquals('The request is missing a required parameter', $json['error_description']);
        $this->assertEquals('https://example.com/docs/errors#invalid_request', $json['error_uri']);
    }

    public function testOAuthErrorResponseMinimal(): void
    {
        $error = new OAuthErrorResponse(error: 'server_error');

        $json = $error->jsonSerialize();
        $this->assertEquals(['error' => 'server_error'], $json);
    }

    public function testOAuthClientMetadata(): void
    {
        $metadata = new OAuthClientMetadata(
            redirectUris: ['https://app.example.com/callback'],
            tokenEndpointAuthMethod: 'client_secret_basic',
            grantTypes: ['authorization_code', 'refresh_token'],
            responseTypes: ['code'],
            clientName: 'Example Application',
            clientUri: 'https://app.example.com',
            logoUri: 'https://app.example.com/logo.png',
            scope: 'openid profile email',
            contacts: ['admin@example.com'],
            tosUri: 'https://app.example.com/terms',
            policyUri: 'https://app.example.com/privacy'
        );

        $json = $metadata->jsonSerialize();

        $this->assertEquals(['https://app.example.com/callback'], $json['redirect_uris']);
        $this->assertEquals('client_secret_basic', $json['token_endpoint_auth_method']);
        $this->assertEquals(['authorization_code', 'refresh_token'], $json['grant_types']);
        $this->assertEquals(['code'], $json['response_types']);
        $this->assertEquals('Example Application', $json['client_name']);
        $this->assertEquals('https://app.example.com', $json['client_uri']);
        $this->assertEquals('https://app.example.com/logo.png', $json['logo_uri']);
        $this->assertEquals('openid profile email', $json['scope']);
        $this->assertEquals(['admin@example.com'], $json['contacts']);
        $this->assertEquals('https://app.example.com/terms', $json['tos_uri']);
        $this->assertEquals('https://app.example.com/privacy', $json['policy_uri']);
    }

    public function testOAuthClientMetadataUrlValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('redirectUris cannot use javascript:, data:, or vbscript: scheme');

        new OAuthClientMetadata(
            redirectUris: ['javascript:void(0)']
        );
    }

    public function testOAuthClientMetadataMinimal(): void
    {
        $metadata = new OAuthClientMetadata(
            redirectUris: ['https://app.example.com/callback']
        );

        $json = $metadata->jsonSerialize();
        $this->assertEquals(['redirect_uris' => ['https://app.example.com/callback']], $json);
    }

    public function testOAuthClientInformation(): void
    {
        $info = new OAuthClientInformation(
            clientId: 'client-123',
            clientSecret: 'secret-456',
            clientIdIssuedAt: 1234567890,
            clientSecretExpiresAt: 1234567890 + 86400
        );

        $this->assertEquals('client-123', $info->getClientId());
        $this->assertEquals('secret-456', $info->getClientSecret());
        $this->assertEquals(1234567890, $info->getClientIdIssuedAt());
        $this->assertEquals(1234567890 + 86400, $info->getClientSecretExpiresAt());

        $json = $info->jsonSerialize();
        $this->assertEquals('client-123', $json['client_id']);
        $this->assertEquals('secret-456', $json['client_secret']);
        $this->assertEquals(1234567890, $json['client_id_issued_at']);
        $this->assertEquals(1234567890 + 86400, $json['client_secret_expires_at']);
    }

    public function testOAuthClientInformationMinimal(): void
    {
        $info = new OAuthClientInformation(clientId: 'client-123');

        $json = $info->jsonSerialize();
        $this->assertEquals(['client_id' => 'client-123'], $json);
    }

    public function testAdditionalProperties(): void
    {
        // Test that additional properties are preserved
        $metadata = new OAuthProtectedResourceMetadata(
            resource: 'https://api.example.com',
            additionalProperties: ['custom_field' => 'custom_value']
        );

        $json = $metadata->jsonSerialize();
        $this->assertEquals('custom_value', $json['custom_field']);
        $this->assertEquals('https://api.example.com', $json['resource']);
    }

    public function testAllUrlValidations(): void
    {
        // Test various dangerous URL schemes
        $dangerousSchemes = ['javascript:', 'data:', 'vbscript:'];

        foreach ($dangerousSchemes as $scheme) {
            try {
                new OAuthMetadata(
                    issuer: 'https://auth.example.com',
                    authorizationEndpoint: 'https://auth.example.com/auth',
                    tokenEndpoint: $scheme . 'alert(1)',
                    responseTypesSupported: ['code']
                );
                $this->fail('Expected exception for ' . $scheme);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('cannot use javascript:, data:, or vbscript: scheme', $e->getMessage());
            }
        }
    }
}
