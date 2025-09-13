<?php

declare(strict_types=1);

namespace MCP\Types\References;

/**
 * A reference to a resource or resource template definition.
 */
final class ResourceTemplateReference implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $additional Additional properties
     */
    public function __construct(
        private readonly string $uri,
        private readonly array $additional = []
    ) {
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type']) || $data['type'] !== 'ref/resource') {
            throw new \InvalidArgumentException('ResourceTemplateReference must have type "ref/resource"');
        }

        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException('ResourceTemplateReference must have a uri property');
        }

        // Collect additional properties
        $additional = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['type', 'uri'], true)) {
                $additional[$key] = $value;
            }
        }

        return new self($data['uri'], $additional);
    }

    /**
     * Get the URI or URI template.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            [
                'type' => 'ref/resource',
                'uri' => $this->uri,
            ],
            $this->additional
        );
    }
}
