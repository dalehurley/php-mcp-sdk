<?php

declare(strict_types=1);

namespace MCP\Types\Prompts;

use MCP\Types\BaseMetadata;

/**
 * A prompt or prompt template that the server offers.
 */
final class Prompt extends BaseMetadata
{
    /**
     * @param PromptArgument[]|null $arguments
     * @param array<string, mixed>|null $_meta
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        string $name,
        ?string $title = null,
        private readonly ?string $description = null,
        private readonly ?array $arguments = null,
        ?array $_meta = null,
        array $additionalProperties = []
    ) {
        parent::__construct($name, $title, $_meta, $additionalProperties);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('Prompt must have a name property');
        }

        $name = $data['name'];
        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;

        $arguments = null;
        if (isset($data['arguments']) && is_array($data['arguments'])) {
            $arguments = array_map(
                fn(array $arg) => PromptArgument::fromArray($arg),
                $data['arguments']
            );
        }

        $_meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;

        // Remove known properties to collect additional properties
        unset($data['name'], $data['title'], $data['description'], $data['arguments'], $data['_meta']);

        return new static(
            name: $name,
            title: $title,
            description: $description,
            arguments: $arguments,
            _meta: $_meta,
            additionalProperties: $data
        );
    }

    /**
     * Get an optional description of what this prompt provides.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the list of arguments to use for templating the prompt.
     *
     * @return PromptArgument[]|null
     */
    public function getArguments(): ?array
    {
        return $this->arguments;
    }

    /**
     * Check if this prompt has arguments.
     */
    public function hasArguments(): bool
    {
        return $this->arguments !== null && count($this->arguments) > 0;
    }

    /**
     * Get an argument by name.
     */
    public function getArgument(string $name): ?PromptArgument
    {
        if ($this->arguments === null) {
            return null;
        }

        foreach ($this->arguments as $argument) {
            if ($argument->getName() === $name) {
                return $argument;
            }
        }

        return null;
    }

    /**
     * Get the names of all arguments.
     *
     * @return string[]
     */
    public function getArgumentNames(): array
    {
        if ($this->arguments === null) {
            return [];
        }

        return array_map(
            fn(PromptArgument $arg) => $arg->getName(),
            $this->arguments
        );
    }

    /**
     * Get the names of required arguments.
     *
     * @return string[]
     */
    public function getRequiredArgumentNames(): array
    {
        if ($this->arguments === null) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn(PromptArgument $arg) => $arg->isRequired() ? $arg->getName() : null,
                $this->arguments
            )
        ));
    }

    /**
     * @return array{name: string, title?: string, description?: string, arguments?: array<array<string, mixed>>, _meta?: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->arguments !== null) {
            $data['arguments'] = array_map(
                fn(PromptArgument $arg) => $arg->jsonSerialize(),
                $this->arguments
            );
        }

        return $data;
    }
}
