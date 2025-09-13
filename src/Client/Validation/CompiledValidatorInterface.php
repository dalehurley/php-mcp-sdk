<?php

declare(strict_types=1);

namespace MCP\Client\Validation;

/**
 * Interface for compiled JSON schema validators that provide better performance
 * than runtime validation for frequently used schemas.
 */
interface CompiledValidatorInterface
{
    /**
     * Validate data against the compiled schema.
     */
    public function validate(mixed $data): bool;

    /**
     * Get validation errors from the last validation attempt.
     * 
     * @return array<string> Array of error messages
     */
    public function getErrors(): array;

    /**
     * Get the original JSON schema that was compiled.
     */
    public function getSchema(): array;

    /**
     * Get a unique hash identifying this compiled validator.
     */
    public function getSchemaHash(): string;

    /**
     * Check if the validator was compiled successfully.
     */
    public function isValid(): bool;
}
