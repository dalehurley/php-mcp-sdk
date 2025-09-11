<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * RFC 7591 OAuth 2.0 Dynamic Client Registration metadata
 */
class OAuthClientMetadata implements \JsonSerializable
{
    /**
     * @param array<string> $redirectUris
     * @param array<string>|null $grantTypes
     * @param array<string>|null $responseTypes
     * @param array<string>|null $contacts
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly array $redirectUris,
        private readonly ?string $tokenEndpointAuthMethod = null,
        private readonly ?array $grantTypes = null,
        private readonly ?array $responseTypes = null,
        private readonly ?string $clientName = null,
        private readonly ?string $clientUri = null,
        private readonly ?string $logoUri = null,
        private readonly ?string $scope = null,
        private readonly ?array $contacts = null,
        private readonly ?string $tosUri = null,
        private readonly ?string $policyUri = null,
        private readonly ?string $jwksUri = null,
        private readonly mixed $jwks = null,
        private readonly ?string $softwareId = null,
        private readonly ?string $softwareVersion = null,
        private readonly ?string $softwareStatement = null,
        private readonly array $additionalProperties = []
    ) {
        foreach ($redirectUris as $uri) {
            $this->validateUrl($uri, 'redirectUris');
        }
        if ($clientUri !== null) {
            $this->validateUrl($clientUri, 'clientUri');
        }
        if ($logoUri !== null) {
            $this->validateUrl($logoUri, 'logoUri');
        }
        if ($tosUri !== null) {
            $this->validateUrl($tosUri, 'tosUri');
        }
        if ($jwksUri !== null) {
            $this->validateUrl($jwksUri, 'jwksUri');
        }
    }

    private function validateUrl(string $url, string $field): void
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            throw new \InvalidArgumentException("{$field} must be a valid URL");
        }

        if (isset($parsed['scheme'])) {
            $scheme = strtolower($parsed['scheme']);
            if (in_array($scheme, ['javascript', 'data', 'vbscript'], true)) {
                throw new \InvalidArgumentException("{$field} cannot use javascript:, data:, or vbscript: scheme");
            }
        }

        if (!isset($parsed['scheme']) || !isset($parsed['host'])) {
            throw new \InvalidArgumentException("{$field} must be a valid URL");
        }
    }

    public function jsonSerialize(): array
    {
        $data = array_merge($this->additionalProperties, [
            'redirect_uris' => $this->redirectUris,
        ]);

        if ($this->tokenEndpointAuthMethod !== null) {
            $data['token_endpoint_auth_method'] = $this->tokenEndpointAuthMethod;
        }
        if ($this->grantTypes !== null) {
            $data['grant_types'] = $this->grantTypes;
        }
        if ($this->responseTypes !== null) {
            $data['response_types'] = $this->responseTypes;
        }
        if ($this->clientName !== null) {
            $data['client_name'] = $this->clientName;
        }
        if ($this->clientUri !== null) {
            $data['client_uri'] = $this->clientUri;
        }
        if ($this->logoUri !== null) {
            $data['logo_uri'] = $this->logoUri;
        }
        if ($this->scope !== null) {
            $data['scope'] = $this->scope;
        }
        if ($this->contacts !== null) {
            $data['contacts'] = $this->contacts;
        }
        if ($this->tosUri !== null) {
            $data['tos_uri'] = $this->tosUri;
        }
        if ($this->policyUri !== null) {
            $data['policy_uri'] = $this->policyUri;
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
        if ($this->softwareStatement !== null) {
            $data['software_statement'] = $this->softwareStatement;
        }

        return $data;
    }
}
