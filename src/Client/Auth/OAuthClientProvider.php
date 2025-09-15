<?php

declare(strict_types=1);

namespace MCP\Client\Auth;

use Amp\Future;
use MCP\Shared\OAuthClientInformationFull;
use MCP\Shared\OAuthClientMetadata;
use MCP\Shared\OAuthTokens;

/**
 * Interface for OAuth client providers that handle OAuth 2.0 flows.
 *
 * This interface abstracts the OAuth client functionality to support different
 * storage mechanisms and authentication flows while maintaining consistency
 * with the TypeScript SDK.
 */
interface OAuthClientProvider
{
    /**
     * Get the redirect URL for OAuth authorization flow.
     */
    public function getRedirectUrl(): string;

    /**
     * Get the OAuth client metadata.
     */
    public function getClientMetadata(): OAuthClientMetadata;

    /**
     * Generate or retrieve an OAuth state parameter.
     */
    public function state(): ?string;

    /**
     * Load existing client information from storage.
     * Returns null if no client information is stored.
     */
    public function loadClientInformation(): Future;

    /**
     * Store client information after dynamic registration.
     */
    public function storeClientInformation(OAuthClientInformationFull $info): Future;

    /**
     * Load existing OAuth tokens from storage.
     * Returns null if no tokens are stored.
     */
    public function loadTokens(): Future;

    /**
     * Store OAuth tokens after successful authorization.
     */
    public function storeTokens(OAuthTokens $tokens): Future;

    /**
     * Clear stored OAuth tokens.
     */
    public function clearTokens(): Future;

    /**
     * Get current OAuth tokens (convenience method).
     */
    public function tokens(): Future;

    /**
     * Redirect the user agent to the authorization URL.
     */
    public function redirectToAuthorization(string $authorizationUrl): Future;

    /**
     * Save PKCE code verifier for the current session.
     */
    public function saveCodeVerifier(string $codeVerifier): Future;

    /**
     * Load PKCE code verifier for the current session.
     */
    public function codeVerifier(): Future;

    /**
     * Add custom client authentication to OAuth requests.
     *
     * This method allows implementations to customize how client credentials
     * are included in token requests, supporting various authentication methods
     * beyond the standard OAuth 2.0 methods.
     */
    public function addClientAuthentication(
        array &$headers,
        array &$params,
        string $url,
        ?array $metadata = null
    ): Future;

    /**
     * Validate and select the resource URL for OAuth requests.
     *
     * If not implemented, default validation will be used.
     */
    public function validateResourceURL(string $serverUrl, ?string $resource = null): Future;

    /**
     * Invalidate stored credentials when they are no longer valid.
     *
     * @param string $scope Scope of invalidation: 'all', 'client', 'tokens', or 'verifier'
     */
    public function invalidateCredentials(string $scope): Future;
}
