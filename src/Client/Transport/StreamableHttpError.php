<?php

declare(strict_types=1);

namespace MCP\Client\Transport;

/**
 * Error class for Streamable HTTP transport errors.
 */
class StreamableHttpError extends \RuntimeException
{
    public function __construct(
        ?int $code,
        string $message
    ) {
        parent::__construct("Streamable HTTP error: $message", $code ?? 0);
    }
}
