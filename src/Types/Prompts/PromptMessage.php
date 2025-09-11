<?php

declare(strict_types=1);

namespace MCP\Types\Prompts;

use MCP\Types\Content\ContentBlock;
use MCP\Types\Content\ContentBlockFactory;

/**
 * Describes a message returned as part of a prompt.
 */
final class PromptMessage implements \JsonSerializable
{
    /**
     * @param 'user'|'assistant' $role
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $role,
        private readonly ContentBlock $content,
        private readonly array $additionalProperties = []
    ) {
        if (!in_array($role, ['user', 'assistant'], true)) {
            throw new \InvalidArgumentException('Role must be either "user" or "assistant"');
        }
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['role']) || !is_string($data['role'])) {
            throw new \InvalidArgumentException('PromptMessage must have a role property');
        }

        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new \InvalidArgumentException('PromptMessage must have a content property');
        }

        $role = $data['role'];
        $content = ContentBlockFactory::fromArray($data['content']);

        // Remove known properties to collect additional properties
        unset($data['role'], $data['content']);

        return new self(
            role: $role,
            content: $content,
            additionalProperties: $data
        );
    }

    /**
     * Get the role of the message sender.
     *
     * @return 'user'|'assistant'
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Get the content of the message.
     */
    public function getContent(): ContentBlock
    {
        return $this->content;
    }

    /**
     * Check if this is a user message.
     */
    public function isUserMessage(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if this is an assistant message.
     */
    public function isAssistantMessage(): bool
    {
        return $this->role === 'assistant';
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
     * @return array{role: string, content: array<string, mixed>, ...}
     */
    public function jsonSerialize(): array
    {
        return array_merge($this->additionalProperties, [
            'role' => $this->role,
            'content' => $this->content->jsonSerialize(),
        ]);
    }
}
