<?php

declare(strict_types=1);

namespace MCP\Client\Auth;

use MCP\Shared\OAuthTokens;

/**
 * Simple in-memory token storage implementation.
 */
final class InMemoryTokenStorage implements TokenStorage
{
    /** @var array<string, OAuthTokens> */
    private array $tokens = [];

    public function storeTokens(string $clientId, OAuthTokens $tokens): void
    {
        $this->tokens[$clientId] = $tokens;
    }

    public function getTokens(string $clientId): ?OAuthTokens
    {
        return $this->tokens[$clientId] ?? null;
    }

    public function clearTokens(string $clientId): void
    {
        unset($this->tokens[$clientId]);
    }

    public function clearAllTokens(): void
    {
        $this->tokens = [];
    }
}
