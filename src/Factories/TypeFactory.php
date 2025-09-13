<?php

declare(strict_types=1);

namespace MCP\Factories;

use MCP\Validation\ValidationException;

/**
 * Interface for type factories.
 *
 * @template T of object
 */
interface TypeFactory
{
    /**
     * Create an instance from array data.
     *
     * @param array<string, mixed> $data
     * @return T
     * @throws ValidationException
     */
    public function create(array $data): object;

    /**
     * Create multiple instances from array data.
     *
     * @param array<array<string, mixed>> $dataArray
     * @return T[]
     * @throws ValidationException
     */
    public function createMultiple(array $dataArray): array;

    /**
     * Validate data before creating an instance.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public function validate(array $data): void;
}
