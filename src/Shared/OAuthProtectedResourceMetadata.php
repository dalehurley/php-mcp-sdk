<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * RFC 9728 OAuth Protected Resource Metadata
 */
class OAuthProtectedResourceMetadata implements \JsonSerializable
{
    /**
     * @param array<string>|null $authorizationServers
     * @param array<string>|null $scopesSupported
     * @param array<string>|null $bearerMethodsSupported
     * @param array<string>|null $resourceSigningAlgValuesSupported
     * @param array<string>|null $authorizationDetailsTypesSupported
     * @param array<string>|null $dpopSigningAlgValuesSupported
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $resource,
        private readonly ?array $authorizationServers = null,
        private readonly ?string $jwksUri = null,
        private readonly ?array $scopesSupported = null,
        private readonly ?array $bearerMethodsSupported = null,
        private readonly ?array $resourceSigningAlgValuesSupported = null,
        private readonly ?string $resourceName = null,
        private readonly ?string $resourceDocumentation = null,
        private readonly ?string $resourcePolicyUri = null,
        private readonly ?string $resourceTosUri = null,
        private readonly ?bool $tlsClientCertificateBoundAccessTokens = null,
        private readonly ?array $authorizationDetailsTypesSupported = null,
        private readonly ?array $dpopSigningAlgValuesSupported = null,
        private readonly ?bool $dpopBoundAccessTokensRequired = null,
        private readonly array $additionalProperties = []
    ) {
        // Validate URLs
        if ($jwksUri !== null) {
            $this->validateUrl($jwksUri, 'jwksUri');
        }
        if ($resourcePolicyUri !== null) {
            $this->validateUrl($resourcePolicyUri, 'resourcePolicyUri');
        }
        if ($resourceTosUri !== null) {
            $this->validateUrl($resourceTosUri, 'resourceTosUri');
        }
        if ($authorizationServers !== null) {
            foreach ($authorizationServers as $server) {
                $this->validateUrl($server, 'authorizationServers');
            }
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
            'resource' => $this->resource,
        ]);

        if ($this->authorizationServers !== null) {
            $data['authorization_servers'] = $this->authorizationServers;
        }
        if ($this->jwksUri !== null) {
            $data['jwks_uri'] = $this->jwksUri;
        }
        if ($this->scopesSupported !== null) {
            $data['scopes_supported'] = $this->scopesSupported;
        }
        if ($this->bearerMethodsSupported !== null) {
            $data['bearer_methods_supported'] = $this->bearerMethodsSupported;
        }
        if ($this->resourceSigningAlgValuesSupported !== null) {
            $data['resource_signing_alg_values_supported'] = $this->resourceSigningAlgValuesSupported;
        }
        if ($this->resourceName !== null) {
            $data['resource_name'] = $this->resourceName;
        }
        if ($this->resourceDocumentation !== null) {
            $data['resource_documentation'] = $this->resourceDocumentation;
        }
        if ($this->resourcePolicyUri !== null) {
            $data['resource_policy_uri'] = $this->resourcePolicyUri;
        }
        if ($this->resourceTosUri !== null) {
            $data['resource_tos_uri'] = $this->resourceTosUri;
        }
        if ($this->tlsClientCertificateBoundAccessTokens !== null) {
            $data['tls_client_certificate_bound_access_tokens'] = $this->tlsClientCertificateBoundAccessTokens;
        }
        if ($this->authorizationDetailsTypesSupported !== null) {
            $data['authorization_details_types_supported'] = $this->authorizationDetailsTypesSupported;
        }
        if ($this->dpopSigningAlgValuesSupported !== null) {
            $data['dpop_signing_alg_values_supported'] = $this->dpopSigningAlgValuesSupported;
        }
        if ($this->dpopBoundAccessTokensRequired !== null) {
            $data['dpop_bound_access_tokens_required'] = $this->dpopBoundAccessTokensRequired;
        }

        return $data;
    }
}
