<?php

declare(strict_types=1);

namespace MCP\Types\Resources;

/**
 * Text contents of a resource.
 */
final class TextResourceContents extends ResourceContents
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        string $uri,
        private readonly string $text,
        ?string $mimeType = null,
        ?array $_meta = null,
        array $additionalProperties = []
    ) {
        parent::__construct($uri, $mimeType, $_meta, $additionalProperties);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException('TextResourceContents must have a uri property');
        }

        if (!isset($data['text']) || !is_string($data['text'])) {
            throw new \InvalidArgumentException('TextResourceContents must have a text property');
        }

        $uri = $data['uri'];
        $text = $data['text'];
        $mimeType = isset($data['mimeType']) && is_string($data['mimeType']) ? $data['mimeType'] : null;
        $_meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;

        // Remove known properties to collect additional properties
        unset($data['uri'], $data['text'], $data['mimeType'], $data['_meta']);

        return new static(
            uri: $uri,
            text: $text,
            mimeType: $mimeType,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get the text of the item.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return array{uri: string, text: string, mimeType?: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = array_merge($this->getAdditionalProperties(), [
            'uri' => $this->getUri(),
            'text' => $this->text,
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
