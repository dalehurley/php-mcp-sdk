<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Describes the name and version of an MCP implementation.
 */
class Implementation
{
    public function __construct(
        private string $name,
        private string $version,
        private ?string $title = null
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getTitle(): ?string
    {
        return $this->title ?? null;
    }

    /**
     * Convert to array for JSON serialization.
     * @return array{name: string, version: string, title?: string}
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'version' => $this->version,
        ];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        return $data;
    }

    /**
     * Create from array.
     * @param array{name?: string, version?: string, title?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'] ?? '',
            $data['version'] ?? '',
            $data['title'] ?? null
        );
    }
}
