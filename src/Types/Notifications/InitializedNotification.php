<?php

declare(strict_types=1);

namespace MCP\Types\Notifications;

use MCP\Types\Notification;

/**
 * This notification is sent from the client to the server after initialization has finished.
 */
final class InitializedNotification extends Notification
{
    public const METHOD = 'notifications/initialized';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new initialized notification.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Check if this is a valid initialized notification.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        return is_array($value) && ($value['method'] ?? null) === self::METHOD;
    }
}
