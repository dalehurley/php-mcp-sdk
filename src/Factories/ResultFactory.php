<?php

declare(strict_types=1);

namespace MCP\Factories;

use MCP\Types\EmptyResult;
use MCP\Types\Result;

/**
 * Factory for creating Result instances.
 *
 * @extends AbstractTypeFactory<Result>
 */
class ResultFactory extends AbstractTypeFactory
{
    protected function createInstance(array $data): Result
    {
        // Check if this should be an EmptyResult
        $_meta = $this->getArray($data, '_meta');
        unset($data['_meta']);

        // If only _meta exists or data is empty, create EmptyResult
        if (empty($data)) {
            return new EmptyResult($_meta);
        }

        // Otherwise create a regular Result
        return new Result($_meta, $data);
    }

    /**
     * Create an empty result.
     */
    public function createEmpty(?array $_meta = null): EmptyResult
    {
        return new EmptyResult($_meta);
    }

    /**
     * Create a result with data.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $_meta
     */
    public function createWithData(array $data, ?array $_meta = null): Result
    {
        return new Result($_meta, $data);
    }
}
