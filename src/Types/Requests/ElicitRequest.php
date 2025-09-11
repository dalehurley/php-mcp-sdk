<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;
use MCP\Types\Elicitation\PrimitiveSchemaDefinition;

/**
 * A request from the server to elicit user input via the client.
 * The client should present the message and form fields to the user.
 */
final class ElicitRequest extends Request
{
    public const METHOD = 'elicitation/create';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new elicit request.
     *
     * @param array<string, PrimitiveSchemaDefinition> $properties
     * @param string[]|null $required
     * @param array<string, mixed> $additional Additional schema properties
     */
    public static function create(
        string $message,
        array $properties,
        ?array $required = null,
        array $additional = []
    ): self {
        $schema = array_merge(
            [
                'type' => 'object',
                'properties' => array_map(
                    fn(PrimitiveSchemaDefinition $prop) => $prop->jsonSerialize(),
                    $properties
                ),
            ],
            $additional
        );

        if ($required !== null) {
            $schema['required'] = $required;
        }

        return new self([
            'message' => $message,
            'requestedSchema' => $schema,
        ]);
    }

    /**
     * Get the message.
     */
    public function getMessage(): ?string
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['message'])) {
            return null;
        }

        return is_string($params['message']) ? $params['message'] : null;
    }

    /**
     * Get the requested schema.
     *
     * @return array{type: string, properties: array<string, array<string, mixed>>, required?: string[]}|null
     */
    public function getRequestedSchema(): ?array
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['requestedSchema']) || !is_array($params['requestedSchema'])) {
            return null;
        }

        $schema = $params['requestedSchema'];
        if (!isset($schema['type']) || $schema['type'] !== 'object' || !isset($schema['properties'])) {
            return null;
        }

        return $schema;
    }

    /**
     * Check if this is a valid elicit request.
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

        return isset($params['message']) && isset($params['requestedSchema']);
    }
}
