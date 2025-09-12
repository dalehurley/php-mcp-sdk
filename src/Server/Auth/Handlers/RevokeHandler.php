<?php

declare(strict_types=1);

namespace MCP\Server\Auth\Handlers;

use MCP\Server\Auth\Errors\InvalidRequestError;
use MCP\Server\Auth\Errors\InvalidClientError;
use MCP\Server\Auth\Errors\OAuthError;
use MCP\Server\Auth\Errors\ServerError;
use MCP\Server\Auth\OAuthServerProvider;
use MCP\Shared\OAuthTokenRevocationRequest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles OAuth token revocation requests.
 */
final class RevokeHandler
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

            if (empty($params['token'])) {
                throw new InvalidRequestError('Missing token parameter');
            }

            // Authenticate client
            $client = $this->authenticateClient($request, $params);

            // Create revocation request
            $revocationRequest = new OAuthTokenRevocationRequest(
                token: $params['token'],
                tokenTypeHint: $params['token_type_hint'] ?? null
            );

            // Revoke the token
            $revokePromise = $this->provider->revokeToken($client, $revocationRequest);
            $this->resolvePromise($revokePromise);

            // Return successful response (200 OK with empty body)
            return $this->createResponse()
                ->withStatus(200)
                ->withHeader('Cache-Control', 'no-store');
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e);
        } catch (\Throwable $e) {
            $serverError = new ServerError('Internal Server Error');
            return $this->createErrorResponse($serverError);
        }
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
