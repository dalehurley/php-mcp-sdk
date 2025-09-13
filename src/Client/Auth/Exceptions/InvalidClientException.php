<?php

declare(strict_types=1);

namespace MCP\Client\Auth\Exceptions;

/**
 * Exception thrown when client authentication fails.
 * 
 * This corresponds to the OAuth 2.0 "invalid_client" error.
 */
class InvalidClientException extends OAuthException {}
