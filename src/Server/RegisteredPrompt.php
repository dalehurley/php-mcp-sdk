<?php

declare(strict_types=1);

namespace MCP\Server;

/**
 * Represents a registered prompt in the MCP server.
 */
class RegisteredPrompt
{
    /**
     * @param string|null $title
     * @param string|null $description
     * @param mixed|null $argsSchema Schema for arguments validation
     * @param PromptCallback $callback
     * @param bool $enabled
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public $argsSchema,
        public PromptCallback $callback,
        public bool $enabled = true,
        private ?\Closure $onUpdate = null,
        private ?\Closure $onRemove = null
    ) {}

    /**
     * Enable the prompt
     */
    public function enable(): void
    {
        $this->enabled = true;
        ($this->onUpdate) && ($this->onUpdate)(['enabled' => true]);
    }

    /**
     * Disable the prompt
     */
    public function disable(): void
    {
        $this->enabled = false;
        ($this->onUpdate) && ($this->onUpdate)(['enabled' => false]);
    }

    /**
     * Update prompt properties
     * 
     * @param array{
     *   name?: string|null,
     *   title?: string,
     *   description?: string,
     *   argsSchema?: mixed,
     *   callback?: PromptCallback,
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

        if (array_key_exists('argsSchema', $updates)) {
            $this->argsSchema = $updates['argsSchema'];
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
     * Remove the prompt
     * 
     * @param callable $onRemove Callback to handle removal
     */
    public function remove(): void
    {
        ($this->onRemove) && ($this->onRemove)();
    }
}
