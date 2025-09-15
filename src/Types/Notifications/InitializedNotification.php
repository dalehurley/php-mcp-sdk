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
                throw new \InvalidArgumentException("Invalid method for InitializedNotification: expected '" . self::METHOD . "', got '$method'");
            }
            parent::__construct($method, $params);
        }
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
