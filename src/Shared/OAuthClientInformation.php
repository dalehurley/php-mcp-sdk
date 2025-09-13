<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * RFC 7591 OAuth 2.0 Dynamic Client Registration client information
 */
class OAuthClientInformation implements \JsonSerializable
{
    public function __construct(
        private readonly string $clientId,
        private readonly ?string $clientSecret = null,
        private readonly ?int $clientIdIssuedAt = null,
        private readonly ?int $clientSecretExpiresAt = null
    ) {}

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function getClientIdIssuedAt(): ?int
    {
        return $this->clientIdIssuedAt;
    }

    public function getClientSecretExpiresAt(): ?int
    {
        return $this->clientSecretExpiresAt;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'client_id' => $this->clientId,
        ];

        if ($this->clientSecret !== null) {
            $data['client_secret'] = $this->clientSecret;
        }
        if ($this->clientIdIssuedAt !== null) {
            $data['client_id_issued_at'] = $this->clientIdIssuedAt;
        }
        if ($this->clientSecretExpiresAt !== null) {
            $data['client_secret_expires_at'] = $this->clientSecretExpiresAt;
        }

        return $data;
    }

    /**
     * Create instance from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientId: $data['client_id'],
            clientSecret: $data['client_secret'] ?? null,
            clientIdIssuedAt: $data['client_id_issued_at'] ?? null,
            clientSecretExpiresAt: $data['client_secret_expires_at'] ?? null
        );
    }
}
