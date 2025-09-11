<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Progress information for long-running operations.
 */
final class Progress implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $additional Additional properties
     */
    public function __construct(
        private readonly float $progress,
        private readonly ?float $total = null,
        private readonly ?string $message = null,
        private readonly array $additional = []
    ) {
        if ($progress < 0) {
            throw new \InvalidArgumentException('Progress cannot be negative');
        }
        
        if ($total !== null && $total < 0) {
            throw new \InvalidArgumentException('Total cannot be negative');
        }
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['progress']) || !is_numeric($data['progress'])) {
            throw new \InvalidArgumentException('Progress must have a numeric progress property');
        }

        $progress = (float) $data['progress'];
        $total = null;
        $message = null;
        $additional = [];

        if (isset($data['total']) && is_numeric($data['total'])) {
            $total = (float) $data['total'];
        }

        if (isset($data['message']) && is_string($data['message'])) {
            $message = $data['message'];
        }

        // Collect additional properties
        foreach ($data as $key => $value) {
            if (!in_array($key, ['progress', 'total', 'message'], true)) {
                $additional[$key] = $value;
            }
        }

        return new self($progress, $total, $message, $additional);
    }

    /**
     * Get the progress value.
     */
    public function getProgress(): float
    {
        return $this->progress;
    }

    /**
     * Get the total value.
     */
    public function getTotal(): ?float
    {
        return $this->total;
    }

    /**
     * Get the message.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Get the completion percentage (0-100).
     * Returns null if total is not set or is 0.
     */
    public function getPercentage(): ?float
    {
        if ($this->total === null || $this->total === 0.0) {
            return null;
        }

        return min(100.0, ($this->progress / $this->total) * 100.0);
    }

    /**
     * Check if the progress is complete.
     * Returns true if progress >= total (when total is set).
     */
    public function isComplete(): bool
    {
        if ($this->total === null) {
            return false;
        }

        return $this->progress >= $this->total;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = array_merge(
            ['progress' => $this->progress],
            $this->additional
        );

        if ($this->total !== null) {
            $data['total'] = $this->total;
        }

        if ($this->message !== null) {
            $data['message'] = $this->message;
        }

        return $data;
    }
}
