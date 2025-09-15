<?php

declare(strict_types=1);

namespace MCP\Server;

/**
 * Metadata for a resource, including optional title, description, and MIME type.
 * This is used to provide additional information about resources beyond their URI.
 *
 * @see \MCP\Types\Resources\Resource
 */
class ResourceMetadata
{
    /**
     * @param string|null $title Optional display title for the resource
     * @param string|null $description Optional description of the resource
     * @param string|null $mimeType Optional MIME type of the resource
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $mimeType = null
    ) {
    }

    /**
     * Convert to array for merging with resource data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }

        return $data;
    }
}
