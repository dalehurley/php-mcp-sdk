<?php

declare(strict_types=1);

namespace MCP\Validation;

use Respect\Validation\Validatable;

/**
 * Interface for type validators.
 */
interface TypeValidator
{
    /**
     * Get the validator for this type.
     */
    public function getValidator(): Validatable;

    /**
     * Validate data and return validated array.
     *
     * @param mixed $data
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(mixed $data): array;

    /**
     * Check if data is valid without throwing exceptions.
     */
    public function isValid(mixed $data): bool;
}
