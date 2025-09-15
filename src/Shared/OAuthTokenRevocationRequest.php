<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * OAuth 2.0 token revocation request.
 */
final readonly class OAuthTokenRevocationRequest
{
    public function __construct(
        public string $token,
        public ?string $tokenTypeHint = null
    ) {
    }
}
