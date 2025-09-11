<?php

declare(strict_types=1);

namespace MCP\Types\Content;

/**
 * An image provided to or from an LLM.
 */
final class ImageContent implements ContentBlock
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $data,
        private readonly string $mimeType,
        private readonly ?array $_meta = null,
        private readonly array $additionalProperties = []
    ) {
        // Validate base64 encoding
        if (!$this->isValidBase64($data)) {
            throw new \InvalidArgumentException('Image data must be valid base64 encoded');
        }
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['type']) || $data['type'] !== 'image') {
            throw new \InvalidArgumentException('ImageContent must have type "image"');
        }

        if (!isset($data['data']) || !is_string($data['data'])) {
            throw new \InvalidArgumentException('ImageContent must have a data property');
        }

        if (!isset($data['mimeType']) || !is_string($data['mimeType'])) {
            throw new \InvalidArgumentException('ImageContent must have a mimeType property');
        }

        $imageData = $data['data'];
        $mimeType = $data['mimeType'];
        $_meta = null;

        if (isset($data['_meta']) && is_array($data['_meta'])) {
            $_meta = $data['_meta'];
        }

        // Remove known properties to collect additional properties
        unset($data['type'], $data['data'], $data['mimeType'], $data['_meta']);

        return new static(
            data: $imageData,
            mimeType: $mimeType,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the content type.
     */
    public function getType(): string
    {
        return 'image';
    }

    /**
     * Get the base64-encoded image data.
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Get the MIME type of the image.
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get metadata associated with this content.
     *
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        return $this->_meta;
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
     * Validate if a string is valid base64.
     */
    private function isValidBase64(string $data): bool
    {
        // Use the same approach as TypeScript SDK: try to decode it
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
     * @return array{type: string, data: string, mimeType: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = array_merge($this->additionalProperties, [
            'type' => 'image',
            'data' => $this->data,
            'mimeType' => $this->mimeType,
        ]);

        if ($this->_meta !== null) {
            $data['_meta'] = $this->_meta;
        }

        return $data;
    }
}
