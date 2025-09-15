<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Validation\TypeValidator;
use MCP\Validation\ValidationException;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for ElicitResult type.
 */
class ElicitResultValidator implements TypeValidator
{
    public function getValidator(): Validatable
    {
        return v::arrayType()
            ->key('action', v::in(['accept', 'reject', 'cancel']))
            ->key('content', v::optional(v::anyOf(v::arrayType(), v::nullType())), false)
            ->key('reason', v::optional(v::stringType()), false)
            ->key('_meta', v::optional(v::arrayType()), false);
    }

    /**
     * @param mixed $data
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(mixed $data): array
    {
        try {
            $this->getValidator()->assert($data);

            if (!is_array($data)) {
                throw new ValidationException('ElicitResult must be an array');
            }

            $action = $data['action'];

            // Validate action-specific requirements
            switch ($action) {
                case 'accept':
                    // Accept action should have content
                    if (!isset($data['content'])) {
                        throw new ValidationException('ElicitResult with accept action should have content');
                    }
                    break;
                case 'reject':
                case 'cancel':
                    // Reject/cancel actions may have a reason
                    if (isset($data['reason']) && !is_string($data['reason'])) {
                        throw new ValidationException('ElicitResult reason must be a string');
                    }
                    break;
            }

            return $data;
        } catch (\Respect\Validation\Exceptions\ValidationException $e) {
            throw new ValidationException('ElicitResult validation failed: ' . $e->getMessage());
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
