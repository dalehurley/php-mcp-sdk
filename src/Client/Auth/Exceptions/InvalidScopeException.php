<?php

declare(strict_types=1);

namespace MCP\Client\Auth\Exceptions;

/**
 * Exception thrown when the requested scope is invalid, unknown, malformed,
 * or exceeds the scope granted by the resource owner.
 *
 * This corresponds to the OAuth 2.0 "invalid_scope" error.
 */
class InvalidScopeException extends OAuthException
{
}
