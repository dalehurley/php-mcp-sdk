<?php

declare(strict_types=1);

namespace MCP\Types\Notifications;

use MCP\Types\Notification;

/**
 * A notification from the client to the server, informing it that the list
 * of roots has changed.
 */
final class RootsListChangedNotification extends Notification
{
    public const METHOD = 'notifications/roots/list_changed';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new roots list changed notification.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Check if this is a valid roots list changed notification.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        return is_array($value) && ($value['method'] ?? null) === self::METHOD;
    }
}
