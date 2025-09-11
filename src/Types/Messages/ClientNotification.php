<?php

declare(strict_types=1);

namespace MCP\Types\Messages;

use MCP\Types\Notifications;

/**
 * Union type helper for client notifications.
 */
final class ClientNotification
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Get the list of valid client notification methods.
     *
     * @return string[]
     */
    public static function getMethods(): array
    {
        return [
            Notifications\CancelledNotification::METHOD,
            Notifications\ProgressNotification::METHOD,
            Notifications\InitializedNotification::METHOD,
            Notifications\RootsListChangedNotification::METHOD,
        ];
    }

    /**
     * Check if a method is a valid client notification method.
     */
    public static function isValidMethod(string $method): bool
    {
        return in_array($method, self::getMethods(), true);
    }

    /**
     * Create a notification from an array based on the method.
     *
     * @param array<string, mixed> $data
     * @return Notifications\CancelledNotification|Notifications\ProgressNotification|Notifications\InitializedNotification|Notifications\RootsListChangedNotification
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
            Notifications\InitializedNotification::METHOD => Notifications\InitializedNotification::fromArray($data),
            Notifications\RootsListChangedNotification::METHOD => Notifications\RootsListChangedNotification::fromArray($data),
            default => throw new \InvalidArgumentException('Unknown client notification method: ' . $data['method']),
        };
    }
}
