<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Result;
use MCP\Types\Content\ContentBlock;
use MCP\Types\Content\TextContent;
use MCP\Types\Content\ImageContent;
use MCP\Types\Content\AudioContent;

/**
 * The client's response to a sampling/create_message request from the server.
 * The client should inform the user before returning the sampled message, to
 * allow them to inspect the response (human in the loop) and decide whether
 * to allow the server to see it.
 */
final class CreateMessageResult extends Result
{
    /**
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly string $model,
        private readonly string $role,
        private readonly ContentBlock $content,
        private readonly ?string $stopReason = null,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['model']) || !is_string($data['model'])) {
            throw new \InvalidArgumentException('CreateMessageResult must have a model property');
        }

        if (!isset($data['role']) || !is_string($data['role'])) {
            throw new \InvalidArgumentException('CreateMessageResult must have a role property');
        }

        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new \InvalidArgumentException('CreateMessageResult must have a content property');
        }

        // For sampling results, content must be one of the specific types
        $content = match ($data['content']['type'] ?? null) {
            'text' => TextContent::fromArray($data['content']),
            'image' => ImageContent::fromArray($data['content']),
            'audio' => AudioContent::fromArray($data['content']),
            default => throw new \InvalidArgumentException('Invalid content type for CreateMessageResult'),
        };

        return new self(
            model: $data['model'],
            role: $data['role'],
            content: $content,
            stopReason: isset($data['stopReason']) && is_string($data['stopReason'])
                ? $data['stopReason']
                : null,
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the role.
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Get the content.
     */
    public function getContent(): ContentBlock
    {
        return $this->content;
    }

    /**
     * Get the stop reason.
     */
    public function getStopReason(): ?string
    {
        return $this->stopReason;
    }

    /**
     * @return array{
     *     model: string,
     *     role: string,
     *     content: array<string, mixed>,
     *     stopReason?: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['model'] = $this->model;
        $data['role'] = $this->role;
        $data['content'] = $this->content->jsonSerialize();

        if ($this->stopReason !== null) {
            $data['stopReason'] = $this->stopReason;
        }

        return $data;
    }
}
