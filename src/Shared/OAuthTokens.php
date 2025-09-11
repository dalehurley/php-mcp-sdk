<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * OAuth 2.1 token response
 */
class OAuthTokens implements \JsonSerializable
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $tokenType,
        private readonly ?string $idToken = null,
        private readonly ?int $expiresIn = null,
        private readonly ?string $scope = null,
        private readonly ?string $refreshToken = null
    ) {}

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function getIdToken(): ?string
    {
        return $this->idToken;
    }

    public function getExpiresIn(): ?int
    {
        return $this->expiresIn;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
        ];

        if ($this->idToken !== null) {
            $data['id_token'] = $this->idToken;
        }
        if ($this->expiresIn !== null) {
            $data['expires_in'] = $this->expiresIn;
        }
        if ($this->scope !== null) {
            $data['scope'] = $this->scope;
        }
        if ($this->refreshToken !== null) {
            $data['refresh_token'] = $this->refreshToken;
        }

        return $data;
    }
}
