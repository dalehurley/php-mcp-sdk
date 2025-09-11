<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * Utilities for handling OAuth resource URIs and authentication.
 */
class AuthUtils
{
    /**
     * Converts a server URL to a resource URL by removing the fragment.
     * RFC 8707 section 2 states that resource URIs "MUST NOT include a fragment component".
     * Keeps everything else unchanged (scheme, domain, port, path, query).
     * 
     * @param string|\Psr\Http\Message\UriInterface $url
     * @return \Psr\Http\Message\UriInterface
     */
    public static function resourceUrlFromServerUrl(string|\Psr\Http\Message\UriInterface $url): \Psr\Http\Message\UriInterface
    {
        if (is_string($url)) {
            $url = new \GuzzleHttp\Psr7\Uri($url);
        } else {
            // Clone to avoid modifying the original
            $url = new \GuzzleHttp\Psr7\Uri((string) $url);
        }

        // Remove fragment
        return $url->withFragment('');
    }

    /**
     * Checks if a requested resource URL matches a configured resource URL.
     * A requested resource matches if it has the same scheme, domain, port,
     * and its path starts with the configured resource's path.
     *
     * @param array{requestedResource: string|\Psr\Http\Message\UriInterface, configuredResource: string|\Psr\Http\Message\UriInterface} $params
     * @return bool true if the requested resource matches the configured resource, false otherwise
     */
    public static function checkResourceAllowed(array $params): bool
    {
        $requested = $params['requestedResource'];
        $configured = $params['configuredResource'];

        if (is_string($requested)) {
            $requested = new \GuzzleHttp\Psr7\Uri($requested);
        } else {
            // Clone to avoid issues
            $requested = new \GuzzleHttp\Psr7\Uri((string) $requested);
        }

        if (is_string($configured)) {
            $configured = new \GuzzleHttp\Psr7\Uri($configured);
        } else {
            // Clone to avoid issues
            $configured = new \GuzzleHttp\Psr7\Uri((string) $configured);
        }

        // Compare the origin (scheme, domain, and port)
        $requestedOrigin = $requested->getScheme() . '://' . $requested->getAuthority();
        $configuredOrigin = $configured->getScheme() . '://' . $configured->getAuthority();

        if ($requestedOrigin !== $configuredOrigin) {
            return false;
        }

        $requestedPath = $requested->getPath();
        $configuredPath = $configured->getPath();

        // Normalize paths by ensuring they end with / for proper comparison
        // This ensures that if we have paths like "/api" and "/api/users",
        // we properly detect that "/api/users" is a subpath of "/api"
        // By adding a trailing slash if missing, we avoid false positives
        // where paths like "/api123" would incorrectly match "/api"
        $requestedPath = str_ends_with($requestedPath, '/') ? $requestedPath : $requestedPath . '/';
        $configuredPath = str_ends_with($configuredPath, '/') ? $configuredPath : $configuredPath . '/';

        return str_starts_with($requestedPath, $configuredPath);
    }

    /**
     * Generate a cryptographically secure random string for OAuth state parameter
     * or PKCE code verifier.
     * 
     * @param int $length The length of the random string to generate
     * @return string Base64url-encoded random string
     */
    public static function generateRandomString(int $length = 32): string
    {
        $bytes = random_bytes($length);
        // Use base64url encoding (URL-safe base64)
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Generate a PKCE code verifier.
     * The code verifier is a cryptographically random string using the characters
     * A-Z, a-z, 0-9, and the punctuation characters -._~ (hyphen, period, underscore, and tilde),
     * between 43 and 128 characters long.
     * 
     * @return string
     */
    public static function generateCodeVerifier(): string
    {
        // Generate 32 bytes = 256 bits of randomness
        // When base64url encoded, this gives us 43 characters
        return self::generateRandomString(32);
    }

    /**
     * Generate a PKCE code challenge from a code verifier.
     * Uses SHA256 as the challenge method.
     * 
     * @param string $codeVerifier
     * @return string Base64url-encoded SHA256 hash of the code verifier
     */
    public static function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        // Use base64url encoding
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Generate an OAuth state parameter.
     * The state parameter is used to maintain state between the request and the callback
     * and provides protection against CSRF attacks.
     * 
     * @return string
     */
    public static function generateState(): string
    {
        // Generate 16 bytes = 128 bits of randomness
        return self::generateRandomString(16);
    }

    /**
     * Parse a JWT token without verification.
     * This is useful for extracting claims from a token without verifying the signature.
     * 
     * WARNING: This does NOT verify the token signature or validate the token.
     * It should only be used when the token has already been verified by another process.
     * 
     * @param string $token The JWT token
     * @return array{header: array<string, mixed>, payload: array<string, mixed>}|null
     */
    public static function parseJwtWithoutVerification(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        try {
            $header = json_decode(self::base64UrlDecode($parts[0]), true);
            $payload = json_decode(self::base64UrlDecode($parts[1]), true);

            if (!is_array($header) || !is_array($payload)) {
                return null;
            }

            return [
                'header' => $header,
                'payload' => $payload
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract claims from a JWT token without verification.
     * 
     * @param string $token
     * @return array<string, mixed>|null The payload claims or null if invalid
     */
    public static function extractJwtClaims(string $token): ?array
    {
        $parsed = self::parseJwtWithoutVerification($token);
        return $parsed['payload'] ?? null;
    }

    /**
     * Check if a JWT token is expired based on the exp claim.
     * 
     * @param string $token
     * @return bool True if expired, false if not expired or no exp claim
     */
    public static function isJwtExpired(string $token): bool
    {
        $claims = self::extractJwtClaims($token);
        if (!$claims || !isset($claims['exp'])) {
            return false;
        }

        return time() > (int) $claims['exp'];
    }

    /**
     * Base64url decode
     * 
     * @param string $input
     * @return string
     */
    private static function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Build an OAuth authorization URL with the given parameters.
     * 
     * @param string $authorizationEndpoint
     * @param array<string, string> $params Query parameters (client_id, redirect_uri, etc.)
     * @return string
     */
    public static function buildAuthorizationUrl(string $authorizationEndpoint, array $params): string
    {
        $uri = new \GuzzleHttp\Psr7\Uri($authorizationEndpoint);

        // Merge with existing query parameters if any
        parse_str($uri->getQuery(), $existingParams);
        $allParams = array_merge($existingParams, $params);

        return (string) $uri->withQuery(http_build_query($allParams));
    }
}
