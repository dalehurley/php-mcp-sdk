<?php

declare(strict_types=1);

namespace MCP\Validation;

use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validatable;

/**
 * Abstract base class for type validators.
 */
abstract class AbstractValidator implements TypeValidator
{
    protected ?Validatable $validator = null;

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $data): array
    {
        try {
            $this->getValidator()->assert($data);
        } catch (NestedValidationException $e) {
            throw ValidationException::fromValidationException($e);
        }

        // If data is an array, return it; otherwise wrap in array
        if (is_array($data)) {
            return $data;
        }

        // For scalar values, we'll return an array with 'value' key
        return ['value' => $data];
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(mixed $data): bool
    {
        return $this->getValidator()->validate($data);
    }

    /**
     * Get or create the validator instance.
     */
    public function getValidator(): Validatable
    {
        if ($this->validator === null) {
            $this->validator = $this->createValidator();
        }

        return $this->validator;
    }

    /**
     * Create the validator instance.
     * This method must be implemented by subclasses.
     */
    abstract protected function createValidator(): Validatable;
}
