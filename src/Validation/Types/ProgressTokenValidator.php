<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Validation\AbstractValidator;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for ProgressToken type.
 * Progress tokens can be either string or integer.
 */
class ProgressTokenValidator extends AbstractValidator
{
    protected function createValidator(): Validatable
    {
        return v::oneOf(
            v::stringType()->notEmpty(),
            v::intType()
        );
    }
}
