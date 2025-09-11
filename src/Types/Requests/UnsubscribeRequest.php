<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;

/**
 * Sent from the client to request cancellation of resources/updated notifications
 * from the server. This should follow a previous resources/subscribe request.
 */
final class UnsubscribeRequest extends Request
{
    public const METHOD = 'resources/unsubscribe';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new unsubscribe request.
     */
    public static function create(string $uri): self
    {
        return new self(['uri' => $uri]);
    }

    /**
     * Get the URI of the resource to unsubscribe from.
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
     * Check if this is a valid unsubscribe request.
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
