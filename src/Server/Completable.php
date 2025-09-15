<?php

declare(strict_types=1);

namespace MCP\Server;

/**
 * Type for a completion callback function.
 *
 * @template T
 *
 * @param T $value The current value to complete
 * @param array{arguments?: array<string, string>}|null $context The completion context
 *
 * @return array<T>|\Amp\Future<array<T>> The completion suggestions
 */
interface CompleteCallback
{
    /**
     * @param mixed $value
     * @param array{arguments?: array<string, string>}|null $context
     *
     * @return array<mixed>|\Amp\Future<array<mixed>>
     */
    public function __invoke($value, ?array $context = null);
}

/**
 * A wrapper that provides autocompletion capabilities for values.
 * Useful for prompt arguments and resource templates in MCP.
 *
 * @template T
 */
class Completable
{
    private CompletableDef $_def;

    /**
     * @param CompletableDef $def The completable definition
     */
    public function __construct(CompletableDef $def)
    {
        $this->_def = $def;
    }

    /**
     * Create a new completable value.
     *
     * @template TType
     *
     * @param TType $type The underlying type/schema
     * @param CompleteCallback $complete The completion callback
     *
     * @return Completable<TType>
     */
    public static function create($type, CompleteCallback $complete): self
    {
        return new self(new CompletableDef($type, $complete));
    }

    /**
     * Get the underlying type.
     *
     * @return T
     */
    public function unwrap()
    {
        return $this->_def->type;
    }

    /**
     * Get the completion callback.
     */
    public function getCompleteCallback(): CompleteCallback
    {
        return $this->_def->complete;
    }

    /**
     * Parse the input value.
     *
     * @param mixed $input
     *
     * @return mixed
     */
    public function parse($input)
    {
        // In PHP, we don't have Zod-like parsing, so we'll just validate and return
        // This would be where you'd integrate with a validation library
        return $input;
    }

    /**
     * Check if this is a Completable instance.
     */
    public static function isCompletable($value): bool
    {
        return $value instanceof self;
    }
}

/**
 * Helper function to create a completable value.
 * Wraps a type/schema to provide autocompletion capabilities.
 *
 * @template T
 *
 * @param T $schema The underlying schema/type
 * @param callable(T, array{arguments?: array<string, string>}|null): array<T>|\Amp\Future<array<T>> $complete
 *
 * @return Completable<T>
 */
function completable($schema, callable $complete): Completable
{
    return Completable::create($schema, new class ($complete) implements CompleteCallback {
        private $callback;

        public function __construct(callable $callback)
        {
            $this->callback = $callback;
        }

        public function __invoke($value, ?array $context = null)
        {
            return ($this->callback)($value, $context);
        }
    });
}
