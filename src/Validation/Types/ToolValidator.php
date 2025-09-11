<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Validation\AbstractValidator;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for Tool type.
 */
class ToolValidator extends AbstractValidator
{
    protected function createValidator(): Validatable
    {
        $inputSchema = v::arrayType()
            ->key('type', v::equals('object'))
            ->key('properties', v::optional(v::arrayType()))
            ->key('required', v::optional(v::arrayType()->each(v::stringType())));

        $outputSchema = v::arrayType()
            ->key('type', v::equals('object'))
            ->key('properties', v::optional(v::arrayType()))
            ->key('required', v::optional(v::arrayType()->each(v::stringType())));

        $annotations = v::arrayType()
            ->key('title', v::optional(v::stringType()))
            ->key('readOnlyHint', v::optional(v::boolType()))
            ->key('destructiveHint', v::optional(v::boolType()))
            ->key('idempotentHint', v::optional(v::boolType()))
            ->key('openWorldHint', v::optional(v::boolType()));

        return v::arrayType()
            ->key('name', v::stringType()->notEmpty())
            ->key('title', v::optional(v::stringType()))
            ->key('description', v::optional(v::stringType()))
            ->key('inputSchema', $inputSchema)
            ->key('outputSchema', v::optional($outputSchema))
            ->key('annotations', v::optional($annotations))
            ->key('_meta', v::optional(v::arrayType()));
    }
}
