<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * RFC 8414 OAuth 2.0 Authorization Server Metadata
 */
class OAuthMetadata implements \JsonSerializable
{
    /**
     * @param array<string> $responseTypesSupported
     * @param array<string>|null $scopesSupported
     * @param array<string>|null $responseModesSupported
     * @param array<string>|null $grantTypesSupported
     * @param array<string>|null $tokenEndpointAuthMethodsSupported
     * @param array<string>|null $tokenEndpointAuthSigningAlgValuesSupported
     * @param array<string>|null $revocationEndpointAuthMethodsSupported
     * @param array<string>|null $revocationEndpointAuthSigningAlgValuesSupported
     * @param array<string>|null $introspectionEndpointAuthMethodsSupported
     * @param array<string>|null $introspectionEndpointAuthSigningAlgValuesSupported
     * @param array<string>|null $codeChallengeMethodsSupported
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $issuer,
        private readonly string $authorizationEndpoint,
        private readonly string $tokenEndpoint,
        private readonly array $responseTypesSupported,
        private readonly ?string $registrationEndpoint = null,
        private readonly ?array $scopesSupported = null,
        private readonly ?array $responseModesSupported = null,
        private readonly ?array $grantTypesSupported = null,
        private readonly ?array $tokenEndpointAuthMethodsSupported = null,
        private readonly ?array $tokenEndpointAuthSigningAlgValuesSupported = null,
        private readonly ?string $serviceDocumentation = null,
        private readonly ?string $revocationEndpoint = null,
        private readonly ?array $revocationEndpointAuthMethodsSupported = null,
        private readonly ?array $revocationEndpointAuthSigningAlgValuesSupported = null,
        private readonly ?string $introspectionEndpoint = null,
        private readonly ?array $introspectionEndpointAuthMethodsSupported = null,
        private readonly ?array $introspectionEndpointAuthSigningAlgValuesSupported = null,
        private readonly ?array $codeChallengeMethodsSupported = null,
        private readonly array $additionalProperties = []
    ) {
        $this->validateUrl($authorizationEndpoint, 'authorizationEndpoint');
        $this->validateUrl($tokenEndpoint, 'tokenEndpoint');
        if ($registrationEndpoint !== null) {
            $this->validateUrl($registrationEndpoint, 'registrationEndpoint');
        }
        if ($serviceDocumentation !== null) {
            $this->validateUrl($serviceDocumentation, 'serviceDocumentation');
        }
        if ($revocationEndpoint !== null) {
            $this->validateUrl($revocationEndpoint, 'revocationEndpoint');
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
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->authorizationEndpoint,
            'token_endpoint' => $this->tokenEndpoint,
            'response_types_supported' => $this->responseTypesSupported,
        ]);

        if ($this->registrationEndpoint !== null) {
            $data['registration_endpoint'] = $this->registrationEndpoint;
        }
        if ($this->scopesSupported !== null) {
            $data['scopes_supported'] = $this->scopesSupported;
        }
        if ($this->responseModesSupported !== null) {
            $data['response_modes_supported'] = $this->responseModesSupported;
        }
        if ($this->grantTypesSupported !== null) {
            $data['grant_types_supported'] = $this->grantTypesSupported;
        }
        if ($this->tokenEndpointAuthMethodsSupported !== null) {
            $data['token_endpoint_auth_methods_supported'] = $this->tokenEndpointAuthMethodsSupported;
        }
        if ($this->tokenEndpointAuthSigningAlgValuesSupported !== null) {
            $data['token_endpoint_auth_signing_alg_values_supported'] = $this->tokenEndpointAuthSigningAlgValuesSupported;
        }
        if ($this->serviceDocumentation !== null) {
            $data['service_documentation'] = $this->serviceDocumentation;
        }
        if ($this->revocationEndpoint !== null) {
            $data['revocation_endpoint'] = $this->revocationEndpoint;
        }
        if ($this->revocationEndpointAuthMethodsSupported !== null) {
            $data['revocation_endpoint_auth_methods_supported'] = $this->revocationEndpointAuthMethodsSupported;
        }
        if ($this->revocationEndpointAuthSigningAlgValuesSupported !== null) {
            $data['revocation_endpoint_auth_signing_alg_values_supported'] = $this->revocationEndpointAuthSigningAlgValuesSupported;
        }
        if ($this->introspectionEndpoint !== null) {
            $data['introspection_endpoint'] = $this->introspectionEndpoint;
        }
        if ($this->introspectionEndpointAuthMethodsSupported !== null) {
            $data['introspection_endpoint_auth_methods_supported'] = $this->introspectionEndpointAuthMethodsSupported;
        }
        if ($this->introspectionEndpointAuthSigningAlgValuesSupported !== null) {
            $data['introspection_endpoint_auth_signing_alg_values_supported'] = $this->introspectionEndpointAuthSigningAlgValuesSupported;
        }
        if ($this->codeChallengeMethodsSupported !== null) {
            $data['code_challenge_methods_supported'] = $this->codeChallengeMethodsSupported;
        }

        return $data;
    }
}
