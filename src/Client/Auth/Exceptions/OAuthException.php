<?php

declare(strict_types=1);

namespace MCP\Client\Auth\Exceptions;

use Exception;

/**
 * Base OAuth exception class.
 */
class OAuthException extends Exception
{
    public function __construct(
        string $message = '',
        private readonly ?string $errorUri = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error URI if provided by the OAuth server.
     */
    public function getErrorUri(): ?string
    {
        return $this->errorUri;
    }
}
