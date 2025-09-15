<?php

declare(strict_types=1);

namespace MCP\Types\Notifications;

use MCP\Types\Notification;
use MCP\Types\RequestId;

/**
 * This notification can be sent by either side to indicate that it is
 * cancelling a previously-issued request.
 */
final class CancelledNotification extends Notification
{
    public const METHOD = 'notifications/cancelled';

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
                throw new \InvalidArgumentException("Invalid method for CancelledNotification: expected '" . self::METHOD . "', got '$method'");
            }
            parent::__construct($method, $params);
        }
    }

    /**
     * Create a new cancelled notification.
     */
    public static function create(RequestId $requestId, ?string $reason = null): self
    {
        $params = ['requestId' => $requestId->jsonSerialize()];

        if ($reason !== null) {
            $params['reason'] = $reason;
        }

        return new self($params);
    }

    /**
     * Get the request ID to cancel.
     */
    public function getRequestId(): ?RequestId
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['requestId'])) {
            return null;
        }

        return RequestId::from($params['requestId']);
    }

    /**
     * Get the cancellation reason.
     */
    public function getReason(): ?string
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['reason'])) {
            return null;
        }

        return is_string($params['reason']) ? $params['reason'] : null;
    }

    /**
     * Check if this is a valid cancelled notification.
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

        return isset($params['requestId']);
    }
}
