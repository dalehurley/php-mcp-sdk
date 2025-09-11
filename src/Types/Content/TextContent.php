<?php

declare(strict_types=1);

namespace MCP\Types\Content;

/**
 * Text provided to or from an LLM.
 */
final class TextContent implements ContentBlock
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $text,
        private readonly ?array $_meta = null,
        private readonly array $additionalProperties = []
    ) {}

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['type']) || $data['type'] !== 'text') {
            throw new \InvalidArgumentException('TextContent must have type "text"');
        }

        if (!isset($data['text']) || !is_string($data['text'])) {
            throw new \InvalidArgumentException('TextContent must have a text property');
        }

        $text = $data['text'];
        $_meta = null;

        if (isset($data['_meta']) && is_array($data['_meta'])) {
            $_meta = $data['_meta'];
        }

        // Remove known properties to collect additional properties
        unset($data['type'], $data['text'], $data['_meta']);

        return new static(
            text: $text,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the content type.
     */
    public function getType(): string
    {
        return 'text';
    }

    /**
     * Get the text content.
     */
    public function getText(): string
    {
        return $this->text;
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
     * @return array{type: string, text: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = array_merge($this->additionalProperties, [
            'type' => 'text',
            'text' => $this->text,
        ]);

        if ($this->_meta !== null) {
            $data['_meta'] = $this->_meta;
        }

        return $data;
    }
}
