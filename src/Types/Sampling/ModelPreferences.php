<?php

declare(strict_types=1);

namespace MCP\Types\Sampling;

/**
 * The server's preferences for model selection, requested of the client during sampling.
 */
final class ModelPreferences implements \JsonSerializable
{
    /**
     * @param ModelHint[]|null $hints
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?array $hints = null,
        private readonly ?float $costPriority = null,
        private readonly ?float $speedPriority = null,
        private readonly ?float $intelligencePriority = null,
        private readonly array $additionalProperties = []
    ) {
        // Validate priorities are between 0 and 1
        if ($costPriority !== null && ($costPriority < 0 || $costPriority > 1)) {
            throw new \InvalidArgumentException('Cost priority must be between 0 and 1');
        }
        if ($speedPriority !== null && ($speedPriority < 0 || $speedPriority > 1)) {
            throw new \InvalidArgumentException('Speed priority must be between 0 and 1');
        }
        if ($intelligencePriority !== null && ($intelligencePriority < 0 || $intelligencePriority > 1)) {
            throw new \InvalidArgumentException('Intelligence priority must be between 0 and 1');
        }
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        $hints = null;
        if (isset($data['hints']) && is_array($data['hints'])) {
            $hints = array_map(
                fn (array $hint) => ModelHint::fromArray($hint),
                $data['hints']
            );
        }

        $costPriority = null;
        if (isset($data['costPriority'])) {
            if (!is_float($data['costPriority']) && !is_int($data['costPriority'])) {
                throw new \InvalidArgumentException('Cost priority must be a number');
            }
            $costPriority = (float) $data['costPriority'];
        }

        $speedPriority = null;
        if (isset($data['speedPriority'])) {
            if (!is_float($data['speedPriority']) && !is_int($data['speedPriority'])) {
                throw new \InvalidArgumentException('Speed priority must be a number');
            }
            $speedPriority = (float) $data['speedPriority'];
        }

        $intelligencePriority = null;
        if (isset($data['intelligencePriority'])) {
            if (!is_float($data['intelligencePriority']) && !is_int($data['intelligencePriority'])) {
                throw new \InvalidArgumentException('Intelligence priority must be a number');
            }
            $intelligencePriority = (float) $data['intelligencePriority'];
        }

        // Remove known properties to collect additional properties
        unset($data['hints'], $data['costPriority'], $data['speedPriority'], $data['intelligencePriority']);

        return new self(
            hints: $hints,
            costPriority: $costPriority,
            speedPriority: $speedPriority,
            intelligencePriority: $intelligencePriority,
            additionalProperties: $data
        );
    }

    /**
     * Get optional hints to use for model selection.
     *
     * @return ModelHint[]|null
     */
    public function getHints(): ?array
    {
        return $this->hints;
    }

    /**
     * Get how much to prioritize cost when selecting a model.
     * Value between 0 and 1.
     */
    public function getCostPriority(): ?float
    {
        return $this->costPriority;
    }

    /**
     * Get how much to prioritize sampling speed (latency) when selecting a model.
     * Value between 0 and 1.
     */
    public function getSpeedPriority(): ?float
    {
        return $this->speedPriority;
    }

    /**
     * Get how much to prioritize intelligence and capabilities when selecting a model.
     * Value between 0 and 1.
     */
    public function getIntelligencePriority(): ?float
    {
        return $this->intelligencePriority;
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

        if ($this->hints !== null) {
            $data['hints'] = array_map(
                fn (ModelHint $hint) => $hint->jsonSerialize(),
                $this->hints
            );
        }

        if ($this->costPriority !== null) {
            $data['costPriority'] = $this->costPriority;
        }

        if ($this->speedPriority !== null) {
            $data['speedPriority'] = $this->speedPriority;
        }

        if ($this->intelligencePriority !== null) {
            $data['intelligencePriority'] = $this->intelligencePriority;
        }

        return $data;
    }
}
