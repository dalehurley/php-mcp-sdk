<?php

declare(strict_types=1);

namespace MCP\Types\Notifications;

use MCP\Types\Notification;
use MCP\Types\Progress;
use MCP\Types\ProgressToken;

/**
 * An out-of-band notification used to inform the receiver of a progress update
 * for a long-running request.
 */
final class ProgressNotification extends Notification
{
    public const METHOD = 'notifications/progress';

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
                throw new \InvalidArgumentException("Invalid method for ProgressNotification: expected '" . self::METHOD . "', got '$method'");
            }
            parent::__construct($method, $params);
        }
    }

    /**
     * Create a new progress notification.
     */
    public static function create(
        ProgressToken $progressToken,
        Progress $progress
    ): self {
        $params = array_merge(
            ['progressToken' => $progressToken->jsonSerialize()],
            $progress->jsonSerialize()
        );

        return new self($params);
    }

    /**
     * Get the progress token.
     */
    public function getProgressToken(): ?ProgressToken
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['progressToken'])) {
            return null;
        }

        return ProgressToken::from($params['progressToken']);
    }

    /**
     * Get the progress information.
     */
    public function getProgress(): ?Progress
    {
        $params = $this->getParams();
        if ($params === null) {
            return null;
        }

        // Extract progress-related fields
        $progressData = [];
        if (isset($params['progress'])) {
            $progressData['progress'] = $params['progress'];
        }
        if (isset($params['total'])) {
            $progressData['total'] = $params['total'];
        }
        if (isset($params['message'])) {
            $progressData['message'] = $params['message'];
        }

        // Add any additional fields (excluding progressToken and _meta)
        foreach ($params as $key => $value) {
            if (!in_array($key, ['progressToken', '_meta', 'progress', 'total', 'message'], true)) {
                $progressData[$key] = $value;
            }
        }

        return Progress::fromArray($progressData);
    }

    /**
     * Check if this is a valid progress notification.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        if (!is_array($value) || ($value['method'] ?? null) !== self::METHOD) {
            return false;
        }

        // Must have progressToken and progress in params
        $params = $value['params'] ?? null;
        if (!is_array($params)) {
            return false;
        }

        return isset($params['progressToken']) && isset($params['progress']);
    }
}
