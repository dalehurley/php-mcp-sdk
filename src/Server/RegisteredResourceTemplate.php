<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Server\ResourceTemplate;
use MCP\Server\ResourceMetadata;

/**
 * Represents a registered resource template in the MCP server.
 */
class RegisteredResourceTemplate
{
    /**
     * @param ResourceTemplate $resourceTemplate
     * @param string|null $title
     * @param ResourceMetadata|null $metadata
     * @param ReadResourceTemplateCallback $readCallback
     * @param bool $enabled
     */
    public function __construct(
        public ResourceTemplate $resourceTemplate,
        public ?string $title,
        public ?ResourceMetadata $metadata,
        public ReadResourceTemplateCallback $readCallback,
        public bool $enabled = true,
        private ?\Closure $onUpdate = null,
        private ?\Closure $onRemove = null
    ) {}

    /**
     * Enable the resource template
     */
    public function enable(): void
    {
        $this->enabled = true;
        ($this->onUpdate) && ($this->onUpdate)(['enabled' => true]);
    }

    /**
     * Disable the resource template
     */
    public function disable(): void
    {
        $this->enabled = false;
        ($this->onUpdate) && ($this->onUpdate)(['enabled' => false]);
    }

    /**
     * Update resource template properties
     * 
     * @param array{
     *   name?: string|null,
     *   title?: string,
     *   template?: ResourceTemplate,
     *   metadata?: ResourceMetadata,
     *   callback?: ReadResourceTemplateCallback,
     *   enabled?: bool
     * } $updates
     * @param callable $onUpdate Callback to handle name changes and list updates
     */
    public function update(array $updates): void
    {
        if (array_key_exists('title', $updates)) {
            $this->title = $updates['title'];
        }

        if (array_key_exists('template', $updates)) {
            $this->resourceTemplate = $updates['template'];
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

        // Notify parent about updates (especially name changes)
        ($this->onUpdate) && ($this->onUpdate)($updates);
    }

    /**
     * Remove the resource template
     * 
     * @param callable $onRemove Callback to handle removal
     */
    public function remove(): void
    {
        ($this->onRemove) && ($this->onRemove)();
    }
}
