<?php

declare(strict_types=1);

namespace MCP\Types\Notifications;

use MCP\Types\Notification;

/**
 * A notification from the server to the client, informing it that a resource
 * has changed and may need to be read again. This should only be sent if the
 * client previously sent a resources/subscribe request.
 */
final class ResourceUpdatedNotification extends Notification
{
    public const METHOD = 'notifications/resources/updated';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new resource updated notification.
     */
    public static function create(string $uri): self
    {
        return new self(['uri' => $uri]);
    }

    /**
     * Get the URI of the resource that has been updated.
     */
    public function getUri(): ?string
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['uri'])) {
            return null;
        }

        return is_string($params['uri']) ? $params['uri'] : null;
    }

    /**
     * Check if this is a valid resource updated notification.
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

        return isset($params['uri']);
    }
}
