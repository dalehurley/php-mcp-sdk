<?php

declare(strict_types=1);

namespace MCP\Server\Auth\Handlers;

use MCP\Server\Auth\Errors\InvalidRequestError;
use MCP\Server\Auth\Errors\OAuthError;
use MCP\Server\Auth\Errors\ServerError;
use MCP\Server\Auth\OAuthRegisteredClientsStore;
use MCP\Shared\OAuthClientInformation;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles OAuth dynamic client registration requests.
 */
final class RegisterHandler
{
    public function __construct(
        private readonly OAuthRegisteredClientsStore $clientsStore,
        private readonly ?ResponseFactoryInterface $responseFactory = null
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Only POST method is allowed
            if ($request->getMethod() !== 'POST') {
                throw new InvalidRequestError('Method not allowed');
            }

            // Parse JSON request body
            $body = (string)$request->getBody();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidRequestError('Invalid JSON in request body');
            }

            if (!is_array($data)) {
                throw new InvalidRequestError('Request body must be a JSON object');
            }

            // Validate client metadata
            $this->validateClientMetadata($data);

            // Generate client credentials
            $clientId = $this->generateClientId();
            $clientSecret = $this->generateClientSecret();

            // Create client information
            $client = new OAuthClientInformation(
                clientId: $clientId,
                clientSecret: $clientSecret,
                clientIdIssuedAt: time(),
                clientSecretExpiresAt: null // Never expires for simplicity
            );

            // Register the client
            $registerPromise = $this->clientsStore->registerClient($client);
            $registeredClient = $this->resolvePromise($registerPromise);

            // Return the registered client information
            $json = json_encode($registeredClient->jsonSerialize());
            $body = $this->createStream($json);

            return $this->createResponse()
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Cache-Control', 'no-store')
                ->withBody($body);
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e);
        } catch (\Throwable $e) {
            $serverError = new ServerError('Internal Server Error');
            return $this->createErrorResponse($serverError);
        }
    }

    private function validateClientMetadata(array $data): void
    {
        // Basic validation - in a real implementation you'd validate more fields
        // like redirect_uris, grant_types, response_types, etc.

        if (isset($data['redirect_uris'])) {
            if (!is_array($data['redirect_uris'])) {
                throw new InvalidRequestError('redirect_uris must be an array');
            }

            foreach ($data['redirect_uris'] as $uri) {
                if (!is_string($uri) || !filter_var($uri, FILTER_VALIDATE_URL)) {
                    throw new InvalidRequestError('Invalid redirect_uri format');
                }
            }
        }

        if (isset($data['client_name']) && !is_string($data['client_name'])) {
            throw new InvalidRequestError('client_name must be a string');
        }

        if (isset($data['scope']) && !is_string($data['scope'])) {
            throw new InvalidRequestError('scope must be a string');
        }
    }

    private function generateClientId(): string
    {
        return 'client_' . bin2hex(random_bytes(16));
    }

    private function generateClientSecret(): string
    {
        return bin2hex(random_bytes(32));
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
        return new class ($content) {
            public function __construct(private string $content)
            {
            }
            public function __toString(): string
            {
                return $this->content;
            }
            public function close(): void
            {
            }
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
            public function seek($offset, $whence = SEEK_SET): void
            {
            }
            public function rewind(): void
            {
            }
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
