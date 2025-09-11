<?php

declare(strict_types=1);

namespace MCP\Types\Tools;

/**
 * Additional properties describing a Tool to clients.
 *
 * NOTE: all properties in ToolAnnotations are **hints**.
 * They are not guaranteed to provide a faithful description of
 * tool behavior (including descriptive properties like `title`).
 *
 * Clients should never make tool use decisions based on ToolAnnotations
 * received from untrusted servers.
 */
final class ToolAnnotations implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?string $title = null,
        private readonly ?bool $readOnlyHint = null,
        private readonly ?bool $destructiveHint = null,
        private readonly ?bool $idempotentHint = null,
        private readonly ?bool $openWorldHint = null,
        private readonly array $additionalProperties = []
    ) {}

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;
        $readOnlyHint = isset($data['readOnlyHint']) && is_bool($data['readOnlyHint']) ? $data['readOnlyHint'] : null;
        $destructiveHint = isset($data['destructiveHint']) && is_bool($data['destructiveHint']) ? $data['destructiveHint'] : null;
        $idempotentHint = isset($data['idempotentHint']) && is_bool($data['idempotentHint']) ? $data['idempotentHint'] : null;
        $openWorldHint = isset($data['openWorldHint']) && is_bool($data['openWorldHint']) ? $data['openWorldHint'] : null;

        // Remove known properties to collect additional properties
        unset(
            $data['title'],
            $data['readOnlyHint'],
            $data['destructiveHint'],
            $data['idempotentHint'],
            $data['openWorldHint']
        );

        return new self(
            title: $title,
            readOnlyHint: $readOnlyHint,
            destructiveHint: $destructiveHint,
            idempotentHint: $idempotentHint,
            openWorldHint: $openWorldHint,
            additionalProperties: $data
        );
    }

    /**
     * Get the human-readable title for the tool.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Get whether the tool does not modify its environment.
     * Default: false
     */
    public function getReadOnlyHint(): ?bool
    {
        return $this->readOnlyHint;
    }

    /**
     * Get whether the tool may perform destructive updates.
     * (Meaningful only when readOnlyHint == false)
     * Default: true
     */
    public function getDestructiveHint(): ?bool
    {
        return $this->destructiveHint;
    }

    /**
     * Get whether calling the tool repeatedly with the same arguments
     * will have no additional effect.
     * (Meaningful only when readOnlyHint == false)
     * Default: false
     */
    public function getIdempotentHint(): ?bool
    {
        return $this->idempotentHint;
    }

    /**
     * Get whether this tool may interact with an "open world" of external entities.
     * Default: true
     */
    public function getOpenWorldHint(): ?bool
    {
        return $this->openWorldHint;
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
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = $this->additionalProperties;

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->readOnlyHint !== null) {
            $data['readOnlyHint'] = $this->readOnlyHint;
        }

        if ($this->destructiveHint !== null) {
            $data['destructiveHint'] = $this->destructiveHint;
        }

        if ($this->idempotentHint !== null) {
            $data['idempotentHint'] = $this->idempotentHint;
        }

        if ($this->openWorldHint !== null) {
            $data['openWorldHint'] = $this->openWorldHint;
        }

        return $data;
    }
}
