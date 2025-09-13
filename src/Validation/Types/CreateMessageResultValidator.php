<?php

declare(strict_types=1);

namespace MCP\Validation\Types;

use MCP\Validation\TypeValidator;
use MCP\Validation\ValidationException;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validator for CreateMessageResult type.
 */
class CreateMessageResultValidator implements TypeValidator
{
    public function getValidator(): Validatable
    {
        return v::arrayType()
            ->key('model', v::stringType()->notEmpty())
            ->key('role', v::stringType()->notEmpty())
            ->key('content', v::arrayType()->notEmpty())
            ->key('stopReason', v::optional(v::stringType()), false)
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
                throw new ValidationException('CreateMessageResult must be an array');
            }

            // Validate content structure
            $content = $data['content'];
            if (!isset($content['type']) || !is_string($content['type'])) {
                throw new ValidationException('CreateMessageResult content must have a type field');
            }

            $validContentTypes = ['text', 'image', 'audio'];
            if (!in_array($content['type'], $validContentTypes, true)) {
                throw new ValidationException(
                    'CreateMessageResult content type must be one of: ' . implode(', ', $validContentTypes)
                );
            }

            // Validate content based on type
            switch ($content['type']) {
                case 'text':
                    if (!isset($content['text']) || !is_string($content['text'])) {
                        throw new ValidationException('Text content must have a text field');
                    }
                    break;
                case 'image':
                    if (!isset($content['data']) || !is_string($content['data'])) {
                        throw new ValidationException('Image content must have a data field');
                    }
                    if (!isset($content['mimeType']) || !is_string($content['mimeType'])) {
                        throw new ValidationException('Image content must have a mimeType field');
                    }
                    break;
                case 'audio':
                    if (!isset($content['data']) || !is_string($content['data'])) {
                        throw new ValidationException('Audio content must have a data field');
                    }
                    if (!isset($content['mimeType']) || !is_string($content['mimeType'])) {
                        throw new ValidationException('Audio content must have a mimeType field');
                    }
                    break;
            }

            return $data;
        } catch (\Respect\Validation\Exceptions\ValidationException $e) {
            throw new ValidationException('CreateMessageResult validation failed: ' . $e->getMessage());
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
