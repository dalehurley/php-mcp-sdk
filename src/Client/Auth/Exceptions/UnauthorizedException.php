<?php

declare(strict_types=1);

namespace MCP\Client\Auth\Exceptions;

/**
 * Exception thrown when authentication is required but not provided or invalid.
 *
 * This is used for general authorization failures in the MCP client.
 */
class UnauthorizedException extends OAuthException
{
    public function __construct(
        string $message = 'Unauthorized',
        ?string $errorUri = null,
        int $code = 0,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $errorUri, $code, $previous);
    }
}
