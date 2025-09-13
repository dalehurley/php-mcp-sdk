<?php

declare(strict_types=1);

namespace MCP\Client\Auth;

use Amp\Future;
use MCP\Client\Auth\Exceptions\InvalidClientException;
use MCP\Client\Auth\Exceptions\InvalidGrantException;
use MCP\Client\Auth\Exceptions\UnauthorizedClientException;
use MCP\Client\Auth\Exceptions\UnauthorizedException;
use MCP\Shared\OAuthClientInformation;
use MCP\Shared\OAuthClientInformationFull;
use MCP\Shared\OAuthClientMetadata;
use MCP\Shared\OAuthTokens;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function Amp\async;

/**
 * Enhanced OAuth 2.1 client with full authorization flow support and PKCE.
 * 
 * This client implements the complete OAuth 2.0 Authorization Code flow with PKCE,
 * automatic token refresh, OAuth server discovery, and error handling that matches
 * the TypeScript SDK functionality.
 */
final class OAuthClient
{
    private readonly OAuthUtils $oauthUtils;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?TokenStorage $tokenStorage = null,
        ?OAuthUtils $oauthUtils = null
    ) {
        $this->oauthUtils = $oauthUtils ?? new OAuthUtils($httpClient, $requestFactory, $streamFactory);
    }

    /**
     * Generate a PKCE code verifier.
     */
    public function generateCodeVerifier(): string
    {
        return OAuthUtils::generateCodeVerifier();
    }

    /**
     * Generate a PKCE code challenge from a verifier.
     */
    public function generateCodeChallenge(string $verifier): string
    {
        return OAuthUtils::generateCodeChallenge($verifier);
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
    ): Future {
        return async(function () use (
            $tokenEndpoint,
            $client,
            $authorizationCode,
            $codeVerifier,
            $redirectUri,
            $resource
        ) {
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
                throw OAuthUtils::parseErrorResponse($response);
            }

            $data = json_decode((string)$response->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from token endpoint');
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

            return $tokens;
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
    ): Future {
        return async(function () use (
            $tokenEndpoint,
            $client,
            $refreshToken,
            $scopes,
            $resource
        ) {
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
                throw OAuthUtils::parseErrorResponse($response);
            }

            $data = json_decode((string)$response->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from token endpoint');
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

            return $tokens;
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
    ): Future {
        return async(function () use (
            $revocationEndpoint,
            $client,
            $token,
            $tokenTypeHint
        ) {
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
                throw OAuthUtils::parseErrorResponse($response);
            }

            // Clear tokens from storage if available
            if ($this->tokenStorage !== null) {
                $this->tokenStorage->clearTokens($client->getClientId());
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
    ): Future {
        return async(function () use ($tokenEndpoint, $client) {
            $tokens = $this->getStoredTokens($client->getClientId());

            if ($tokens === null) {
                throw new \Exception('No tokens found for client');
            }

            // Check if token is expired (with 30 second buffer)
            $expiresIn = $tokens->getExpiresIn();
            if ($expiresIn !== null && $expiresIn <= 30) {
                // Try to refresh
                $refreshToken = $tokens->getRefreshToken();
                if ($refreshToken === null) {
                    throw new \Exception('Token expired and no refresh token available');
                }

                return $this->refreshToken($tokenEndpoint, $client, $refreshToken)->await();
            }

            return $tokens;
        });
    }

    /**
     * Get authorization URL for OAuth flow with automatic server discovery.
     */
    public function getAuthorizationUrl(
        string $serverUrl,
        OAuthClientInformation $client,
        string $redirectUri,
        ?string $state = null,
        array $scopes = [],
        ?string $resource = null
    ): Future {
        return async(function () use ($serverUrl, $client, $redirectUri, $state, $scopes, $resource) {
            // Discover authorization server metadata
            $metadata = $this->oauthUtils->discoverAuthorizationServerMetadata($serverUrl)->await();

            if (!$metadata || !isset($metadata['authorization_endpoint'])) {
                throw new \Exception('Could not discover authorization endpoint');
            }

            $codeVerifier = $this->generateCodeVerifier();
            $codeChallenge = $this->generateCodeChallenge($codeVerifier);

            $authUrl = $this->buildAuthorizationUrl(
                $metadata['authorization_endpoint'],
                $client,
                $redirectUri,
                $codeChallenge,
                $state,
                $scopes,
                $resource
            );

            return [
                'authorizationUrl' => $authUrl,
                'codeVerifier' => $codeVerifier,
                'state' => $state
            ];
        });
    }

    /**
     * Exchange authorization code for access token with automatic server discovery.
     */
    public function exchangeAuthorizationCodeWithDiscovery(
        string $serverUrl,
        OAuthClientInformation $client,
        string $authorizationCode,
        string $codeVerifier,
        string $redirectUri,
        ?string $resource = null
    ): Future {
        return async(function () use ($serverUrl, $client, $authorizationCode, $codeVerifier, $redirectUri, $resource) {
            // Discover authorization server metadata
            $metadata = $this->oauthUtils->discoverAuthorizationServerMetadata($serverUrl)->await();

            if (!$metadata || !isset($metadata['token_endpoint'])) {
                throw new \Exception('Could not discover token endpoint');
            }

            return $this->exchangeAuthorizationCode(
                $metadata['token_endpoint'],
                $client,
                $authorizationCode,
                $codeVerifier,
                $redirectUri,
                $resource
            )->await();
        });
    }

    /**
     * Refresh access token with automatic server discovery.
     */
    public function refreshTokenWithDiscovery(
        string $serverUrl,
        OAuthClientInformation $client,
        string $refreshToken,
        array $scopes = [],
        ?string $resource = null
    ): Future {
        return async(function () use ($serverUrl, $client, $refreshToken, $scopes, $resource) {
            // Discover authorization server metadata
            $metadata = $this->oauthUtils->discoverAuthorizationServerMetadata($serverUrl)->await();

            if (!$metadata || !isset($metadata['token_endpoint'])) {
                throw new \Exception('Could not discover token endpoint');
            }

            return $this->refreshToken(
                $metadata['token_endpoint'],
                $client,
                $refreshToken,
                $scopes,
                $resource
            )->await();
        });
    }

    /**
     * Register OAuth client dynamically.
     */
    public function registerClient(
        string $serverUrl,
        OAuthClientMetadata $clientMetadata
    ): Future {
        return async(function () use ($serverUrl, $clientMetadata) {
            // Discover authorization server metadata
            $metadata = $this->oauthUtils->discoverAuthorizationServerMetadata($serverUrl)->await();

            if (!$metadata || !isset($metadata['registration_endpoint'])) {
                throw new \Exception('Server does not support dynamic client registration');
            }

            return $this->oauthUtils->registerClient(
                $metadata['registration_endpoint'],
                $clientMetadata->jsonSerialize()
            )->await();
        });
    }

    /**
     * Check if the client is authorized (has valid tokens).
     */
    public function isAuthorized(string $clientId): bool
    {
        $tokens = $this->getStoredTokens($clientId);

        if (!$tokens || !$tokens->getAccessToken()) {
            return false;
        }

        // Check if token is expired
        $expiresIn = $tokens->getExpiresIn();
        if ($expiresIn !== null && $expiresIn <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Revoke tokens with automatic server discovery.
     */
    public function revokeTokensWithDiscovery(
        string $serverUrl,
        OAuthClientInformation $client,
        ?string $token = null
    ): Future {
        return async(function () use ($serverUrl, $client, $token) {
            // Discover authorization server metadata
            $metadata = $this->oauthUtils->discoverAuthorizationServerMetadata($serverUrl)->await();

            if (!$metadata || !isset($metadata['revocation_endpoint'])) {
                throw new \Exception('Server does not support token revocation');
            }

            // Use provided token or get stored access token
            if (!$token) {
                $tokens = $this->getStoredTokens($client->getClientId());
                $token = $tokens?->getAccessToken();

                if (!$token) {
                    throw new \Exception('No token to revoke');
                }
            }

            return $this->revokeToken(
                $metadata['revocation_endpoint'],
                $client,
                $token
            )->await();
        });
    }

    /**
     * Complete OAuth flow with error handling and retry logic.
     */
    public function auth(
        OAuthClientProvider $provider,
        string $serverUrl,
        ?string $authorizationCode = null,
        ?string $scope = null,
        ?string $resourceMetadataUrl = null
    ): Future {
        return async(function () use ($provider, $serverUrl, $authorizationCode, $scope, $resourceMetadataUrl) {
            try {
                return $this->authInternal($provider, $serverUrl, $authorizationCode, $scope, $resourceMetadataUrl)->await();
            } catch (InvalidClientException | UnauthorizedClientException $e) {
                // Handle recoverable errors by invalidating credentials and retrying
                $provider->invalidateCredentials('all')->await();
                return $this->authInternal($provider, $serverUrl, $authorizationCode, $scope, $resourceMetadataUrl)->await();
            } catch (InvalidGrantException $e) {
                // Handle token-specific errors
                $provider->invalidateCredentials('tokens')->await();
                return $this->authInternal($provider, $serverUrl, $authorizationCode, $scope, $resourceMetadataUrl)->await();
            }
        });
    }

    /**
     * Internal auth implementation.
     */
    private function authInternal(
        OAuthClientProvider $provider,
        string $serverUrl,
        ?string $authorizationCode = null,
        ?string $scope = null,
        ?string $resourceMetadataUrl = null
    ): Future {
        return async(function () use ($provider, $serverUrl, $authorizationCode, $scope, $resourceMetadataUrl) {
            // Discover protected resource metadata if URL provided
            $resourceMetadata = null;
            if ($resourceMetadataUrl) {
                $resourceMetadata = $this->oauthUtils->discoverProtectedResourceMetadata(
                    $serverUrl,
                    $resourceMetadataUrl
                )->await();
            }

            // Determine authorization server URL
            $authServerUrl = $serverUrl;
            if ($resourceMetadata && isset($resourceMetadata['authorization_servers'][0])) {
                $authServerUrl = $resourceMetadata['authorization_servers'][0];
            }

            // Discover authorization server metadata
            $metadata = $this->oauthUtils->discoverAuthorizationServerMetadata($authServerUrl)->await();
            if (!$metadata) {
                throw new \Exception('Could not discover authorization server metadata');
            }

            // Handle client registration if needed
            $clientInfo = $provider->loadClientInformation()->await();
            if (!$clientInfo) {
                if ($authorizationCode !== null) {
                    throw new \Exception('Existing OAuth client information is required when exchanging an authorization code');
                }

                $fullInfo = $this->registerClient($authServerUrl, $provider->getClientMetadata())->await();
                $provider->storeClientInformation(OAuthClientInformationFull::fromArray($fullInfo))->await();
                $clientInfo = OAuthClientInformation::fromArray($fullInfo);
            }

            // Exchange authorization code for tokens
            if ($authorizationCode !== null) {
                $codeVerifier = $provider->codeVerifier()->await();
                $resource = $resourceMetadata ? OAuthUtils::getResourceUrlFromServerUrl($serverUrl) : null;

                $tokens = $this->exchangeAuthorizationCode(
                    $metadata['token_endpoint'],
                    $clientInfo,
                    $authorizationCode,
                    $codeVerifier,
                    $provider->getRedirectUrl(),
                    $resource
                )->await();

                $provider->storeTokens($tokens)->await();
                return 'AUTHORIZED';
            }

            $tokens = $provider->loadTokens()->await();

            // Handle token refresh or new authorization
            if ($tokens && $tokens->getRefreshToken()) {
                try {
                    $resource = $resourceMetadata ? OAuthUtils::getResourceUrlFromServerUrl($serverUrl) : null;

                    $newTokens = $this->refreshToken(
                        $metadata['token_endpoint'],
                        $clientInfo,
                        $tokens->getRefreshToken(),
                        $scope ? [$scope] : [],
                        $resource
                    )->await();

                    $provider->storeTokens($newTokens)->await();
                    return 'AUTHORIZED';
                } catch (\Exception $e) {
                    // If refresh fails, continue to new authorization
                }
            }

            // Start new authorization flow
            $state = $provider->state();
            $resource = $resourceMetadata ? OAuthUtils::getResourceUrlFromServerUrl($serverUrl) : null;

            $authResult = $this->getAuthorizationUrl(
                $authServerUrl,
                $clientInfo,
                $provider->getRedirectUrl(),
                $state,
                $scope ? [$scope] : [],
                $resource
            )->await();

            $provider->saveCodeVerifier($authResult['codeVerifier'])->await();
            $provider->redirectToAuthorization($authResult['authorizationUrl'])->await();

            return 'REDIRECT';
        });
    }
}
