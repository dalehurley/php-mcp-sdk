<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Types\Protocol;
use MCP\Validation\AbstractValidator;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for JSONRPCRequest type.
 */
class JSONRPCRequestValidator extends AbstractValidator
{
    protected function createValidator(): Validatable
    {
        return v::arrayType()
            ->key('jsonrpc', v::equals(Protocol::JSONRPC_VERSION))
            ->key('id', v::oneOf(v::stringType()->notEmpty(), v::intType()))
            ->key('method', v::stringType()->notEmpty())
            ->key('params', v::optional(v::arrayType()));
    }
}
