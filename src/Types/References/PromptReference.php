<?php

declare(strict_types=1);

namespace MCP\Types\References;

/**
 * Identifies a prompt.
 */
final class PromptReference implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $additional Additional properties
     */
    public function __construct(
        private readonly string $name,
        private readonly array $additional = []
    ) {}

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type']) || $data['type'] !== 'ref/prompt') {
            throw new \InvalidArgumentException('PromptReference must have type "ref/prompt"');
        }

        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('PromptReference must have a name property');
        }

        // Collect additional properties
        $additional = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['type', 'name'], true)) {
                $additional[$key] = $value;
            }
        }

        return new self($data['name'], $additional);
    }

    /**
     * Get the prompt name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            [
                'type' => 'ref/prompt',
                'name' => $this->name,
            ],
            $this->additional
        );
    }
}
