<?php

declare(strict_types=1);

namespace MCP\Client\Auth\Exceptions;

/**
 * Exception thrown when the client is not authorized to request an authorization
 * code using this method.
 *
 * This corresponds to the OAuth 2.0 "unauthorized_client" error.
 */
class UnauthorizedClientException extends OAuthException
{
}
