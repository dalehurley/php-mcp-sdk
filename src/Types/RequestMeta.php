<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Metadata for requests, including optional progress tracking.
 */
final class RequestMeta implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?ProgressToken $progressToken = null,
        private readonly array $additionalProperties = []
    ) {
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        $progressToken = null;
        if (isset($data['progressToken'])) {
            $progressToken = ProgressToken::from($data['progressToken']);
            unset($data['progressToken']);
        }

        return new self(
            progressToken: $progressToken,
            additionalProperties: $data
        );
    }

    /**
     * Get the progress token, if specified.
     */
    public function getProgressToken(): ?ProgressToken
    {
        return $this->progressToken;
    }

    /**
     * Get additional properties.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalProperties(): array
    {
        return $this->additionalProperties;
    }

    /**
     * Get a specific additional property.
     */
    public function getAdditionalProperty(string $key): mixed
    {
        return $this->additionalProperties[$key] ?? null;
    }

    /**
     * Check if this metadata has a progress token.
     */
    public function hasProgressToken(): bool
    {
        return $this->progressToken !== null;
    }

    /**
     * Create a new instance with a progress token.
     */
    public function withProgressToken(ProgressToken $progressToken): self
    {
        return new self(
            progressToken: $progressToken,
            additionalProperties: $this->additionalProperties
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = $this->additionalProperties;

        if ($this->progressToken !== null) {
            $data['progressToken'] = $this->progressToken->jsonSerialize();
        }

        return $data;
    }
}
