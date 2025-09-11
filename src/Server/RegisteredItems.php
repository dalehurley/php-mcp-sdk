<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Shared\RequestHandlerExtra;
use MCP\Types\ServerRequest;
use MCP\Types\ServerNotification;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\Tools\ToolAnnotations;

/**
 * Callback for a tool handler.
 * 
 * @template Args
 * @param Args $args The parsed and validated arguments (if schema provided)
 * @param RequestHandlerExtra<ServerRequest, ServerNotification> $extra
 * @return CallToolResult|\Amp\Future<CallToolResult>
 */
interface ToolCallback
{
    /**
     * @param mixed $argsOrExtra Either the parsed arguments or the RequestHandlerExtra (if no args schema)
     * @param RequestHandlerExtra|null $extra Only provided if args schema exists
     */
    public function __invoke($argsOrExtra, $extra = null);
}

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
        public bool $enabled = true
    ) {}

    /**
     * Enable the tool
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable the tool
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Update tool properties
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
    public function update(array $updates, callable $onUpdate): void
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
        $onUpdate($updates);
    }

    /**
     * Remove the tool
     * 
     * @param callable $onRemove Callback to handle removal
     */
    public function remove(callable $onRemove): void
    {
        $onRemove();
    }
}

/**
 * Callback to read a resource at a given URI.
 * 
 * @param \URL|string $uri The resource URI
 * @param RequestHandlerExtra<ServerRequest, ServerNotification> $extra
 * @return ReadResourceResult|\Amp\Future<ReadResourceResult>
 */
interface ReadResourceCallback
{
    public function __invoke($uri, RequestHandlerExtra $extra);
}

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
        public bool $enabled = true
    ) {}

    /**
     * Enable the resource
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable the resource
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Update resource properties
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
    public function update(array $updates, callable $onUpdate): void
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
        $onUpdate($updates);
    }

    /**
     * Remove the resource
     * 
     * @param callable $onRemove Callback to handle removal
     */
    public function remove(callable $onRemove): void
    {
        $onRemove();
    }
}

/**
 * Callback to read a resource at a given URI, following a filled-in URI template.
 * 
 * @param \URL|string $uri The resource URI
 * @param array<string, string> $variables The template variables
 * @param RequestHandlerExtra<ServerRequest, ServerNotification> $extra
 * @return ReadResourceResult|\Amp\Future<ReadResourceResult>
 */
interface ReadResourceTemplateCallback
{
    public function __invoke($uri, array $variables, RequestHandlerExtra $extra);
}

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
        public bool $enabled = true
    ) {}

    /**
     * Enable the resource template
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable the resource template
     */
    public function disable(): void
    {
        $this->enabled = false;
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
    public function update(array $updates, callable $onUpdate): void
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
        $onUpdate($updates);
    }

    /**
     * Remove the resource template
     * 
     * @param callable $onRemove Callback to handle removal
     */
    public function remove(callable $onRemove): void
    {
        $onRemove();
    }
}

/**
 * Callback for a prompt handler.
 * 
 * @template Args
 * @param Args $args The parsed and validated arguments (if schema provided)
 * @param RequestHandlerExtra<ServerRequest, ServerNotification> $extra
 * @return GetPromptResult|\Amp\Future<GetPromptResult>
 */
interface PromptCallback
{
    /**
     * @param mixed $argsOrExtra Either the parsed arguments or the RequestHandlerExtra (if no args schema)
     * @param RequestHandlerExtra|null $extra Only provided if args schema exists
     */
    public function __invoke($argsOrExtra, $extra = null);
}

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
        public bool $enabled = true
    ) {}

    /**
     * Enable the prompt
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable the prompt
     */
    public function disable(): void
    {
        $this->enabled = false;
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
    public function update(array $updates, callable $onUpdate): void
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
        $onUpdate($updates);
    }

    /**
     * Remove the prompt
     * 
     * @param callable $onRemove Callback to handle removal
     */
    public function remove(callable $onRemove): void
    {
        $onRemove();
    }
}
