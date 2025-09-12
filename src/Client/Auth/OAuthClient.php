<?php

declare(strict_types=1);

namespace MCP\Client\Auth;

use MCP\Shared\OAuthClientInformation;
use MCP\Shared\OAuthTokens;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * OAuth 2.1 client with PKCE support for MCP.
 */
final class OAuthClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?TokenStorage $tokenStorage = null
    ) {}

    /**
     * Generate a PKCE code verifier.
     */
    public function generateCodeVerifier(): string
    {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Generate a PKCE code challenge from a verifier.
     */
    public function generateCodeChallenge(string $verifier): string
    {
        $hash = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Build authorization URL for OAuth flow.
     */
    public function buildAuthorizationUrl(
        string $authorizationEndpoint,
        OAuthClientInformation $client,
        string $redirectUri,
        string $codeChallenge,
        ?string $state = null,
        array $scopes = [],
        ?string $resource = null
    ): string {
        $params = [
            'response_type' => 'code',
            'client_id' => $client->getClientId(),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        if ($state !== null) {
            $params['state'] = $state;
        }

        if (!empty($scopes)) {
            $params['scope'] = implode(' ', $scopes);
        }

        if ($resource !== null) {
            $params['resource'] = $resource;
        }

        return $authorizationEndpoint . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeAuthorizationCode(
        string $tokenEndpoint,
        OAuthClientInformation $client,
        string $authorizationCode,
        string $codeVerifier,
        string $redirectUri,
        ?string $resource = null
    ): PromiseInterface {
        return new Promise(function ($resolve, $reject) use (
            $tokenEndpoint,
            $client,
            $authorizationCode,
            $codeVerifier,
            $redirectUri,
            $resource
        ) {
            try {
                $params = [
                    'grant_type' => 'authorization_code',
                    'client_id' => $client->getClientId(),
                    'code' => $authorizationCode,
                    'code_verifier' => $codeVerifier,
                    'redirect_uri' => $redirectUri,
                ];

                if ($client->getClientSecret() !== null) {
                    $params['client_secret'] = $client->getClientSecret();
                }

                if ($resource !== null) {
                    $params['resource'] = $resource;
                }

                $request = $this->requestFactory
                    ->createRequest('POST', $tokenEndpoint)
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                    ->withBody($this->streamFactory->createStream(http_build_query($params)));

                $response = $this->httpClient->sendRequest($request);

                if ($response->getStatusCode() !== 200) {
                    $reject(new \Exception("Token exchange failed: {$response->getStatusCode()}"));
                    return;
                }

                $data = json_decode((string)$response->getBody(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $reject(new \Exception('Invalid JSON response from token endpoint'));
                    return;
                }

                $tokens = new OAuthTokens(
                    $data['access_token'],
                    $data['token_type'] ?? 'Bearer',
                    $data['id_token'] ?? null,
                    $data['expires_in'] ?? null,
                    $data['scope'] ?? null,
                    $data['refresh_token'] ?? null
                );

                // Store tokens if storage is available
                if ($this->tokenStorage !== null) {
                    $this->tokenStorage->storeTokens($client->getClientId(), $tokens);
                }

                $resolve($tokens);
            } catch (\Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * Refresh access token using refresh token.
     */
    public function refreshToken(
        string $tokenEndpoint,
        OAuthClientInformation $client,
        string $refreshToken,
        array $scopes = [],
        ?string $resource = null
    ): PromiseInterface {
        return new Promise(function ($resolve, $reject) use (
            $tokenEndpoint,
            $client,
            $refreshToken,
            $scopes,
            $resource
        ) {
            try {
                $params = [
                    'grant_type' => 'refresh_token',
                    'client_id' => $client->getClientId(),
                    'refresh_token' => $refreshToken,
                ];

                if ($client->getClientSecret() !== null) {
                    $params['client_secret'] = $client->getClientSecret();
                }

                if (!empty($scopes)) {
                    $params['scope'] = implode(' ', $scopes);
                }

                if ($resource !== null) {
                    $params['resource'] = $resource;
                }

                $request = $this->requestFactory
                    ->createRequest('POST', $tokenEndpoint)
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                    ->withBody($this->streamFactory->createStream(http_build_query($params)));

                $response = $this->httpClient->sendRequest($request);

                if ($response->getStatusCode() !== 200) {
                    $reject(new \Exception("Token refresh failed: {$response->getStatusCode()}"));
                    return;
                }

                $data = json_decode((string)$response->getBody(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $reject(new \Exception('Invalid JSON response from token endpoint'));
                    return;
                }

                $tokens = new OAuthTokens(
                    $data['access_token'],
                    $data['token_type'] ?? 'Bearer',
                    $data['id_token'] ?? null,
                    $data['expires_in'] ?? null,
                    $data['scope'] ?? null,
                    $data['refresh_token'] ?? $refreshToken // Keep original if not provided
                );

                // Store tokens if storage is available
                if ($this->tokenStorage !== null) {
                    $this->tokenStorage->storeTokens($client->getClientId(), $tokens);
                }

                $resolve($tokens);
            } catch (\Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * Revoke a token.
     */
    public function revokeToken(
        string $revocationEndpoint,
        OAuthClientInformation $client,
        string $token,
        ?string $tokenTypeHint = null
    ): PromiseInterface {
        return new Promise(function ($resolve, $reject) use (
            $revocationEndpoint,
            $client,
            $token,
            $tokenTypeHint
        ) {
            try {
                $params = [
                    'token' => $token,
                    'client_id' => $client->getClientId(),
                ];

                if ($client->getClientSecret() !== null) {
                    $params['client_secret'] = $client->getClientSecret();
                }

                if ($tokenTypeHint !== null) {
                    $params['token_type_hint'] = $tokenTypeHint;
                }

                $request = $this->requestFactory
                    ->createRequest('POST', $revocationEndpoint)
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                    ->withBody($this->streamFactory->createStream(http_build_query($params)));

                $response = $this->httpClient->sendRequest($request);

                if ($response->getStatusCode() !== 200) {
                    $reject(new \Exception("Token revocation failed: {$response->getStatusCode()}"));
                    return;
                }

                // Clear tokens from storage if available
                if ($this->tokenStorage !== null) {
                    $this->tokenStorage->clearTokens($client->getClientId());
                }

                $resolve();
            } catch (\Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * Get stored tokens for a client.
     */
    public function getStoredTokens(string $clientId): ?OAuthTokens
    {
        return $this->tokenStorage?->getTokens($clientId);
    }

    /**
     * Check if stored tokens are expired and refresh if needed.
     */
    public function ensureValidToken(
        string $tokenEndpoint,
        OAuthClientInformation $client
    ): PromiseInterface {
        return new Promise(function ($resolve, $reject) use ($tokenEndpoint, $client) {
            $tokens = $this->getStoredTokens($client->getClientId());

            if ($tokens === null) {
                $reject(new \Exception('No tokens found for client'));
                return;
            }

            // Check if token is expired (with 30 second buffer)
            $expiresIn = $tokens->getExpiresIn();
            if ($expiresIn !== null && $expiresIn <= 30) {
                // Try to refresh
                $refreshToken = $tokens->getRefreshToken();
                if ($refreshToken === null) {
                    $reject(new \Exception('Token expired and no refresh token available'));
                    return;
                }

                $this->refreshToken($tokenEndpoint, $client, $refreshToken)
                    ->then($resolve, $reject);
            } else {
                $resolve($tokens);
            }
        });
    }
}
