<?php

declare(strict_types=1);

namespace MCP\Server\Auth\Errors;

use MCP\Shared\OAuthErrorResponse;
use MCP\Types\McpError;

/**
 * Base class for OAuth errors.
 */
abstract class OAuthError extends \Exception
{
    public function __construct(
        protected readonly string $errorCode,
        string $message = '',
        protected readonly ?string $errorUri = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorUri(): ?string
    {
        return $this->errorUri;
    }

    public function toResponseObject(): OAuthErrorResponse
    {
        return new OAuthErrorResponse(
            $this->errorCode,
            $this->getMessage() ?: null,
            $this->errorUri
        );
    }
}

/**
 * OAuth server error (500).
 */
final class ServerError extends OAuthError
{
    public function __construct(string $message = 'Internal Server Error', ?\Throwable $previous = null)
    {
        parent::__construct('server_error', $message, null, $previous);
    }
}

/**
 * Invalid request error (400).
 */
final class InvalidRequestError extends OAuthError
{
    public function __construct(string $message = 'Invalid request', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_request', $message, null, $previous);
    }
}

/**
 * Invalid client error (401).
 */
final class InvalidClientError extends OAuthError
{
    public function __construct(string $message = 'Invalid client', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_client', $message, null, $previous);
    }
}

/**
 * Invalid grant error (400).
 */
final class InvalidGrantError extends OAuthError
{
    public function __construct(string $message = 'Invalid grant', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_grant', $message, null, $previous);
    }
}

/**
 * Unsupported grant type error (400).
 */
final class UnsupportedGrantTypeError extends OAuthError
{
    public function __construct(string $message = 'Unsupported grant type', ?\Throwable $previous = null)
    {
        parent::__construct('unsupported_grant_type', $message, null, $previous);
    }
}

/**
 * Invalid scope error (400).
 */
final class InvalidScopeError extends OAuthError
{
    public function __construct(string $message = 'Invalid scope', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_scope', $message, null, $previous);
    }
}

/**
 * Invalid token error (401).
 */
final class InvalidTokenError extends OAuthError
{
    public function __construct(string $message = 'Invalid token', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_token', $message, null, $previous);
    }
}

/**
 * Insufficient scope error (403).
 */
final class InsufficientScopeError extends OAuthError
{
    public function __construct(string $message = 'Insufficient scope', ?\Throwable $previous = null)
    {
        parent::__construct('insufficient_scope', $message, null, $previous);
    }
}

/**
 * Too many requests error (429).
 */
final class TooManyRequestsError extends OAuthError
{
    public function __construct(string $message = 'Too many requests', ?\Throwable $previous = null)
    {
        parent::__construct('too_many_requests', $message, null, $previous);
    }
}

/**
 * Access denied error (403).
 */
final class AccessDeniedError extends OAuthError
{
    public function __construct(string $message = 'Access denied', ?\Throwable $previous = null)
    {
        parent::__construct('access_denied', $message, null, $previous);
    }
}

/**
 * Unsupported response type error (400).
 */
final class UnsupportedResponseTypeError extends OAuthError
{
    public function __construct(string $message = 'Unsupported response type', ?\Throwable $previous = null)
    {
        parent::__construct('unsupported_response_type', $message, null, $previous);
    }
}
