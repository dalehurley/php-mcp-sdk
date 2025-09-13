<?php

declare(strict_types=1);

namespace MCP\Server\Auth\Middleware;

use MCP\Server\Auth\AuthInfo;
use MCP\Server\Auth\Errors\InsufficientScopeError;
use MCP\Server\Auth\Errors\InvalidTokenError;
use MCP\Server\Auth\Errors\OAuthError;
use MCP\Server\Auth\Errors\ServerError;
use MCP\Server\Auth\OAuthTokenVerifier;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that requires a valid Bearer token in the Authorization header.
 *
 * This will validate the token with the auth provider and add the resulting auth info to the request attributes.
 */
final class BearerAuthMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $requiredScopes
     */
    public function __construct(
        private readonly OAuthTokenVerifier $verifier,
        private readonly array $requiredScopes = [],
        private readonly ?string $resourceMetadataUrl = null,
        private readonly ?ResponseFactoryInterface $responseFactory = null
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            $authHeader = $request->getHeaderLine('Authorization');
            if (empty($authHeader)) {
                throw new InvalidTokenError('Missing Authorization header');
            }

            $parts = explode(' ', $authHeader, 2);
            if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer') {
                throw new InvalidTokenError("Invalid Authorization header format, expected 'Bearer TOKEN'");
            }

            $token = $parts[1];
            if (empty($token)) {
                throw new InvalidTokenError("Invalid Authorization header format, expected 'Bearer TOKEN'");
            }

            // Verify the access token
            $authInfoPromise = $this->verifier->verifyAccessToken($token);
            $authInfo = $this->resolvePromise($authInfoPromise);

            // Check if token has the required scopes (if any)
            if (!empty($this->requiredScopes)) {
                $hasAllScopes = array_diff($this->requiredScopes, $authInfo->getScopes()) === [];

                if (!$hasAllScopes) {
                    throw new InsufficientScopeError('Insufficient scope');
                }
            }

            // Check if the token is expired
            $expiresAt = $authInfo->getExpiresAt();
            if ($expiresAt === null) {
                throw new InvalidTokenError('Token has no expiration time');
            }

            if ($expiresAt < time()) {
                throw new InvalidTokenError('Token has expired');
            }

            // Add auth info to request attributes
            $request = $request->withAttribute('authInfo', $authInfo);

            return $handler->handle($request);
        } catch (InvalidTokenError $e) {
            return $this->createUnauthorizedResponse($e);
        } catch (InsufficientScopeError $e) {
            return $this->createForbiddenResponse($e);
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e, 400);
        } catch (\Throwable $e) {
            $serverError = new ServerError('Internal Server Error');
            return $this->createErrorResponse($serverError, 500);
        }
    }

    private function createUnauthorizedResponse(InvalidTokenError $error): ResponseInterface
    {
        $response = $this->createResponse();

        $wwwAuthValue = $this->resourceMetadataUrl !== null
            ? sprintf(
                'Bearer error="%s", error_description="%s", resource_metadata="%s"',
                $error->getErrorCode(),
                $error->getMessage(),
                $this->resourceMetadataUrl
            )
            : sprintf(
                'Bearer error="%s", error_description="%s"',
                $error->getErrorCode(),
                $error->getMessage()
            );

        $json = json_encode($error->toResponseObject()->jsonSerialize());
        $body = $this->createStream($json);

        return $response
            ->withStatus(401)
            ->withHeader('WWW-Authenticate', $wwwAuthValue)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    private function createForbiddenResponse(InsufficientScopeError $error): ResponseInterface
    {
        $response = $this->createResponse();

        $wwwAuthValue = $this->resourceMetadataUrl !== null
            ? sprintf(
                'Bearer error="%s", error_description="%s", resource_metadata="%s"',
                $error->getErrorCode(),
                $error->getMessage(),
                $this->resourceMetadataUrl
            )
            : sprintf(
                'Bearer error="%s", error_description="%s"',
                $error->getErrorCode(),
                $error->getMessage()
            );

        $json = json_encode($error->toResponseObject()->jsonSerialize());
        $body = $this->createStream($json);

        return $response
            ->withStatus(403)
            ->withHeader('WWW-Authenticate', $wwwAuthValue)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    private function createErrorResponse(OAuthError $error, int $statusCode): ResponseInterface
    {
        $response = $this->createResponse();
        $json = json_encode($error->toResponseObject()->jsonSerialize());
        $body = $this->createStream($json);

        return $response
            ->withStatus($statusCode)
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
