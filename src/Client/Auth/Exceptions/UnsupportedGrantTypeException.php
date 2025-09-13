<?php

declare(strict_types=1);

namespace MCP\Client\Auth\Exceptions;

/**
 * Exception thrown when the authorization grant type is not supported by the
 * authorization server.
 *
 * This corresponds to the OAuth 2.0 "unsupported_grant_type" error.
 */
class UnsupportedGrantTypeException extends OAuthException
{
}
