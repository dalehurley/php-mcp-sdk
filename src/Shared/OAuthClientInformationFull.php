<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * Full OAuth client information including all metadata from dynamic registration.
 * 
 * This extends the basic client information with additional metadata
 * returned during OAuth 2.0 Dynamic Client Registration (RFC 7591).
 */
class OAuthClientInformationFull extends OAuthClientInformation
{
    public function __construct(
        string $clientId,
        ?string $clientSecret = null,
        ?int $clientIdIssuedAt = null,
        ?int $clientSecretExpiresAt = null,
        private readonly ?string $clientName = null,
        private readonly ?string $clientUri = null,
        private readonly ?array $redirectUris = null,
        private readonly ?array $grantTypes = null,
        private readonly ?array $responseTypes = null,
        private readonly ?string $tokenEndpointAuthMethod = null,
        private readonly ?string $scope = null,
        private readonly ?array $contacts = null,
        private readonly ?string $logoUri = null,
        private readonly ?string $policyUri = null,
        private readonly ?string $tosUri = null,
        private readonly ?string $jwksUri = null,
        private readonly ?array $jwks = null,
        private readonly ?string $softwareId = null,
        private readonly ?string $softwareVersion = null,
        private readonly ?string $registrationAccessToken = null,
        private readonly ?string $registrationClientUri = null
    ) {
        parent::__construct($clientId, $clientSecret, $clientIdIssuedAt, $clientSecretExpiresAt);
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function getClientUri(): ?string
    {
        return $this->clientUri;
    }

    public function getRedirectUris(): ?array
    {
        return $this->redirectUris;
    }

    public function getGrantTypes(): ?array
    {
        return $this->grantTypes;
    }

    public function getResponseTypes(): ?array
    {
        return $this->responseTypes;
    }

    public function getTokenEndpointAuthMethod(): ?string
    {
        return $this->tokenEndpointAuthMethod;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getContacts(): ?array
    {
        return $this->contacts;
    }

    public function getLogoUri(): ?string
    {
        return $this->logoUri;
    }

    public function getPolicyUri(): ?string
    {
        return $this->policyUri;
    }

    public function getTosUri(): ?string
    {
        return $this->tosUri;
    }

    public function getJwksUri(): ?string
    {
        return $this->jwksUri;
    }

    public function getJwks(): ?array
    {
        return $this->jwks;
    }

    public function getSoftwareId(): ?string
    {
        return $this->softwareId;
    }

    public function getSoftwareVersion(): ?string
    {
        return $this->softwareVersion;
    }

    public function getRegistrationAccessToken(): ?string
    {
        return $this->registrationAccessToken;
    }

    public function getRegistrationClientUri(): ?string
    {
        return $this->registrationClientUri;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->clientName !== null) {
            $data['client_name'] = $this->clientName;
        }
        if ($this->clientUri !== null) {
            $data['client_uri'] = $this->clientUri;
        }
        if ($this->redirectUris !== null) {
            $data['redirect_uris'] = $this->redirectUris;
        }
        if ($this->grantTypes !== null) {
            $data['grant_types'] = $this->grantTypes;
        }
        if ($this->responseTypes !== null) {
            $data['response_types'] = $this->responseTypes;
        }
        if ($this->tokenEndpointAuthMethod !== null) {
            $data['token_endpoint_auth_method'] = $this->tokenEndpointAuthMethod;
        }
        if ($this->scope !== null) {
            $data['scope'] = $this->scope;
        }
        if ($this->contacts !== null) {
            $data['contacts'] = $this->contacts;
        }
        if ($this->logoUri !== null) {
            $data['logo_uri'] = $this->logoUri;
        }
        if ($this->policyUri !== null) {
            $data['policy_uri'] = $this->policyUri;
        }
        if ($this->tosUri !== null) {
            $data['tos_uri'] = $this->tosUri;
        }
        if ($this->jwksUri !== null) {
            $data['jwks_uri'] = $this->jwksUri;
        }
        if ($this->jwks !== null) {
            $data['jwks'] = $this->jwks;
        }
        if ($this->softwareId !== null) {
            $data['software_id'] = $this->softwareId;
        }
        if ($this->softwareVersion !== null) {
            $data['software_version'] = $this->softwareVersion;
        }
        if ($this->registrationAccessToken !== null) {
            $data['registration_access_token'] = $this->registrationAccessToken;
        }
        if ($this->registrationClientUri !== null) {
            $data['registration_client_uri'] = $this->registrationClientUri;
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
            clientSecretExpiresAt: $data['client_secret_expires_at'] ?? null,
            clientName: $data['client_name'] ?? null,
            clientUri: $data['client_uri'] ?? null,
            redirectUris: $data['redirect_uris'] ?? null,
            grantTypes: $data['grant_types'] ?? null,
            responseTypes: $data['response_types'] ?? null,
            tokenEndpointAuthMethod: $data['token_endpoint_auth_method'] ?? null,
            scope: $data['scope'] ?? null,
            contacts: $data['contacts'] ?? null,
            logoUri: $data['logo_uri'] ?? null,
            policyUri: $data['policy_uri'] ?? null,
            tosUri: $data['tos_uri'] ?? null,
            jwksUri: $data['jwks_uri'] ?? null,
            jwks: $data['jwks'] ?? null,
            softwareId: $data['software_id'] ?? null,
            softwareVersion: $data['software_version'] ?? null,
            registrationAccessToken: $data['registration_access_token'] ?? null,
            registrationClientUri: $data['registration_client_uri'] ?? null
        );
    }
}
