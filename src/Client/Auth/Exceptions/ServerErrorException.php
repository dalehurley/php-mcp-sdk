<?php

declare(strict_types=1);

namespace MCP\Client\Auth\Exceptions;

/**
 * Exception thrown when the authorization server encounters an unexpected
 * condition that prevented it from fulfilling the request.
 * 
 * This corresponds to the OAuth 2.0 "server_error" error.
 */
class ServerErrorException extends OAuthException {}
