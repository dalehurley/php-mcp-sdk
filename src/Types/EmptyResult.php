<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * A response that indicates success but carries no data.
 * This is a strict result with only optional _meta field.
 */
final class EmptyResult extends Result
{
    /**
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(?array $_meta = null)
    {
        parent::__construct($_meta, []);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        // Only allow _meta field for EmptyResult
        $_meta = null;
        if (isset($data['_meta']) && is_array($data['_meta'])) {
            $_meta = $data['_meta'];
            unset($data['_meta']);
        }

        // Throw if there are any other properties
        if (!empty($data)) {
            throw new \InvalidArgumentException(
                'EmptyResult should not have any properties other than _meta'
            );
        }

        return new static($_meta);
    }

    /**
     * @return array{_meta?: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->getMeta() !== null) {
            $data['_meta'] = $this->getMeta();
        }

        return $data;
    }
}
