<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Shared\RequestHandlerExtra;
use MCP\Shared\UriTemplate;
use MCP\Types\Results\ListResourcesResult;
use MCP\Types\ServerNotification;
use MCP\Types\ServerRequest;

/**
 * A callback to complete one variable within a resource template's URI template.
 *
 * @param string $value The current value to complete
 * @param array{arguments?: array<string, string>}|null $context The completion context
 *
 * @return array<string>|\Amp\Future<array<string>> The completion suggestions
 */
interface CompleteResourceTemplateCallback
{
    public function __invoke(string $value, ?array $context = null);
}

/**
 * Callback to list all resources matching a given template.
 *
 * @param RequestHandlerExtra<ServerRequest, ServerNotification> $extra
 *
 * @return ListResourcesResult|\Amp\Future<ListResourcesResult>
 */
interface ListResourcesCallback
{
    public function __invoke(RequestHandlerExtra $extra);
}

/**
 * A resource template combines a URI pattern with optional functionality to enumerate
 * all resources matching that pattern.
 */
class ResourceTemplate
{
    private UriTemplate $_uriTemplate;

    /**
     * @param string|UriTemplate $uriTemplate The URI template pattern
     * @param array{
     *   list?: ListResourcesCallback|null,
     *   complete?: array<string, CompleteResourceTemplateCallback>
     * } $callbacks
     */
    public function __construct(
        string|UriTemplate $uriTemplate,
        private array $callbacks
    ) {
        $this->_uriTemplate = $uriTemplate instanceof UriTemplate
            ? $uriTemplate
            : new UriTemplate($uriTemplate);

        // Ensure 'list' key exists even if null to match TypeScript behavior
        if (!array_key_exists('list', $this->callbacks)) {
            $this->callbacks['list'] = null;
        }
    }

    /**
     * Gets the URI template pattern.
     */
    public function getUriTemplate(): UriTemplate
    {
        return $this->_uriTemplate;
    }

    /**
     * Gets the list callback, if one was provided.
     */
    public function getListCallback(): ?ListResourcesCallback
    {
        return $this->callbacks['list'] ?? null;
    }

    /**
     * Gets the callback for completing a specific URI template variable, if one was provided.
     */
    public function getCompleteCallback(string $variable): ?CompleteResourceTemplateCallback
    {
        return $this->callbacks['complete'][$variable] ?? null;
    }
}
