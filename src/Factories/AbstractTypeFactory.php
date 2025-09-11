<?php

declare(strict_types=1);

namespace MCP\Factories;

use MCP\Validation\ValidationException;
use MCP\Validation\ValidationService;

/**
 * Abstract base class for type factories.
 *
 * @template T
 * @implements TypeFactory<T>
 */
abstract class AbstractTypeFactory implements TypeFactory
{
    public function __construct(
        protected readonly ?ValidationService $validationService = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function create(array $data): object
    {
        $this->validate($data);
        return $this->createInstance($data);
    }

    /**
     * {@inheritdoc}
     */
    public function createMultiple(array $dataArray): array
    {
        return array_map(
            fn(array $data) => $this->create($data),
            $dataArray
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data): void
    {
        if ($this->validationService !== null && $this->getValidatorType() !== null) {
            $this->validationService->validate($this->getValidatorType(), $data);
        }
    }

    /**
     * Create an instance from validated data.
     *
     * @param array<string, mixed> $data
     * @return T
     */
    abstract protected function createInstance(array $data): object;

    /**
     * Get the validator type name for this factory.
     * Return null if no validation should be performed.
     */
    protected function getValidatorType(): ?string
    {
        return null;
    }

    /**
     * Extract a value from data with a default.
     *
     * @template V
     * @param array<string, mixed> $data
     * @param V|null $default
     * @return V|null
     */
    protected function getValue(array $data, string $key, mixed $default = null): mixed
    {
        return $data[$key] ?? $default;
    }

    /**
     * Extract a string value from data.
     *
     * @param array<string, mixed> $data
     */
    protected function getString(array $data, string $key, ?string $default = null): ?string
    {
        $value = $this->getValue($data, $key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * Extract a boolean value from data.
     *
     * @param array<string, mixed> $data
     */
    protected function getBool(array $data, string $key, ?bool $default = null): ?bool
    {
        $value = $this->getValue($data, $key, $default);
        return is_bool($value) ? $value : $default;
    }

    /**
     * Extract an integer value from data.
     *
     * @param array<string, mixed> $data
     */
    protected function getInt(array $data, string $key, ?int $default = null): ?int
    {
        $value = $this->getValue($data, $key, $default);
        return is_int($value) ? $value : $default;
    }

    /**
     * Extract a float value from data.
     *
     * @param array<string, mixed> $data
     */
    protected function getFloat(array $data, string $key, ?float $default = null): ?float
    {
        $value = $this->getValue($data, $key, $default);
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }
        return $default;
    }

    /**
     * Extract an array value from data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    protected function getArray(array $data, string $key, ?array $default = null): ?array
    {
        $value = $this->getValue($data, $key, $default);
        return is_array($value) ? $value : $default;
    }
}
