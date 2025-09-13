<?php

declare(strict_types=1);

namespace MCP\Server\Auth;

use MCP\Shared\OAuthClientInformation;
use MCP\Shared\OAuthTokenRevocationRequest;
use MCP\Shared\OAuthTokens;
use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;

/**
 * Store for registered OAuth clients.
 */
interface OAuthRegisteredClientsStore
{
    /**
     * Get client information by client ID.
     *
     * @return PromiseInterface<OAuthClientInformation|null>
     */
    public function getClient(string $clientId): PromiseInterface;

    /**
     * Register a new client (optional - for dynamic registration).
     *
     * @return PromiseInterface<OAuthClientInformation>
     */
    public function registerClient(OAuthClientInformation $client): PromiseInterface;
}

/**
 * Implements an end-to-end OAuth server.
 */
interface OAuthServerProvider
{
    /**
     * A store used to read information about registered OAuth clients.
     */
    public function getClientsStore(): OAuthRegisteredClientsStore;

    /**
     * Begins the authorization flow, which can either be implemented by this server itself
     * or via redirection to a separate authorization server.
     *
     * This server must eventually issue a redirect with an authorization response or an
     * error response to the given redirect URI. Per OAuth 2.1:
     * - In the successful case, the redirect MUST include the `code` and `state` (if present) query parameters.
     * - In the error case, the redirect MUST include the `error` query parameter, and MAY include
     *   an optional `error_description` query parameter.
     *
     * @return PromiseInterface<void>
     */
    public function authorize(
        OAuthClientInformation $client,
        AuthorizationParams $params,
        ResponseInterface $response
    ): PromiseInterface;

    /**
     * Returns the `codeChallenge` that was used when the indicated authorization began.
     *
     * @return PromiseInterface<string>
     */
    public function challengeForAuthorizationCode(
        OAuthClientInformation $client,
        string $authorizationCode
    ): PromiseInterface;

    /**
     * Exchanges an authorization code for an access token.
     *
     * @return PromiseInterface<OAuthTokens>
     */
    public function exchangeAuthorizationCode(
        OAuthClientInformation $client,
        string $authorizationCode,
        ?string $codeVerifier = null,
        ?string $redirectUri = null,
        ?string $resource = null
    ): PromiseInterface;

    /**
     * Exchanges a refresh token for an access token.
     *
     * @param string[] $scopes
     * @return PromiseInterface<OAuthTokens>
     */
    public function exchangeRefreshToken(
        OAuthClientInformation $client,
        string $refreshToken,
        array $scopes = [],
        ?string $resource = null
    ): PromiseInterface;

    /**
     * Verifies an access token and returns information about it.
     *
     * @return PromiseInterface<AuthInfo>
     */
    public function verifyAccessToken(string $token): PromiseInterface;

    /**
     * Revokes an access or refresh token. If unimplemented, token revocation is not supported (not recommended).
     *
     * If the given token is invalid or already revoked, this method should do nothing.
     *
     * @return PromiseInterface<void>
     */
    public function revokeToken(
        OAuthClientInformation $client,
        OAuthTokenRevocationRequest $request
    ): PromiseInterface;

    /**
     * Whether to skip local PKCE validation.
     *
     * If true, the server will not perform PKCE validation locally and will pass the
     * code_verifier to the upstream server.
     *
     * NOTE: This should only be true if the upstream server is performing the actual PKCE validation.
     */
    public function skipLocalPkceValidation(): bool;
}

/**
 * Slim implementation useful for token verification only.
 */
interface OAuthTokenVerifier
{
    /**
     * Verifies an access token and returns information about it.
     *
     * @return PromiseInterface<AuthInfo>
     */
    public function verifyAccessToken(string $token): PromiseInterface;
}
