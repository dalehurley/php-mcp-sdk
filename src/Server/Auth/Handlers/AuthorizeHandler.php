<?php

declare(strict_types=1);

namespace MCP\Server\Auth\Handlers;

use MCP\Server\Auth\AuthorizationParams;
use MCP\Server\Auth\Errors\InvalidRequestError;
use MCP\Server\Auth\Errors\InvalidClientError;
use MCP\Server\Auth\Errors\UnsupportedResponseTypeError;
use MCP\Server\Auth\Errors\OAuthError;
use MCP\Server\Auth\Errors\ServerError;
use MCP\Server\Auth\OAuthServerProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles OAuth authorization requests.
 */
final class AuthorizeHandler
{
    public function __construct(
        private readonly OAuthServerProvider $provider,
        private readonly ?ResponseFactoryInterface $responseFactory = null
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Only GET method is allowed for authorization endpoint
            if ($request->getMethod() !== 'GET') {
                throw new InvalidRequestError('Method not allowed');
            }

            $params = $request->getQueryParams();

            // Validate required parameters
            $this->validateRequiredParams($params);

            // Get client information
            $clientId = $params['client_id'];
            $clientPromise = $this->provider->getClientsStore()->getClient($clientId);

            // For now, we'll assume synchronous operation for simplicity
            // In a real implementation, you'd need to handle the promise properly
            $client = $this->resolvePromise($clientPromise);

            if ($client === null) {
                throw new InvalidClientError('Invalid client_id');
            }

            // Validate response_type
            if ($params['response_type'] !== 'code') {
                throw new UnsupportedResponseTypeError('Only authorization_code flow is supported');
            }

            // Validate PKCE parameters
            if (empty($params['code_challenge']) || empty($params['code_challenge_method'])) {
                throw new InvalidRequestError('PKCE parameters are required');
            }

            if ($params['code_challenge_method'] !== 'S256') {
                throw new InvalidRequestError('Only S256 code_challenge_method is supported');
            }

            // Parse scopes
            $scopes = [];
            if (!empty($params['scope'])) {
                $scopes = explode(' ', $params['scope']);
            }

            // Create authorization params
            $authParams = new AuthorizationParams(
                codeChallenge: $params['code_challenge'],
                redirectUri: $params['redirect_uri'],
                state: $params['state'] ?? null,
                scopes: $scopes,
                resource: $params['resource'] ?? null
            );

            // Create a basic response object
            $response = $this->createResponse();

            // Delegate to the provider
            $authPromise = $this->provider->authorize($client, $authParams, $response);

            return $this->resolvePromise($authPromise);
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e, $params['redirect_uri'] ?? null, $params['state'] ?? null);
        } catch (\Throwable $e) {
            $serverError = new ServerError('Internal Server Error');
            return $this->createErrorResponse($serverError, $params['redirect_uri'] ?? null, $params['state'] ?? null);
        }
    }

    private function validateRequiredParams(array $params): void
    {
        $required = ['client_id', 'response_type', 'redirect_uri', 'code_challenge', 'code_challenge_method'];

        foreach ($required as $param) {
            if (empty($params[$param])) {
                throw new InvalidRequestError("Missing required parameter: {$param}");
            }
        }

        // Validate redirect_uri format
        if (!filter_var($params['redirect_uri'], FILTER_VALIDATE_URL)) {
            throw new InvalidRequestError('Invalid redirect_uri format');
        }
    }

    private function createErrorResponse(OAuthError $error, ?string $redirectUri, ?string $state): ResponseInterface
    {
        $response = $this->createResponse();

        // If we have a redirect URI, redirect with error
        if ($redirectUri !== null) {
            $params = [
                'error' => $error->getErrorCode(),
                'error_description' => $error->getMessage(),
            ];

            if ($state !== null) {
                $params['state'] = $state;
            }

            $separator = str_contains($redirectUri, '?') ? '&' : '?';
            $errorUrl = $redirectUri . $separator . http_build_query($params);

            return $response
                ->withStatus(302)
                ->withHeader('Location', $errorUrl);
        }

        // Otherwise return JSON error
        $json = json_encode($error->toResponseObject()->jsonSerialize());
        $body = $this->createStream($json);

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json')
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
