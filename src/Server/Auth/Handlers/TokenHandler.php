<?php

declare(strict_types=1);

namespace MCP\Server\Auth\Handlers;

use MCP\Server\Auth\Errors\InvalidRequestError;
use MCP\Server\Auth\Errors\InvalidClientError;
use MCP\Server\Auth\Errors\InvalidGrantError;
use MCP\Server\Auth\Errors\UnsupportedGrantTypeError;
use MCP\Server\Auth\Errors\OAuthError;
use MCP\Server\Auth\Errors\ServerError;
use MCP\Server\Auth\OAuthServerProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles OAuth token requests.
 */
final class TokenHandler
{
    public function __construct(
        private readonly OAuthServerProvider $provider,
        private readonly ?ResponseFactoryInterface $responseFactory = null
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Only POST method is allowed
            if ($request->getMethod() !== 'POST') {
                throw new InvalidRequestError('Method not allowed');
            }

            // Parse request body
            $body = (string)$request->getBody();
            parse_str($body, $params);

            if (empty($params['grant_type'])) {
                throw new InvalidRequestError('Missing grant_type parameter');
            }

            // Authenticate client
            $client = $this->authenticateClient($request, $params);

            $response = $this->createResponse()
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Content-Type', 'application/json');

            switch ($params['grant_type']) {
                case 'authorization_code':
                    return $this->handleAuthorizationCodeGrant($client, $params, $response);

                case 'refresh_token':
                    return $this->handleRefreshTokenGrant($client, $params, $response);

                default:
                    throw new UnsupportedGrantTypeError('The grant type is not supported by this authorization server');
            }
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e);
        } catch (\Throwable $e) {
            $serverError = new ServerError('Internal Server Error');
            return $this->createErrorResponse($serverError);
        }
    }

    private function handleAuthorizationCodeGrant($client, array $params, ResponseInterface $response): ResponseInterface
    {
        // Validate required parameters
        if (empty($params['code'])) {
            throw new InvalidRequestError('Missing code parameter');
        }

        if (empty($params['code_verifier'])) {
            throw new InvalidRequestError('Missing code_verifier parameter');
        }

        $code = $params['code'];
        $codeVerifier = $params['code_verifier'];
        $redirectUri = $params['redirect_uri'] ?? null;
        $resource = $params['resource'] ?? null;

        // Perform local PKCE validation unless explicitly skipped
        if (!$this->provider->skipLocalPkceValidation()) {
            $challengePromise = $this->provider->challengeForAuthorizationCode($client, $code);
            $codeChallenge = $this->resolvePromise($challengePromise);

            if (!$this->verifyPkce($codeVerifier, $codeChallenge)) {
                throw new InvalidGrantError('code_verifier does not match the challenge');
            }
        }

        // Exchange authorization code for tokens
        $tokensPromise = $this->provider->exchangeAuthorizationCode(
            $client,
            $code,
            $this->provider->skipLocalPkceValidation() ? $codeVerifier : null,
            $redirectUri,
            $resource
        );

        $tokens = $this->resolvePromise($tokensPromise);
        $json = json_encode($tokens->jsonSerialize());
        $body = $this->createStream($json);

        return $response
            ->withStatus(200)
            ->withBody($body);
    }

    private function handleRefreshTokenGrant($client, array $params, ResponseInterface $response): ResponseInterface
    {
        if (empty($params['refresh_token'])) {
            throw new InvalidRequestError('Missing refresh_token parameter');
        }

        $refreshToken = $params['refresh_token'];
        $scope = $params['scope'] ?? null;
        $resource = $params['resource'] ?? null;

        $scopes = $scope ? explode(' ', $scope) : [];

        $tokensPromise = $this->provider->exchangeRefreshToken($client, $refreshToken, $scopes, $resource);
        $tokens = $this->resolvePromise($tokensPromise);

        $json = json_encode($tokens->jsonSerialize());
        $body = $this->createStream($json);

        return $response
            ->withStatus(200)
            ->withBody($body);
    }

    private function authenticateClient(ServerRequestInterface $request, array $params)
    {
        $clientId = null;
        $clientSecret = null;

        // Try client_secret_post first
        if (!empty($params['client_id'])) {
            $clientId = $params['client_id'];
            $clientSecret = $params['client_secret'] ?? null;
        }

        // Try client_secret_basic (Authorization header)
        if ($clientId === null) {
            $authHeader = $request->getHeaderLine('Authorization');
            if (str_starts_with($authHeader, 'Basic ')) {
                $credentials = base64_decode(substr($authHeader, 6));
                if ($credentials !== false && str_contains($credentials, ':')) {
                    [$clientId, $clientSecret] = explode(':', $credentials, 2);
                }
            }
        }

        if ($clientId === null) {
            throw new InvalidClientError('Client authentication failed');
        }

        // Get client from store
        $clientPromise = $this->provider->getClientsStore()->getClient($clientId);
        $client = $this->resolvePromise($clientPromise);

        if ($client === null) {
            throw new InvalidClientError('Invalid client_id');
        }

        // Verify client secret if present
        if ($client->getClientSecret() !== null && $client->getClientSecret() !== $clientSecret) {
            throw new InvalidClientError('Invalid client_secret');
        }

        return $client;
    }

    private function verifyPkce(string $codeVerifier, string $codeChallenge): bool
    {
        $hash = hash('sha256', $codeVerifier, true);
        $computedChallenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
        return hash_equals($codeChallenge, $computedChallenge);
    }

    private function createErrorResponse(OAuthError $error): ResponseInterface
    {
        $response = $this->createResponse();
        $json = json_encode($error->toResponseObject()->jsonSerialize());
        $body = $this->createStream($json);

        $status = $error instanceof ServerError ? 500 : 400;

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($body);
    }

    private function createResponse(): ResponseInterface
    {
        if ($this->responseFactory !== null) {
            return $this->responseFactory->createResponse();
        }

        // Fallback to a simple implementation
        return new class implements ResponseInterface {
            private int $statusCode = 200;
            private string $reasonPhrase = 'OK';
            private array $headers = [];
            private $body = null;
            private string $version = '1.1';

            public function getProtocolVersion(): string
            {
                return $this->version;
            }
            public function withProtocolVersion($version): ResponseInterface
            {
                $new = clone $this;
                $new->version = $version;
                return $new;
            }
            public function getHeaders(): array
            {
                return $this->headers;
            }
            public function hasHeader($name): bool
            {
                return isset($this->headers[strtolower($name)]);
            }
            public function getHeader($name): array
            {
                return $this->headers[strtolower($name)] ?? [];
            }
            public function getHeaderLine($name): string
            {
                return implode(', ', $this->getHeader($name));
            }
            public function withHeader($name, $value): ResponseInterface
            {
                $new = clone $this;
                $new->headers[strtolower($name)] = is_array($value) ? $value : [$value];
                return $new;
            }
            public function withAddedHeader($name, $value): ResponseInterface
            {
                $new = clone $this;
                $existing = $new->headers[strtolower($name)] ?? [];
                $new->headers[strtolower($name)] = array_merge($existing, is_array($value) ? $value : [$value]);
                return $new;
            }
            public function withoutHeader($name): ResponseInterface
            {
                $new = clone $this;
                unset($new->headers[strtolower($name)]);
                return $new;
            }
            public function getBody()
            {
                return $this->body;
            }
            public function withBody($body): ResponseInterface
            {
                $new = clone $this;
                $new->body = $body;
                return $new;
            }
            public function getStatusCode(): int
            {
                return $this->statusCode;
            }
            public function withStatus($code, $reasonPhrase = ''): ResponseInterface
            {
                $new = clone $this;
                $new->statusCode = $code;
                $new->reasonPhrase = $reasonPhrase;
                return $new;
            }
            public function getReasonPhrase(): string
            {
                return $this->reasonPhrase;
            }
        };
    }

    private function createStream(string $content)
    {
        return new class($content) {
            public function __construct(private string $content) {}
            public function __toString(): string
            {
                return $this->content;
            }
            public function close(): void {}
            public function detach()
            {
                return null;
            }
            public function getSize(): ?int
            {
                return strlen($this->content);
            }
            public function tell(): int
            {
                return 0;
            }
            public function eof(): bool
            {
                return true;
            }
            public function isSeekable(): bool
            {
                return false;
            }
            public function seek($offset, $whence = SEEK_SET): void {}
            public function rewind(): void {}
            public function isWritable(): bool
            {
                return false;
            }
            public function write($string): int
            {
                return 0;
            }
            public function isReadable(): bool
            {
                return true;
            }
            public function read($length): string
            {
                return $this->content;
            }
            public function getContents(): string
            {
                return $this->content;
            }
            public function getMetadata($key = null)
            {
                return null;
            }
        };
    }

    /**
     * Simplified promise resolution for demonstration.
     * In a real implementation, you'd use proper async handling.
     */
    private function resolvePromise($promise)
    {
        if (method_exists($promise, 'wait')) {
            return $promise->wait();
        }
        return $promise;
    }
}
