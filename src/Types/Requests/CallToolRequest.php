<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;

/**
 * Used by the client to invoke a tool provided by the server.
 */
final class CallToolRequest extends Request
{
    public const METHOD = 'tools/call';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new call tool request.
     *
     * @param array<string, mixed>|null $arguments
     */
    public static function create(string $name, ?array $arguments = null): self
    {
        $params = ['name' => $name];

        if ($arguments !== null) {
            $params['arguments'] = $arguments;
        }

        return new self($params);
    }

    /**
     * Get the name of the tool to call.
     */
    public function getName(): ?string
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['name'])) {
            return null;
        }

        return is_string($params['name']) ? $params['name'] : null;
    }

    /**
     * Get the arguments for the tool call.
     *
     * @return array<string, mixed>|null
     */
    public function getArguments(): ?array
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['arguments'])) {
            return null;
        }

        return is_array($params['arguments']) ? $params['arguments'] : null;
    }

    /**
     * Check if this is a valid call tool request.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        if (!is_array($value) || ($value['method'] ?? null) !== self::METHOD) {
            return false;
        }

        $params = $value['params'] ?? null;
        if (!is_array($params)) {
            return false;
        }

        return isset($params['name']);
    }
}
