<?php

declare(strict_types=1);

namespace MCP\Types\Notifications;

use MCP\Types\Notification;

/**
 * An optional notification from the server to the client, informing it that
 * the list of prompts it offers has changed. This may be issued by servers
 * without any previous subscription from the client.
 */
final class PromptListChangedNotification extends Notification
{
    public const METHOD = 'notifications/prompts/list_changed';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new prompt list changed notification.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Check if this is a valid prompt list changed notification.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        return is_array($value) && ($value['method'] ?? null) === self::METHOD;
    }
}
