<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Types\Tools\ToolAnnotations;

/**
 * Represents a registered tool in the MCP server.
 */
class RegisteredTool
{
    /**
     * @param string|null $title
     * @param string|null $description
     * @param mixed|null $inputSchema Schema for input validation (e.g., array schema definition)
     * @param mixed|null $outputSchema Schema for output validation
     * @param ToolAnnotations|null $annotations
     * @param ToolCallback $callback
     * @param bool $enabled
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public $inputSchema,
        public $outputSchema,
        public ?ToolAnnotations $annotations,
        public ToolCallback $callback,
        public bool $enabled = true,
        private ?\Closure $onUpdate = null,
        private ?\Closure $onRemove = null
    ) {
    }

    /**
     * Enable the tool.
     */
    public function enable(): void
    {
        $this->enabled = true;
        // Trigger update callback for list_changed notifications
        ($this->onUpdate) && ($this->onUpdate)(['enabled' => true]);
    }

    /**
     * Disable the tool.
     */
    public function disable(): void
    {
        $this->enabled = false;
        // Trigger update callback for list_changed notifications
        ($this->onUpdate) && ($this->onUpdate)(['enabled' => false]);
    }

    /**
     * Update tool properties.
     *
     * @param array{
     *   name?: string|null,
     *   title?: string,
     *   description?: string,
     *   paramsSchema?: mixed,
     *   outputSchema?: mixed,
     *   annotations?: ToolAnnotations,
     *   callback?: ToolCallback,
     *   enabled?: bool
     * } $updates
     * @param callable $onUpdate Callback to handle name changes and list updates
     */
    public function update(array $updates): void
    {
        if (array_key_exists('title', $updates)) {
            $this->title = $updates['title'];
        }

        if (array_key_exists('description', $updates)) {
            $this->description = $updates['description'];
        }

        if (array_key_exists('paramsSchema', $updates)) {
            $this->inputSchema = $updates['paramsSchema'];
        }

        if (array_key_exists('outputSchema', $updates)) {
            $this->outputSchema = $updates['outputSchema'];
        }

        if (array_key_exists('annotations', $updates)) {
            $this->annotations = $updates['annotations'];
        }

        if (array_key_exists('callback', $updates)) {
            $this->callback = $updates['callback'];
        }

        if (array_key_exists('enabled', $updates)) {
            $this->enabled = $updates['enabled'];
        }

        // Notify parent about updates (especially name changes)
        ($this->onUpdate) && ($this->onUpdate)($updates);
    }

    /**
     * Remove the tool.
     *
     * @param callable $onRemove Callback to handle removal
     */
    public function remove(): void
    {
        ($this->onRemove) && ($this->onRemove)();
    }
}
