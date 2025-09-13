<?php

declare(strict_types=1);

namespace MCP\Types\Capabilities;

/**
 * Capabilities a client may support.
 * Known capabilities are defined here, but this is not a closed set:
 * any client can define its own, additional capabilities.
 */
final class ClientCapabilities implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|null $experimental
     * @param array<string, mixed>|null $sampling
     * @param array<string, mixed>|null $elicitation
     * @param array<string, mixed>|null $roots
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?array $experimental = null,
        private readonly ?array $sampling = null,
        private readonly ?array $elicitation = null,
        private readonly ?array $roots = null,
        private readonly array $additionalProperties = []
    ) {
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        $experimental = isset($data['experimental']) && is_array($data['experimental']) ? $data['experimental'] : null;
        $sampling = isset($data['sampling']) && is_array($data['sampling']) ? $data['sampling'] : null;
        $elicitation = isset($data['elicitation']) && is_array($data['elicitation']) ? $data['elicitation'] : null;
        $roots = isset($data['roots']) && is_array($data['roots']) ? $data['roots'] : null;

        // Remove known properties to collect additional properties
        unset($data['experimental'], $data['sampling'], $data['elicitation'], $data['roots']);

        return new self(
            experimental: $experimental,
            sampling: $sampling,
            elicitation: $elicitation,
            roots: $roots,
            additionalProperties: $data
        );
    }

    /**
     * Get experimental, non-standard capabilities that the client supports.
     *
     * @return array<string, mixed>|null
     */
    public function getExperimental(): ?array
    {
        return $this->experimental;
    }

    /**
     * Check if the client supports experimental capabilities.
     */
    public function hasExperimental(): bool
    {
        return $this->experimental !== null;
    }

    /**
     * Get sampling capability if the client supports sampling from an LLM.
     *
     * @return array<string, mixed>|null
     */
    public function getSampling(): ?array
    {
        return $this->sampling;
    }

    /**
     * Check if the client supports sampling.
     */
    public function hasSampling(): bool
    {
        return $this->sampling !== null;
    }

    /**
     * Get elicitation capability if the client supports eliciting user input.
     *
     * @return array<string, mixed>|null
     */
    public function getElicitation(): ?array
    {
        return $this->elicitation;
    }

    /**
     * Check if the client supports elicitation.
     */
    public function hasElicitation(): bool
    {
        return $this->elicitation !== null;
    }

    /**
     * Get roots capability if the client supports listing roots.
     *
     * @return array<string, mixed>|null
     */
    public function getRoots(): ?array
    {
        return $this->roots;
    }

    /**
     * Check if the client supports roots.
     */
    public function hasRoots(): bool
    {
        return $this->roots !== null;
    }

    /**
     * Check if the client supports roots list changed notifications.
     */
    public function supportsRootsListChanged(): bool
    {
        return $this->roots !== null
            && isset($this->roots['listChanged'])
            && $this->roots['listChanged'] === true;
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

        if ($this->experimental !== null) {
            $data['experimental'] = $this->experimental;
        }

        if ($this->sampling !== null) {
            $data['sampling'] = $this->sampling;
        }

        if ($this->elicitation !== null) {
            $data['elicitation'] = $this->elicitation;
        }

        if ($this->roots !== null) {
            $data['roots'] = $this->roots;
        }

        return $data;
    }
}
