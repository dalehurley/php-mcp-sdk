<?php

declare(strict_types=1);

namespace MCP\Server\Auth;

use MCP\Server\Auth\Handlers\AuthorizeHandler;
use MCP\Server\Auth\Handlers\MetadataHandler;
use MCP\Server\Auth\Handlers\RegisterHandler;
use MCP\Server\Auth\Handlers\RevokeHandler;
use MCP\Server\Auth\Handlers\TokenHandler;
use MCP\Shared\OAuthMetadata;
use MCP\Shared\OAuthProtectedResourceMetadata;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Configuration for OAuth router.
 */
final readonly class AuthRouterOptions
{
    /**
     * @param string[] $scopesSupported
     */
    public function __construct(
        public OAuthServerProvider $provider,
        public string $issuerUrl,
        public ?string $baseUrl = null,
        public ?string $serviceDocumentationUrl = null,
        public array $scopesSupported = [],
        public ?string $resourceName = null
    ) {}
}

/**
 * Configuration for auth metadata router.
 */
final readonly class AuthMetadataOptions
{
    /**
     * @param string[] $scopesSupported
     */
    public function __construct(
        public OAuthMetadata $oauthMetadata,
        public string $resourceServerUrl,
        public ?string $serviceDocumentationUrl = null,
        public array $scopesSupported = [],
        public ?string $resourceName = null
    ) {}
}

/**
 * PSR-15 middleware that handles OAuth endpoints.
 */
final class McpAuthRouter implements MiddlewareInterface
{
    private OAuthMetadata $oauthMetadata;
    private AuthorizeHandler $authorizeHandler;
    private TokenHandler $tokenHandler;
    private ?RegisterHandler $registerHandler;
    private ?RevokeHandler $revokeHandler;
    private MetadataHandler $metadataHandler;

    public function __construct(AuthRouterOptions $options)
    {
        $this->oauthMetadata = $this->createOAuthMetadata($options);

        $this->authorizeHandler = new AuthorizeHandler($options->provider);
        $this->tokenHandler = new TokenHandler($options->provider);
        $this->registerHandler = $options->provider->getClientsStore() instanceof OAuthRegisteredClientsStore
            ? new RegisterHandler($options->provider->getClientsStore())
            : null;
        $this->revokeHandler = new RevokeHandler($options->provider);

        // Create protected resource metadata
        $protectedResourceMetadata = new OAuthProtectedResourceMetadata(
            resource: $options->resourceServerUrl ?? $this->oauthMetadata->getIssuer(),
            authorizationServers: [$this->oauthMetadata->getIssuer()],
            jwksUri: null,
            scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
            bearerMethodsSupported: null,
            resourceSigningAlgValuesSupported: null,
            resourceName: $options->resourceName,
            resourceDocumentation: $options->serviceDocumentationUrl
        );

        $this->metadataHandler = new MetadataHandler($this->oauthMetadata, $protectedResourceMetadata);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        return match ([$path, $method]) {
            ['/.well-known/mcp-oauth-metadata', 'GET'] => $this->metadataHandler->handleOAuthMetadata($request),
            ['/.well-known/oauth-protected-resource', 'GET'] => $this->metadataHandler->handleProtectedResourceMetadata($request),
            ['/.well-known/oauth-authorization-server', 'GET'] => $this->metadataHandler->handleOAuthMetadata($request), // Backwards compatibility
            ['/oauth2/authorize', 'GET'] => $this->authorizeHandler->handle($request),
            ['/oauth2/token', 'POST'] => $this->tokenHandler->handle($request),
            ['/oauth2/revoke', 'POST'] => $this->revokeHandler?->handle($request) ?? $handler->handle($request),
            ['/oauth2/register', 'POST'] => $this->registerHandler?->handle($request) ?? $handler->handle($request),
            default => $handler->handle($request)
        };
    }

    private function createOAuthMetadata(AuthRouterOptions $options): OAuthMetadata
    {
        $this->checkIssuerUrl($options->issuerUrl);

        $baseUrl = $options->baseUrl ?? $options->issuerUrl;

        return new OAuthMetadata(
            issuer: $options->issuerUrl,
            authorizationEndpoint: $this->buildUrl('/oauth2/authorize', $baseUrl),
            tokenEndpoint: $this->buildUrl('/oauth2/token', $baseUrl),
            responseTypesSupported: ['code'],
            registrationEndpoint: $this->buildUrl('/oauth2/register', $baseUrl),
            scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
            responseModesSupported: null,
            grantTypesSupported: ['authorization_code', 'refresh_token'],
            tokenEndpointAuthMethodsSupported: ['client_secret_post'],
            tokenEndpointAuthSigningAlgValuesSupported: null,
            serviceDocumentation: $options->serviceDocumentationUrl,
            revocationEndpoint: $this->buildUrl('/oauth2/revoke', $baseUrl),
            revocationEndpointAuthMethodsSupported: ['client_secret_post'],
            revocationEndpointAuthSigningAlgValuesSupported: null,
            introspectionEndpoint: null,
            introspectionEndpointAuthMethodsSupported: null,
            introspectionEndpointAuthSigningAlgValuesSupported: null,
            codeChallengeMethodsSupported: ['S256']
        );
    }

    private function checkIssuerUrl(string $issuer): void
    {
        $parsed = parse_url($issuer);

        // Technically RFC 8414 does not permit a localhost HTTPS exemption, but this is necessary for testing
        if (
            $parsed['scheme'] !== 'https' &&
            !in_array($parsed['host'] ?? '', ['localhost', '127.0.0.1'], true)
        ) {
            throw new \InvalidArgumentException('Issuer URL must be HTTPS');
        }

        if (isset($parsed['fragment'])) {
            throw new \InvalidArgumentException("Issuer URL must not have a fragment: {$issuer}");
        }

        if (isset($parsed['query'])) {
            throw new \InvalidArgumentException("Issuer URL must not have a query string: {$issuer}");
        }
    }

    private function buildUrl(string $path, string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . $path;
    }
}

/**
 * Metadata-only router for resource servers that don't provide authorization endpoints.
 */
final class McpAuthMetadataRouter implements MiddlewareInterface
{
    private MetadataHandler $metadataHandler;

    public function __construct(AuthMetadataOptions $options)
    {
        $protectedResourceMetadata = new OAuthProtectedResourceMetadata(
            resource: $options->resourceServerUrl,
            authorizationServers: [$options->oauthMetadata->getIssuer()],
            jwksUri: null,
            scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
            bearerMethodsSupported: null,
            resourceSigningAlgValuesSupported: null,
            resourceName: $options->resourceName,
            resourceDocumentation: $options->serviceDocumentationUrl
        );

        $this->metadataHandler = new MetadataHandler($options->oauthMetadata, $protectedResourceMetadata);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        return match ([$path, $method]) {
            ['/.well-known/oauth-protected-resource', 'GET'] => $this->metadataHandler->handleProtectedResourceMetadata($request),
            ['/.well-known/oauth-authorization-server', 'GET'] => $this->metadataHandler->handleOAuthMetadata($request),
            default => $handler->handle($request)
        };
    }
}

/**
 * Helper function to construct the OAuth 2.0 Protected Resource Metadata URL
 * from a given server URL. This replaces the path with the standard metadata endpoint.
 */
function getOAuthProtectedResourceMetadataUrl(string $serverUrl): string
{
    $parsed = parse_url($serverUrl);
    $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
    if (isset($parsed['port'])) {
        $baseUrl .= ':' . $parsed['port'];
    }

    return $baseUrl . '/.well-known/oauth-protected-resource';
}
