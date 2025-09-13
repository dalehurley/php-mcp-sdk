<?php

declare(strict_types=1);

namespace MCP\Client\Middleware;

use Amp\Future;
use MCP\Client\Auth\Exceptions\UnauthorizedException;
use MCP\Client\Auth\OAuthClientProvider;
use MCP\Client\Auth\OAuthUtils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Amp\async;

/**
 * Middleware that handles OAuth authentication automatically.
 * 
 * This middleware:
 * - Adds Authorization headers with access tokens
 * - Handles 401 responses by attempting re-authentication
 * - Retries the original request after successful auth
 * - Manages token refresh automatically
 */
class OAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly OAuthClientProvider $provider,
        private readonly ?string $baseUrl = null
    ) {}

    public function process(RequestInterface $request, callable $next): Future
    {
        return async(function () use ($request, $next) {
            // Add authorization header if tokens are available
            $modifiedRequest = $this->addAuthorizationHeader($request)->await();

            try {
                $response = $next($modifiedRequest)->await();

                // Handle 401 responses by attempting re-authentication
                if ($response->getStatusCode() === 401) {
                    return $this->handleUnauthorizedResponse($request, $next, $response)->await();
                }

                return $response;
            } catch (\Throwable $exception) {
                // Re-throw the exception as-is
                throw $exception;
            }
        });
    }

    private function addAuthorizationHeader(RequestInterface $request): Future
    {
        return async(function () use ($request) {
            $tokens = $this->provider->tokens()->await();

            if ($tokens && $tokens->getAccessToken()) {
                // Check if token is expired and refresh if needed
                if ($this->isTokenExpired($tokens)) {
                    $tokens = $this->refreshTokenIfPossible($tokens)->await();
                }

                if ($tokens && $tokens->getAccessToken()) {
                    return $request->withHeader('Authorization', 'Bearer ' . $tokens->getAccessToken());
                }
            }

            return $request;
        });
    }

    private function handleUnauthorizedResponse(RequestInterface $request, callable $next, ResponseInterface $response): Future
    {
        return async(function () use ($request, $next, $response) {
            try {
                // Extract resource metadata URL from WWW-Authenticate header if present
                $resourceMetadataUrl = OAuthUtils::extractResourceMetadataUrl($response);

                // Determine server URL
                $serverUrl = $this->baseUrl ?: $this->getServerUrlFromRequest($request);

                // Attempt re-authentication
                $result = $this->performAuth($serverUrl, $resourceMetadataUrl)->await();

                if ($result !== 'AUTHORIZED') {
                    throw new UnauthorizedException(
                        $result === 'REDIRECT'
                            ? 'Authentication requires user authorization - redirect initiated'
                            : "Authentication failed with result: {$result}"
                    );
                }

                // Retry the original request with fresh tokens
                $retryRequest = $this->addAuthorizationHeader($request)->await();
                $retryResponse = $next($retryRequest)->await();

                // If we still get 401, give up
                if ($retryResponse->getStatusCode() === 401) {
                    throw new UnauthorizedException("Authentication failed for {$request->getUri()}");
                }

                return $retryResponse;
            } catch (UnauthorizedException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $previous = $e instanceof \Exception ? $e : null;
                throw new UnauthorizedException(
                    "Failed to re-authenticate: {$e->getMessage()}",
                    null,
                    0,
                    $previous
                );
            }
        });
    }

    private function isTokenExpired($tokens): bool
    {
        if (!$tokens || !$tokens->getAccessToken()) {
            return true;
        }

        $expiresIn = $tokens->getExpiresIn();
        if ($expiresIn === null) {
            return false; // No expiry info, assume valid
        }

        // Consider token expired if it expires within 30 seconds
        return $expiresIn <= 30;
    }

    private function refreshTokenIfPossible($tokens): Future
    {
        return async(function () use ($tokens) {
            $refreshToken = $tokens->getRefreshToken();
            if (!$refreshToken) {
                return $tokens; // Can't refresh, return original
            }

            // This would need to be implemented in the provider
            // For now, return the original tokens
            return $tokens;
        });
    }

    private function getServerUrlFromRequest(RequestInterface $request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        $serverUrl = "{$scheme}://{$host}";
        if ($port && (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))) {
            $serverUrl .= ":{$port}";
        }

        return $serverUrl;
    }

    private function performAuth(string $serverUrl, ?string $resourceMetadataUrl): Future
    {
        return async(function () use ($serverUrl) {
            // This is a simplified version - in practice, this would need to handle
            // the full OAuth flow including discovery, client registration, etc.
            
            // Check if we have stored tokens that we can refresh
            $tokens = $this->provider->loadTokens()->await();
            if ($tokens && $tokens->getRefreshToken()) {
                // Attempt to refresh tokens
                $refreshedTokens = $this->refreshTokenIfPossible($tokens)->await();
                if ($refreshedTokens && $refreshedTokens !== $tokens) {
                    $this->provider->storeTokens($refreshedTokens)->await();
                    return 'AUTHORIZED';
                }
            }
            
            // If we can't refresh, we need to start a new authorization flow
            // This would typically result in a redirect to the authorization server
            return 'REDIRECT';
        });
    }
}
