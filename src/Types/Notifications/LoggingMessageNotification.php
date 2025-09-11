<?php

declare(strict_types=1);

namespace MCP\Types\Notifications;

use MCP\Types\Notification;
use MCP\Types\LoggingLevel;

/**
 * Notification of a log message passed from server to client. If no logging/setLevel
 * request has been sent from the client, the server MAY decide which messages to
 * send automatically.
 */
final class LoggingMessageNotification extends Notification
{
    public const METHOD = 'notifications/message';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new logging message notification.
     */
    public static function create(
        LoggingLevel $level,
        mixed $data,
        ?string $logger = null
    ): self {
        $params = [
            'level' => $level->value,
            'data' => $data,
        ];

        if ($logger !== null) {
            $params['logger'] = $logger;
        }

        return new self($params);
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
     * Get the logger name.
     */
    public function getLogger(): ?string
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['logger'])) {
            return null;
        }

        return is_string($params['logger']) ? $params['logger'] : null;
    }

    /**
     * Get the log data.
     */
    public function getData(): mixed
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['data'])) {
            return null;
        }

        return $params['data'];
    }

    /**
     * Check if this is a valid logging message notification.
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

        return isset($params['level']) && isset($params['data']);
    }
}
