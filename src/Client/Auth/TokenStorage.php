<?php

declare(strict_types=1);

namespace MCP\Client\Auth;

use MCP\Shared\OAuthTokens;

/**
 * Interface for storing and retrieving OAuth tokens.
 */
interface TokenStorage
{
    /**
     * Store tokens for a client.
     */
    public function storeTokens(string $clientId, OAuthTokens $tokens): void;

    /**
     * Retrieve tokens for a client.
     */
    public function getTokens(string $clientId): ?OAuthTokens;

    /**
     * Clear tokens for a client.
     */
    public function clearTokens(string $clientId): void;

    /**
     * Clear all stored tokens.
     */
    public function clearAllTokens(): void;
}

