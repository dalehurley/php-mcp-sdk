<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Validation\AbstractValidator;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for ContentBlock types.
 * Validates the union of all content block types.
 */
class ContentBlockValidator extends AbstractValidator
{
    protected function createValidator(): Validatable
    {
        $textContent = v::arrayType()
            ->key('type', v::equals('text'))
            ->key('text', v::stringType())
            ->key('_meta', v::optional(v::arrayType()));

        $imageContent = v::arrayType()
            ->key('type', v::equals('image'))
            ->key('data', v::stringType()) // Base64 validation could be added
            ->key('mimeType', v::stringType())
            ->key('_meta', v::optional(v::arrayType()));

        $audioContent = v::arrayType()
            ->key('type', v::equals('audio'))
            ->key('data', v::stringType()) // Base64 validation could be added
            ->key('mimeType', v::stringType())
            ->key('_meta', v::optional(v::arrayType()));

        $resourceLink = v::arrayType()
            ->key('type', v::equals('resource_link'))
            ->key('name', v::stringType()->notEmpty())
            ->key('uri', v::stringType()->notEmpty())
            ->key('title', v::optional(v::stringType()))
            ->key('description', v::optional(v::stringType()))
            ->key('mimeType', v::optional(v::stringType()))
            ->key('_meta', v::optional(v::arrayType()));

        $embeddedResource = v::arrayType()
            ->key('type', v::equals('resource'))
            ->key('resource', v::arrayType())
            ->key('_meta', v::optional(v::arrayType()));

        return v::oneOf(
            $textContent,
            $imageContent,
            $audioContent,
            $resourceLink,
            $embeddedResource
        );
    }
}
