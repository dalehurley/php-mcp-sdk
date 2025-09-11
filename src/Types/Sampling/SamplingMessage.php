<?php

declare(strict_types=1);

namespace MCP\Types\Sampling;

use MCP\Types\Content\AudioContent;
use MCP\Types\Content\ContentBlockFactory;
use MCP\Types\Content\ImageContent;
use MCP\Types\Content\TextContent;

/**
 * Describes a message issued to or received from an LLM API.
 */
final class SamplingMessage implements \JsonSerializable
{
    /**
     * @param 'user'|'assistant' $role
     * @param TextContent|ImageContent|AudioContent $content
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly string $role,
        private readonly TextContent|ImageContent|AudioContent $content,
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
            throw new \InvalidArgumentException('SamplingMessage must have a role property');
        }

        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new \InvalidArgumentException('SamplingMessage must have a content property');
        }

        $role = $data['role'];
        $contentBlock = ContentBlockFactory::fromArray($data['content']);

        // Validate content type - must be text, image, or audio
        if (
            !($contentBlock instanceof TextContent)
            && !($contentBlock instanceof ImageContent)
            && !($contentBlock instanceof AudioContent)
        ) {
            throw new \InvalidArgumentException(
                'SamplingMessage content must be TextContent, ImageContent, or AudioContent'
            );
        }

        // Remove known properties to collect additional properties
        unset($data['role'], $data['content']);

        return new self(
            role: $role,
            content: $contentBlock,
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
     *
     * @return TextContent|ImageContent|AudioContent
     */
    public function getContent(): TextContent|ImageContent|AudioContent
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
     * Check if the content is text.
     */
    public function hasTextContent(): bool
    {
        return $this->content instanceof TextContent;
    }

    /**
     * Check if the content is an image.
     */
    public function hasImageContent(): bool
    {
        return $this->content instanceof ImageContent;
    }

    /**
     * Check if the content is audio.
     */
    public function hasAudioContent(): bool
    {
        return $this->content instanceof AudioContent;
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
