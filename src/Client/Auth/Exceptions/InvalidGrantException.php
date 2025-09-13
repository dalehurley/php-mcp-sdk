<?php

declare(strict_types=1);

namespace MCP\Client\Auth\Exceptions;

/**
 * Exception thrown when the authorization grant is invalid, expired, revoked, 
 * or does not match the redirection URI used in the authorization request.
 * 
 * This corresponds to the OAuth 2.0 "invalid_grant" error.
 */
class InvalidGrantException extends OAuthException {}
