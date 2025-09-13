<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;
use MCP\Types\References\PromptReference;
use MCP\Types\References\ResourceTemplateReference;

/**
 * A request from the client to the server, to ask for completion options.
 */
final class CompleteRequest extends Request
{
    public const METHOD = 'completion/complete';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new complete request.
     *
     * @param PromptReference|ResourceTemplateReference $ref
     * @param array{name: string, value: string} $argument
     * @param array{arguments?: array<string, string>}|null $context
     */
    public static function create(
        PromptReference|ResourceTemplateReference $ref,
        array $argument,
        ?array $context = null
    ): self {
        $params = [
            'ref' => $ref->jsonSerialize(),
            'argument' => $argument,
        ];

        if ($context !== null) {
            $params['context'] = $context;
        }

        return new self($params);
    }

    /**
     * Get the reference (prompt or resource template).
     */
    public function getRef(): PromptReference|ResourceTemplateReference|null
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['ref']) || !is_array($params['ref'])) {
            return null;
        }

        $type = $params['ref']['type'] ?? null;

        return match ($type) {
            'ref/prompt' => PromptReference::fromArray($params['ref']),
            'ref/resource' => ResourceTemplateReference::fromArray($params['ref']),
            default => null,
        };
    }

    /**
     * Get the argument information.
     *
     * @return array{name: string, value: string}|null
     */
    public function getArgument(): ?array
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['argument']) || !is_array($params['argument'])) {
            return null;
        }

        $argument = $params['argument'];
        if (
            isset($argument['name']) && is_string($argument['name']) &&
            isset($argument['value']) && is_string($argument['value'])
        ) {
            return [
                'name' => $argument['name'],
                'value' => $argument['value'],
            ];
        }

        return null;
    }

    /**
     * Get the context.
     *
     * @return array{arguments?: array<string, string>}|null
     */
    public function getContext(): ?array
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['context']) || !is_array($params['context'])) {
            return null;
        }

        return $params['context'];
    }

    /**
     * Check if this is a valid complete request.
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

        return isset($params['ref']) && isset($params['argument']);
    }
}
