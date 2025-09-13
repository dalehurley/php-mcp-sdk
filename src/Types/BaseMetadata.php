<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Base metadata interface for common properties across resources, tools, prompts, and implementations.
 */
abstract class BaseMetadata implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $name,
        private readonly ?string $title = null,
        private readonly ?array $_meta = null,
        private readonly array $additionalProperties = []
    ) {
    }

    /**
     * Get the name intended for programmatic or logical use.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the title intended for UI and end-user contexts.
     * If not provided, the name should be used for display.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Get the display name (title if available, otherwise name).
     */
    public function getDisplayName(): string
    {
        return $this->title ?? $this->name;
    }

    /**
     * Get metadata associated with this object.
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
     * @return array{name: string, title?: string, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = array_merge($this->additionalProperties, [
            'name' => $this->name,
        ]);

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->_meta !== null) {
            $data['_meta'] = $this->_meta;
        }

        return $data;
    }
}
