<?php

declare(strict_types=1);

namespace MCP\Types\Resources;

/**
 * Blob (binary) contents of a resource.
 */
final class BlobResourceContents extends ResourceContents
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        string $uri,
        private readonly string $blob,
        ?string $mimeType = null,
        ?array $_meta = null,
        array $additionalProperties = []
    ) {
        if (!$this->isValidBase64($blob)) {
            throw new \InvalidArgumentException('Blob data must be valid base64 encoded');
        }

        parent::__construct($uri, $mimeType, $_meta, $additionalProperties);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException('BlobResourceContents must have a uri property');
        }

        if (!isset($data['blob']) || !is_string($data['blob'])) {
            throw new \InvalidArgumentException('BlobResourceContents must have a blob property');
        }

        $uri = $data['uri'];
        $blob = $data['blob'];
        $mimeType = isset($data['mimeType']) && is_string($data['mimeType']) ? $data['mimeType'] : null;
        $_meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;

        // Remove known properties to collect additional properties
        unset($data['uri'], $data['blob'], $data['mimeType'], $data['_meta']);

        return new static(
            uri: $uri,
            blob: $blob,
            mimeType: $mimeType,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the base64-encoded binary data of the item.
     */
    public function getBlob(): string
    {
        return $this->blob;
    }

    /**
     * Validate if a string is valid base64.
     */
    private function isValidBase64(string $data): bool
    {
        try {
            $decoded = base64_decode($data, true);
            if ($decoded === false) {
                return false;
            }
            // Re-encode and compare to check if it's valid base64
            return base64_encode($decoded) === $data;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @return array{uri: string, blob: string, mimeType?: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = array_merge($this->getAdditionalProperties(), [
            'uri' => $this->getUri(),
            'blob' => $this->blob,
        ]);

        if ($this->getMimeType() !== null) {
            $data['mimeType'] = $this->getMimeType();
        }

        if ($this->getMeta() !== null) {
            $data['_meta'] = $this->getMeta();
        }

        return $data;
    }
}
