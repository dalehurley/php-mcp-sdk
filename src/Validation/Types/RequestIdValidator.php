<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Validation\AbstractValidator;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for RequestId type.
 * Request IDs can be either string or integer.
 */
class RequestIdValidator extends AbstractValidator
{
    protected function createValidator(): Validatable
    {
        return v::oneOf(
            v::stringType()->notEmpty(),
            v::intType()
        );
    }
}
