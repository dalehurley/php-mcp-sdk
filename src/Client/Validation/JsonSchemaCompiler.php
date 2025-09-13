<?php

declare(strict_types=1);

namespace MCP\Client\Validation;

use Amp\Future;
use MCP\Types\McpError;
use MCP\Types\ErrorCode;

use function Amp\async;

/**
 * Compiles JSON schemas into optimized validators for better performance.
 *
 * This compiler pre-processes JSON schemas to create optimized validation
 * functions that can validate data faster than generic schema validators.
 */
class JsonSchemaCompiler
{
    /** @var array<string, CompiledValidatorInterface> */
    private array $compiledValidators = [];

    public function __construct(
        private readonly ?object $cache = null,
        private readonly int $cacheTimeout = 3600
    ) {}

    /**
     * Compile a JSON schema into an optimized validator.
     */
    public function compile(array $schema): CompiledValidatorInterface
    {
        $schemaHash = $this->generateSchemaHash($schema);

        // Check if already compiled
        if (isset($this->compiledValidators[$schemaHash])) {
            return $this->compiledValidators[$schemaHash];
        }

        // Try to load from cache
        if ($this->cache !== null && method_exists($this->cache, 'get')) {
            try {
                $cached = $this->cache->get("compiled_validator_{$schemaHash}");
                if ($cached instanceof CompiledValidatorInterface) {
                    $this->compiledValidators[$schemaHash] = $cached;
                    return $cached;
                }
            } catch (\Throwable $e) {
                // Cache error, continue with compilation
            }
        }

        // Compile the schema
        $validator = $this->compileSchema($schema, $schemaHash);
        $this->compiledValidators[$schemaHash] = $validator;

        // Cache the compiled validator
        if ($this->cache !== null && method_exists($this->cache, 'set') && $validator->isValid()) {
            try {
                $this->cache->set(
                    "compiled_validator_{$schemaHash}",
                    $validator,
                    $this->cacheTimeout
                );
            } catch (\Throwable $e) {
                // Cache error, but continue
            }
        }

        return $validator;
    }

    /**
     * Compile a schema from a URL.
     */
    public function compileFromUrl(string $url): Future
    {
        return async(function () use ($url) {
            // This would fetch the schema from the URL and compile it
            // For now, this is a placeholder
            throw new McpError(
                ErrorCode::MethodNotFound,
                "Schema compilation from URL not yet implemented: {$url}"
            );
        });
    }

    /**
     * Get a compiled validator by schema hash.
     */
    public function getValidator(string $schemaHash): ?CompiledValidatorInterface
    {
        return $this->compiledValidators[$schemaHash] ?? null;
    }

    /**
     * Clear all compiled validators.
     */
    public function clearCache(): void
    {
        $this->compiledValidators = [];

        if ($this->cache !== null && method_exists($this->cache, 'clear')) {
            try {
                $this->cache->clear();
            } catch (\Throwable $e) {
                // Ignore cache errors
            }
        }
    }

    /**
     * Get statistics about compiled validators.
     */
    public function getStats(): array
    {
        return [
            'compiled_count' => count($this->compiledValidators),
            'cache_enabled' => $this->cache !== null,
            'cache_timeout' => $this->cacheTimeout,
        ];
    }

    /**
     * Generate a unique hash for a schema.
     */
    private function generateSchemaHash(array $schema): string
    {
        return hash('sha256', json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Actually compile a schema into a validator.
     */
    private function compileSchema(array $schema, string $schemaHash): CompiledValidatorInterface
    {
        // Analyze the schema and create an optimized validator
        $validator = new CompiledValidator($schema, $schemaHash);

        // Pre-compile validation rules based on schema structure
        $this->optimizeValidator($validator, $schema);

        return $validator;
    }

    /**
     * Optimize the validator based on schema patterns.
     */
    private function optimizeValidator(CompiledValidator $validator, array $schema): void
    {
        // Analyze common patterns and create optimized validation paths

        // Handle simple type validation
        if (isset($schema['type']) && is_string($schema['type'])) {
            $validator->addTypeCheck($schema['type']);
        }

        // Handle required properties
        if (isset($schema['required']) && is_array($schema['required'])) {
            $validator->addRequiredCheck($schema['required']);
        }

        // Handle property validation
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                $validator->addPropertyCheck($property, $propertySchema);
            }
        }

        // Handle array validation
        if (isset($schema['items'])) {
            $validator->addItemsCheck($schema['items']);
        }

        // Handle enum validation
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $validator->addEnumCheck($schema['enum']);
        }

        // Handle pattern validation
        if (isset($schema['pattern']) && is_string($schema['pattern'])) {
            $validator->addPatternCheck($schema['pattern']);
        }

        // Handle numeric constraints
        if (isset($schema['minimum'])) {
            $validator->addMinimumCheck($schema['minimum']);
        }
        if (isset($schema['maximum'])) {
            $validator->addMaximumCheck($schema['maximum']);
        }

        // Handle string length constraints
        if (isset($schema['minLength'])) {
            $validator->addMinLengthCheck($schema['minLength']);
        }
        if (isset($schema['maxLength'])) {
            $validator->addMaxLengthCheck($schema['maxLength']);
        }
    }
}
