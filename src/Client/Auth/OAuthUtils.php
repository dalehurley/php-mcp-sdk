<?php

declare(strict_types=1);

namespace MCP\Client\Auth;

use function Amp\async;

use Amp\Future;
use MCP\Client\Auth\Exceptions\InvalidClientException;
use MCP\Client\Auth\Exceptions\InvalidGrantException;
use MCP\Client\Auth\Exceptions\InvalidScopeException;
use MCP\Client\Auth\Exceptions\OAuthException;
use MCP\Client\Auth\Exceptions\ServerErrorException;
use MCP\Client\Auth\Exceptions\UnauthorizedClientException;
use MCP\Client\Auth\Exceptions\UnsupportedGrantTypeException;
use MCP\Shared\OAuthClientInformation;
use MCP\Shared\OAuthProtectedResourceMetadata;
use MCP\Types\Protocol;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

use Psr\Http\Message\StreamFactoryInterface;

/**
 * OAuth utilities for PKCE, JWT parsing, metadata discovery, and error handling.
 */
class OAuthUtils
{
    /**
     * OAuth error code to exception class mapping.
     */
    private const ERROR_MAP = [
        'invalid_client' => InvalidClientException::class,
        'invalid_grant' => InvalidGrantException::class,
        'unauthorized_client' => UnauthorizedClientException::class,
        'unsupported_grant_type' => UnsupportedGrantTypeException::class,
        'invalid_scope' => InvalidScopeException::class,
        'server_error' => ServerErrorException::class,
    ];

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
    }

    /**
     * Generate a PKCE code verifier according to RFC 7636.
     */
    public static function generateCodeVerifier(): string
    {
        // Generate 32 random bytes and encode as URL-safe base64
        $bytes = random_bytes(32);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Generate a PKCE code challenge from a verifier according to RFC 7636.
     */
    public static function generateCodeChallenge(string $verifier): string
    {
        $hash = hash('sha256', $verifier, true);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Generate a cryptographically secure state parameter.
     */
    public static function generateState(): string
    {
        $bytes = random_bytes(16);

        return bin2hex($bytes);
    }

    /**
     * Parse a JWT token without verification (for extracting claims).
     *
     * WARNING: This method does NOT verify the token signature.
     * Only use for extracting non-sensitive claims from trusted sources.
     */
    public static function parseJwtClaims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        // Decode the payload (second part)
        $payload = $parts[1];
        // Add padding if needed
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        $decoded = base64_decode(strtr($payload, '-_', '+/'));
        if ($decoded === false) {
            return null;
        }

        $claims = json_decode($decoded, true);

        return is_array($claims) ? $claims : null;
    }

    /**
     * Check if a JWT token is expired based on the 'exp' claim.
     */
    public static function isJwtExpired(string $jwt, int $clockSkew = 300): bool
    {
        $claims = self::parseJwtClaims($jwt);
        if (!$claims || !isset($claims['exp'])) {
            return true; // Assume expired if we can't parse or no exp claim
        }

        return time() >= ($claims['exp'] - $clockSkew);
    }

    /**
     * Discover OAuth authorization server metadata according to RFC 8414.
     */
    public function discoverAuthorizationServerMetadata(
        string $serverUrl,
        ?string $protocolVersion = null
    ): Future {
        return async(function () use ($serverUrl, $protocolVersion) {
            $protocolVersion ??= Protocol::LATEST_PROTOCOL_VERSION;

            // Try RFC 8414 OAuth Authorization Server Metadata first
            $urls = $this->buildDiscoveryUrls($serverUrl);

            foreach ($urls as $url) {
                try {
                    $request = $this->requestFactory
                        ->createRequest('GET', $url)
                        ->withHeader('MCP-Protocol-Version', $protocolVersion)
                        ->withHeader('Accept', 'application/json');

                    $response = $this->httpClient->sendRequest($request);

                    if ($response->getStatusCode() === 200) {
                        $data = json_decode((string) $response->getBody(), true);
                        if (is_array($data)) {
                            return $data;
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next URL
                    continue;
                }
            }

            return null;
        });
    }

    /**
     * Discover OAuth protected resource metadata according to RFC 9728.
     */
    public function discoverProtectedResourceMetadata(
        string $serverUrl,
        ?string $resourceMetadataUrl = null,
        ?string $protocolVersion = null
    ): Future {
        return async(function () use ($serverUrl, $resourceMetadataUrl, $protocolVersion) {
            $protocolVersion ??= Protocol::LATEST_PROTOCOL_VERSION;

            if ($resourceMetadataUrl) {
                $url = $resourceMetadataUrl;
            } else {
                $parsedUrl = parse_url($serverUrl);
                $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                if (isset($parsedUrl['port'])) {
                    $baseUrl .= ':' . $parsedUrl['port'];
                }
                $url = $baseUrl . '/.well-known/oauth-protected-resource';
            }

            try {
                $request = $this->requestFactory
                    ->createRequest('GET', $url)
                    ->withHeader('MCP-Protocol-Version', $protocolVersion)
                    ->withHeader('Accept', 'application/json');

                $response = $this->httpClient->sendRequest($request);

                if ($response->getStatusCode() === 200) {
                    $data = json_decode((string) $response->getBody(), true);
                    if (is_array($data)) {
                        return OAuthProtectedResourceMetadata::fromArray($data);
                    }
                }
            } catch (\Exception $e) {
                // Return null on any error
            }

            return null;
        });
    }

    /**
     * Build discovery URLs for OAuth metadata.
     */
    private function buildDiscoveryUrls(string $serverUrl): array
    {
        $parsedUrl = parse_url($serverUrl);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $baseUrl .= ':' . $parsedUrl['port'];
        }

        $path = $parsedUrl['path'] ?? '/';
        $urls = [];

        // Try path-specific discovery first if not root
        if ($path !== '/') {
            $urls[] = $baseUrl . '/.well-known/oauth-authorization-server' . rtrim($path, '/');
        }

        // Try root discovery
        $urls[] = $baseUrl . '/.well-known/oauth-authorization-server';

        // Try OpenID Connect discovery as fallback
        if ($path !== '/') {
            $urls[] = $baseUrl . '/.well-known/openid-configuration' . rtrim($path, '/');
        }
        $urls[] = $baseUrl . '/.well-known/openid-configuration';

        return $urls;
    }

    /**
     * Extract resource metadata URL from WWW-Authenticate header.
     */
    public static function extractResourceMetadataUrl(ResponseInterface $response): ?string
    {
        $authenticateHeader = $response->getHeaderLine('WWW-Authenticate');
        if (empty($authenticateHeader)) {
            return null;
        }

        // Parse Bearer authentication challenge
        if (!str_starts_with(strtolower($authenticateHeader), 'bearer ')) {
            return null;
        }

        // Extract resource_metadata parameter
        if (preg_match('/resource_metadata="([^"]*)"/', $authenticateHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate that a resource URL is allowed for the given server.
     */
    public static function validateResourceUrl(string $serverUrl, string $resourceUrl): bool
    {
        $serverParsed = parse_url($serverUrl);
        $resourceParsed = parse_url($resourceUrl);

        if (!$serverParsed || !$resourceParsed) {
            return false;
        }

        // Must have same scheme and host
        if (
            $serverParsed['scheme'] !== $resourceParsed['scheme'] ||
            $serverParsed['host'] !== $resourceParsed['host']
        ) {
            return false;
        }

        // Port must match (or both be default)
        $serverPort = $serverParsed['port'] ?? ($serverParsed['scheme'] === 'https' ? 443 : 80);
        $resourcePort = $resourceParsed['port'] ?? ($resourceParsed['scheme'] === 'https' ? 443 : 80);

        return $serverPort === $resourcePort;
    }

    /**
     * Get the resource URL from a server URL according to MCP specification.
     */
    public static function getResourceUrlFromServerUrl(string $serverUrl): string
    {
        $parsedUrl = parse_url($serverUrl);
        $resourceUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        if (isset($parsedUrl['port'])) {
            $resourceUrl .= ':' . $parsedUrl['port'];
        }

        $resourceUrl .= '/';

        return $resourceUrl;
    }

    /**
     * Select the appropriate client authentication method based on server support.
     */
    public static function selectClientAuthMethod(
        OAuthClientInformation $clientInfo,
        array $supportedMethods
    ): string {
        $hasClientSecret = $clientInfo->getClientSecret() !== null;

        // If server doesn't specify supported methods, use RFC 6749 defaults
        if (empty($supportedMethods)) {
            return $hasClientSecret ? 'client_secret_post' : 'none';
        }

        // Try methods in priority order (most secure first)
        if ($hasClientSecret && in_array('client_secret_basic', $supportedMethods, true)) {
            return 'client_secret_basic';
        }

        if ($hasClientSecret && in_array('client_secret_post', $supportedMethods, true)) {
            return 'client_secret_post';
        }

        if (in_array('none', $supportedMethods, true)) {
            return 'none';
        }

        // Fallback
        return $hasClientSecret ? 'client_secret_post' : 'none';
    }

    /**
     * Apply client authentication to request headers and parameters.
     */
    public static function applyClientAuthentication(
        string $method,
        OAuthClientInformation $clientInfo,
        array &$headers,
        array &$params
    ): void {
        $clientId = $clientInfo->getClientId();
        $clientSecret = $clientInfo->getClientSecret();

        switch ($method) {
            case 'client_secret_basic':
                if (!$clientSecret) {
                    throw new \InvalidArgumentException('client_secret_basic requires a client secret');
                }
                $credentials = base64_encode($clientId . ':' . $clientSecret);
                $headers['Authorization'] = 'Basic ' . $credentials;
                break;

            case 'client_secret_post':
                $params['client_id'] = $clientId;
                if ($clientSecret) {
                    $params['client_secret'] = $clientSecret;
                }
                break;

            case 'none':
                $params['client_id'] = $clientId;
                break;

            default:
                throw new \InvalidArgumentException("Unsupported client authentication method: {$method}");
        }
    }

    /**
     * Parse OAuth error response and throw appropriate exception.
     */
    public static function parseErrorResponse(ResponseInterface $response): OAuthException
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true);
            if (is_array($data) && isset($data['error'])) {
                $error = $data['error'];
                $description = $data['error_description'] ?? '';
                $uri = $data['error_uri'] ?? null;

                $exceptionClass = self::ERROR_MAP[$error] ?? OAuthException::class;

                return new $exceptionClass($description, $uri, $statusCode);
            }
        } catch (\JsonException $e) {
            // Fall through to generic error
        }

        // Generic error if we can't parse the OAuth error
        $message = "HTTP {$statusCode}: Invalid OAuth error response. Raw body: {$body}";

        return new OAuthException($message, null, $statusCode);
    }

    /**
     * Register OAuth client dynamically according to RFC 7591.
     */
    public function registerClient(
        string $registrationEndpoint,
        array $clientMetadata
    ): Future {
        return async(function () use ($registrationEndpoint, $clientMetadata) {
            $request = $this->requestFactory
                ->createRequest('POST', $registrationEndpoint)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Accept', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($clientMetadata)));

            $response = $this->httpClient->sendRequest($request);

            if (!$response->getStatusCode() === 201) {
                throw self::parseErrorResponse($response);
            }

            $data = json_decode((string) $response->getBody(), true);
            if (!is_array($data)) {
                throw new OAuthException('Invalid JSON response from registration endpoint');
            }

            return $data;
        });
    }
}
