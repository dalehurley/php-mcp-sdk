<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Result;

/**
 * The server's response to a completion/complete request.
 */
final class CompleteResult extends Result
{
    /**
     * @param string[] $values
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly array $values,
        private readonly ?int $total = null,
        private readonly ?bool $hasMore = null,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
        
        if (count($this->values) > 100) {
            throw new \InvalidArgumentException('Completion values must not exceed 100 items');
        }
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['completion']) || !is_array($data['completion'])) {
            throw new \InvalidArgumentException('CompleteResult must have a completion property');
        }

        $completion = $data['completion'];
        
        if (!isset($completion['values']) || !is_array($completion['values'])) {
            throw new \InvalidArgumentException('CompleteResult completion must have a values array');
        }

        // Ensure all values are strings
        $values = array_filter($completion['values'], 'is_string');

        return new self(
            values: $values,
            total: isset($completion['total']) && is_int($completion['total'])
                ? $completion['total']
                : null,
            hasMore: isset($completion['hasMore']) && is_bool($completion['hasMore'])
                ? $completion['hasMore']
                : null,
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the completion values.
     *
     * @return string[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get the total number of completion options available.
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    /**
     * Check if there are more completion options available.
     */
    public function hasMore(): ?bool
    {
        return $this->hasMore;
    }

    /**
     * @return array{
     *     completion: array{
     *         values: string[],
     *         total?: int,
     *         hasMore?: bool
     *     },
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        
        $completion = ['values' => $this->values];

        if ($this->total !== null) {
            $completion['total'] = $this->total;
        }

        if ($this->hasMore !== null) {
            $completion['hasMore'] = $this->hasMore;
        }

        $data['completion'] = $completion;

        return $data;
    }
}
