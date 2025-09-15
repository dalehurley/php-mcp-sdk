<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\LoggingLevel;
use MCP\Types\Request;

/**
 * A request from the client to the server, to enable or adjust logging.
 */
final class SetLevelRequest extends Request
{
    public const METHOD = 'logging/setLevel';

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
                throw new \InvalidArgumentException("Invalid method for SetLevelRequest: expected '" . self::METHOD . "', got '$method'");
            }
            parent::__construct($method, $params);
        }
    }

    /**
     * Create a new set level request.
     */
    public static function create(LoggingLevel $level): self
    {
        return new self(['level' => $level->value]);
    }

    /**
     * Get the logging level.
     */
    public function getLevel(): ?LoggingLevel
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['level'])) {
            return null;
        }

        if (is_string($params['level'])) {
            return LoggingLevel::tryFrom($params['level']);
        }

        return null;
    }

    /**
     * Check if this is a valid set level request.
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

        return isset($params['level']);
    }
}
