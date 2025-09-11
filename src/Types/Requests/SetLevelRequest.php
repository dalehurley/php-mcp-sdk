<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;
use MCP\Types\LoggingLevel;

/**
 * A request from the client to the server, to enable or adjust logging.
 */
final class SetLevelRequest extends Request
{
    public const METHOD = 'logging/setLevel';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
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
