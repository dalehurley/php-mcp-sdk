<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;

/**
 * Used by the client to get a prompt provided by the server.
 */
final class GetPromptRequest extends Request
{
    public const METHOD = 'prompts/get';

    /**
     * @param array<string, mixed>|null|string $methodOrParams For backward compatibility, can be params array or method string
     * @param array<string, mixed>|null $params Only used when first parameter is method string
     */
    public function __construct($methodOrParams = null, ?array $params = null)
    {
        // Handle backward compatibility: if first param is array, treat as params
        if (is_array($methodOrParams)) {
            parent::__construct(self::METHOD, $methodOrParams);
        } else {
            // If method is null, use default. If method is provided, it should match our expected method.
            $method = $methodOrParams ?? self::METHOD;
            if ($method !== self::METHOD) {
                throw new \InvalidArgumentException("Invalid method for GetPromptRequest: expected '" . self::METHOD . "', got '$method'");
            }
            parent::__construct($method, $params);
        }
    }

    /**
     * Create a new get prompt request.
     *
     * @param array<string, string>|null $arguments
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
     * Get the name of the prompt.
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
     * Get the arguments for templating the prompt.
     *
     * @return array<string, string>|null
     */
    public function getArguments(): ?array
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['arguments'])) {
            return null;
        }

        if (is_array($params['arguments'])) {
            // Ensure all values are strings
            $arguments = [];
            foreach ($params['arguments'] as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $arguments[$key] = $value;
                }
            }

            return $arguments;
        }

        return null;
    }

    /**
     * Check if this is a valid get prompt request.
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
