<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Result;

/**
 * The client's response to an elicitation/create request from the server.
 */
final class ElicitResult extends Result
{
    /**
     * @param array<string, mixed>|null $content
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly string $action,
        private readonly ?array $content = null,
        ?array $_meta = null
    ) {
        if (!in_array($action, ['accept', 'decline', 'cancel'], true)) {
            throw new \InvalidArgumentException('Action must be one of: accept, decline, cancel');
        }

        if ($action === 'accept' && $content === null) {
            throw new \InvalidArgumentException('Content is required when action is accept');
        }

        parent::__construct($_meta);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['action']) || !is_string($data['action'])) {
            throw new \InvalidArgumentException('ElicitResult must have an action property');
        }

        $content = null;
        if (isset($data['content']) && is_array($data['content'])) {
            $content = $data['content'];
        }

        return new self(
            action: $data['action'],
            content: $content,
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the user's response action.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get the collected user input content.
     *
     * @return array<string, mixed>|null
     */
    public function getContent(): ?array
    {
        return $this->content;
    }

    /**
     * Check if the user accepted.
     */
    public function isAccepted(): bool
    {
        return $this->action === 'accept';
    }

    /**
     * Check if the user declined.
     */
    public function isDeclined(): bool
    {
        return $this->action === 'decline';
    }

    /**
     * Check if the user cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->action === 'cancel';
    }

    /**
     * @return array{
     *     action: string,
     *     content?: array<string, mixed>,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['action'] = $this->action;

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        return $data;
    }
}
