<?php

declare(strict_types=1);

namespace MCP\UI;

/**
 * Structured representation of a parsed UIResource.
 *
 * This class holds the parsed data from a UIResource content block,
 * making it easy to work with UI resources in client applications.
 *
 * @example
 * ```php
 * $data = UIResourceParser::parseResource($resource);
 * if ($data->isHtml()) {
 *     echo $data->getIframeSrcDoc();
 * }
 * ```
 */
class UIResourceData
{
    /**
     * @param string      $uri      The unique resource identifier (e.g., "ui://weather/sydney")
     * @param string      $type     The content type: 'html', 'url', 'remoteDom', or 'unknown'
     * @param string      $mimeType The original MIME type from the resource
     * @param string      $content  The actual content (HTML, URL, or script)
     * @param string      $encoding How the content was encoded: 'text' or 'blob'
     * @param string|null $flavor   For remoteDom: 'react' or 'webcomponents'
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $type,
        public readonly string $mimeType,
        public readonly string $content,
        public readonly string $encoding,
        public readonly ?string $flavor = null,
    ) {
    }

    /**
     * Get a unique ID from the URI (useful for caching/keying).
     *
     * Strips the "ui://" prefix from the URI.
     *
     * @return string The ID portion of the URI
     *
     * @example
     * ```php
     * $data = new UIResourceData('ui://weather/sydney', ...);
     * echo $data->getId(); // "weather/sydney"
     * ```
     */
    public function getId(): string
    {
        return substr($this->uri, 5); // Remove 'ui://' prefix
    }

    /**
     * Get a sanitized ID safe for use in HTML attributes.
     *
     * @return string URL-encoded ID
     */
    public function getSafeId(): string
    {
        return urlencode($this->getId());
    }

    /**
     * Check if this is renderable HTML content.
     *
     * @return bool True if this resource contains inline HTML
     */
    public function isHtml(): bool
    {
        return $this->type === 'html';
    }

    /**
     * Check if this is an external URL.
     *
     * @return bool True if this resource contains a URL to embed
     */
    public function isUrl(): bool
    {
        return $this->type === 'url';
    }

    /**
     * Check if this is a Remote DOM script.
     *
     * @return bool True if this resource contains a Remote DOM script
     */
    public function isRemoteDom(): bool
    {
        return $this->type === 'remoteDom';
    }

    /**
     * Check if the content type is unknown/unsupported.
     *
     * @return bool True if the content type is not recognized
     */
    public function isUnknown(): bool
    {
        return $this->type === 'unknown';
    }

    /**
     * Check if this resource can be rendered in an iframe.
     *
     * @return bool True if this is HTML or URL content
     */
    public function isIframeRenderable(): bool
    {
        return $this->isHtml() || $this->isUrl();
    }

    /**
     * Get the content for embedding in an iframe srcDoc attribute.
     *
     * @return string|null The HTML content, or null if not HTML type
     */
    public function getIframeSrcDoc(): ?string
    {
        return $this->isHtml() ? $this->content : null;
    }

    /**
     * Get the URL for embedding in an iframe src attribute.
     *
     * @return string|null The URL, or null if not URL type
     */
    public function getIframeSrc(): ?string
    {
        return $this->isUrl() ? $this->content : null;
    }

    /**
     * Get the Remote DOM script content.
     *
     * @return string|null The script, or null if not remoteDom type
     */
    public function getRemoteDomScript(): ?string
    {
        return $this->isRemoteDom() ? $this->content : null;
    }

    /**
     * Get the content length in bytes.
     *
     * @return int Content length
     */
    public function getContentLength(): int
    {
        return strlen($this->content);
    }

    /**
     * Check if the content was base64 encoded in the original response.
     *
     * @return bool True if content was blob-encoded
     */
    public function wasBase64Encoded(): bool
    {
        return $this->encoding === 'blob';
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed> Array representation
     */
    public function toArray(): array
    {
        $data = [
            'uri' => $this->uri,
            'id' => $this->getId(),
            'type' => $this->type,
            'mimeType' => $this->mimeType,
            'content' => $this->content,
            'encoding' => $this->encoding,
            'contentLength' => $this->getContentLength(),
        ];

        if ($this->flavor !== null) {
            $data['flavor'] = $this->flavor;
        }

        return $data;
    }

    /**
     * Convert to JSON string.
     *
     * @param int $flags JSON encoding flags
     *
     * @return string JSON representation
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Create a UIResourceData instance from an array (e.g., from JSON).
     *
     * @param array<string, mixed> $data The array data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uri: $data['uri'],
            type: $data['type'],
            mimeType: $data['mimeType'],
            content: $data['content'],
            encoding: $data['encoding'],
            flavor: $data['flavor'] ?? null,
        );
    }
}

