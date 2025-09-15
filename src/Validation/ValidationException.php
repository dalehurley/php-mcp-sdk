<?php

declare(strict_types=1);

namespace MCP\Validation;

use Respect\Validation\Exceptions\NestedValidationException;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends \InvalidArgumentException
{
    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create from a Respect Validation exception.
     */
    public static function fromValidationException(NestedValidationException $exception): self
    {
        $errors = [];
        foreach ($exception->getMessages() as $key => $messages) {
            if (is_array($messages)) {
                $errors[$key] = array_values($messages);
            } else {
                $errors[$key] = [$messages];
            }
        }

        return new self(
            'Validation failed: ' . $exception->getMessage(),
            $errors,
            0,
            $exception
        );
    }

    /**
     * Get validation errors.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get flattened error messages.
     *
     * @return string[]
     */
    public function getErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = "$field: $error";
            }
        }

        return $messages;
    }
}
