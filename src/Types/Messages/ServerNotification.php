<?php

declare(strict_types=1);

namespace MCP\Types\Messages;

use MCP\Types\Notifications;

/**
 * Union type helper for server notifications.
 */
final class ServerNotification
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Get the list of valid server notification methods.
     *
     * @return string[]
     */
    public static function getMethods(): array
    {
        return [
            Notifications\CancelledNotification::METHOD,
            Notifications\ProgressNotification::METHOD,
            Notifications\LoggingMessageNotification::METHOD,
            Notifications\ResourceUpdatedNotification::METHOD,
            Notifications\ResourceListChangedNotification::METHOD,
            Notifications\ToolListChangedNotification::METHOD,
            Notifications\PromptListChangedNotification::METHOD,
        ];
    }

    /**
     * Check if a method is a valid server notification method.
     */
    public static function isValidMethod(string $method): bool
    {
        return in_array($method, self::getMethods(), true);
    }

    /**
     * Create a notification from an array based on the method.
     *
     * @param array<string, mixed> $data
     * @return Notifications\CancelledNotification|Notifications\ProgressNotification|Notifications\LoggingMessageNotification|Notifications\ResourceUpdatedNotification|Notifications\ResourceListChangedNotification|Notifications\ToolListChangedNotification|Notifications\PromptListChangedNotification
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): object
    {
        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new \InvalidArgumentException('Notification must have a method property');
        }

        return match ($data['method']) {
            Notifications\CancelledNotification::METHOD => Notifications\CancelledNotification::fromArray($data),
            Notifications\ProgressNotification::METHOD => Notifications\ProgressNotification::fromArray($data),
            Notifications\LoggingMessageNotification::METHOD => Notifications\LoggingMessageNotification::fromArray($data),
            Notifications\ResourceUpdatedNotification::METHOD => Notifications\ResourceUpdatedNotification::fromArray($data),
            Notifications\ResourceListChangedNotification::METHOD => Notifications\ResourceListChangedNotification::fromArray($data),
            Notifications\ToolListChangedNotification::METHOD => Notifications\ToolListChangedNotification::fromArray($data),
            Notifications\PromptListChangedNotification::METHOD => Notifications\PromptListChangedNotification::fromArray($data),
            default => throw new \InvalidArgumentException('Unknown server notification method: ' . $data['method']),
        };
    }
}
