<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Validation\AbstractValidator;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for Cursor type.
 * Cursors are non-empty strings used for pagination.
 */
class CursorValidator extends AbstractValidator
{
    protected function createValidator(): Validatable
    {
        return v::stringType()->notEmpty();
    }
}
