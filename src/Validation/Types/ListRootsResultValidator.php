<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Validation\TypeValidator;
use MCP\Validation\ValidationException;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for ListRootsResult type.
 */
class ListRootsResultValidator implements TypeValidator
{
    public function getValidator(): Validatable
    {
        return v::arrayType()
            ->key('roots', v::arrayType()->each(
                v::arrayType()
                    ->key('uri', v::stringType()->notEmpty())
                    ->key('name', v::optional(v::stringType()), false)
            ))
            ->key('_meta', v::optional(v::arrayType()), false);
    }

    /**
     * @param mixed $data
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(mixed $data): array
    {
        try {
            $this->getValidator()->assert($data);

            if (!is_array($data)) {
                throw new ValidationException('ListRootsResult must be an array');
            }

            // Additional validation for URI format
            if (isset($data['roots']) && is_array($data['roots'])) {
                foreach ($data['roots'] as $index => $root) {
                    if (!is_array($root)) {
                        throw new ValidationException("Root at index $index must be an array");
                    }

                    if (!isset($root['uri']) || !is_string($root['uri'])) {
                        throw new ValidationException("Root at index $index must have a uri field");
                    }

                    // Basic URI validation
                    if (!filter_var($root['uri'], FILTER_VALIDATE_URL) && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $root['uri'])) {
                        throw new ValidationException("Root at index $index has an invalid URI format: {$root['uri']}");
                    }
                }
            }

            return $data;
        } catch (\Respect\Validation\Exceptions\ValidationException $e) {
            throw new ValidationException('ListRootsResult validation failed: ' . $e->getMessage());
        }
    }

    public function isValid(mixed $data): bool
    {
        try {
            $this->validate($data);
            return true;
        } catch (ValidationException) {
            return false;
        }
    }
}
