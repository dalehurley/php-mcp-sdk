<?php

declare(strict_types=1);

namespace MCP\Server;

/**
 * Represents a registered static resource in the MCP server.
 */
class RegisteredResource
{
    /**
     * @param string $name
     * @param string|null $title
     * @param ResourceMetadata|null $metadata
     * @param ReadResourceCallback $readCallback
     * @param bool $enabled
     */
    public function __construct(
        public string $name,
        public ?string $title,
        public ?ResourceMetadata $metadata,
        public ReadResourceCallback $readCallback,
        public bool $enabled = true,
        private ?\Closure $onUpdate = null,
        private ?\Closure $onRemove = null
    ) {
    }

    /**
     * Enable the resource.
     */
    public function enable(): void
    {
        $this->enabled = true;
        ($this->onUpdate) && ($this->onUpdate)(['enabled' => true]);
    }

    /**
     * Disable the resource.
     */
    public function disable(): void
    {
        $this->enabled = false;
        ($this->onUpdate) && ($this->onUpdate)(['enabled' => false]);
    }

    /**
     * Update resource properties.
     *
     * @param array{
     *   name?: string,
     *   title?: string,
     *   uri?: string|null,
     *   metadata?: ResourceMetadata,
     *   callback?: ReadResourceCallback,
     *   enabled?: bool
     * } $updates
     * @param callable $onUpdate Callback to handle URI changes and list updates
     */
    public function update(array $updates): void
    {
        if (array_key_exists('name', $updates)) {
            $this->name = $updates['name'];
        }

        if (array_key_exists('title', $updates)) {
            $this->title = $updates['title'];
        }

        if (array_key_exists('metadata', $updates)) {
            $this->metadata = $updates['metadata'];
        }

        if (array_key_exists('callback', $updates)) {
            $this->readCallback = $updates['callback'];
        }

        if (array_key_exists('enabled', $updates)) {
            $this->enabled = $updates['enabled'];
        }

        // Notify parent about updates (especially URI changes)
        ($this->onUpdate) && ($this->onUpdate)($updates);
    }

    /**
     * Remove the resource.
     *
     * @param callable $onRemove Callback to handle removal
     */
    public function remove(): void
    {
        ($this->onRemove) && ($this->onRemove)();
    }
}
