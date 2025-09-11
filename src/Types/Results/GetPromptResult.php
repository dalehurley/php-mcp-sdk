<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Result;
use MCP\Types\Prompts\PromptMessage;

/**
 * The server's response to a prompts/get request from the client.
 */
final class GetPromptResult extends Result
{
    /**
     * @param PromptMessage[] $messages
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $messages,
        private readonly ?string $description = null,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['messages']) || !is_array($data['messages'])) {
            throw new \InvalidArgumentException('GetPromptResult must have a messages array');
        }

        $messages = array_map(
            fn(array $item) => PromptMessage::fromArray($item),
            $data['messages']
        );

        return new self(
            messages: $messages,
            description: isset($data['description']) && is_string($data['description'])
                ? $data['description']
                : null,
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the messages.
     *
     * @return PromptMessage[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array{
     *     messages: array<array{role: string, content: array<string, mixed>}>,
     *     description?: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        
        $data['messages'] = array_map(
            fn(PromptMessage $message) => $message->jsonSerialize(),
            $this->messages
        );

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
