<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * OAuth 2.1 error response.
 */
class OAuthErrorResponse implements \JsonSerializable
{
    public function __construct(
        private readonly string $error,
        private readonly ?string $errorDescription = null,
        private readonly ?string $errorUri = null
    ) {
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getErrorDescription(): ?string
    {
        return $this->errorDescription;
    }

    public function getErrorUri(): ?string
    {
        return $this->errorUri;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'error' => $this->error,
        ];

        if ($this->errorDescription !== null) {
            $data['error_description'] = $this->errorDescription;
        }
        if ($this->errorUri !== null) {
            $data['error_uri'] = $this->errorUri;
        }

        return $data;
    }
}
