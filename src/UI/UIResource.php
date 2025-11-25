<?php

declare(strict_types=1);

namespace MCP\UI;

use InvalidArgumentException;

/**
 * Helper for creating MCP-UI compatible resource responses.
 *
 * UIResources allow tools to return interactive HTML widgets that compatible
 * hosts (Goose, LibreChat, MCP-UI Chat, etc.) can render in sandboxed iframes.
 *
 * @see https://github.com/idosal/mcp-ui
 *
 * @example
 * ```php
 * // In a tool handler:
 * return [
 *     'content' => [
 *         ['type' => 'text', 'text' => 'Here is the weather'],
 *         UIResource::html('ui://weather/sydney', '<html>...</html>')
 *     ]
 * ];
 * ```
 */
class UIResource
{
    /**
     * MIME type for inline HTML content (rendered via iframe srcDoc).
     */
    public const MIME_HTML = 'text/html';

    /**
     * MIME type for external URL content (rendered via iframe src).
     */
    public const MIME_URL = 'text/uri-list';

    /**
     * MIME type for Remote DOM content with React flavor.
     */
    public const MIME_REMOTE_DOM_REACT = 'application/vnd.mcp-ui.remote-dom+javascript; flavor=react';

    /**
     * MIME type for Remote DOM content with Web Components flavor.
     */
    public const MIME_REMOTE_DOM_WC = 'application/vnd.mcp-ui.remote-dom+javascript; flavor=webcomponents';

    /**
     * Create an HTML resource for inline HTML content.
     *
     * This is the most common type of UI resource. The HTML is rendered
     * in a sandboxed iframe using the srcDoc attribute.
     *
     * @param string $uri    Unique identifier (must start with "ui://")
     * @param string $html   The HTML content to render
     * @param bool   $base64 Whether to base64 encode the content (use for large content)
     *
     * @return array The resource content block for inclusion in tool response
     *
     * @throws InvalidArgumentException If URI doesn't start with "ui://"
     *
     * @example
     * ```php
     * $resource = UIResource::html(
     *     'ui://weather/sydney',
     *     '<html><body><h1>Sydney: 25°C</h1></body></html>'
     * );
     * ```
     */
    public static function html(string $uri, string $html, bool $base64 = false): array
    {
        self::validateUri($uri);

        $resource = [
            'uri' => $uri,
            'mimeType' => self::MIME_HTML,
        ];

        if ($base64) {
            $resource['blob'] = base64_encode($html);
        } else {
            $resource['text'] = $html;
        }

        return [
            'type' => 'resource',
            'resource' => $resource,
        ];
    }

    /**
     * Create a URL resource for embedding external content.
     *
     * The URL is loaded in a sandboxed iframe using the src attribute.
     * Useful for embedding external dashboards, maps, or other web content.
     *
     * @param string $uri Unique identifier (must start with "ui://")
     * @param string $url The external URL to embed in an iframe
     *
     * @return array The resource content block for inclusion in tool response
     *
     * @throws InvalidArgumentException If URI doesn't start with "ui://"
     *
     * @example
     * ```php
     * $resource = UIResource::url(
     *     'ui://map/location',
     *     'https://maps.example.com/embed?q=Sydney'
     * );
     * ```
     */
    public static function url(string $uri, string $url): array
    {
        self::validateUri($uri);

        return [
            'type' => 'resource',
            'resource' => [
                'uri' => $uri,
                'mimeType' => self::MIME_URL,
                'text' => $url,
            ],
        ];
    }

    /**
     * Create a Remote DOM resource for dynamic UI via Shopify's remote-dom.
     *
     * Remote DOM allows creating UIs that match the host's look-and-feel
     * by using a component library defined by the host. The script runs
     * in a sandboxed iframe and communicates UI changes via JSON.
     *
     * @param string $uri    Unique identifier (must start with "ui://")
     * @param string $script JavaScript that builds the UI using remote-dom
     * @param string $flavor 'react' or 'webcomponents'
     *
     * @return array The resource content block for inclusion in tool response
     *
     * @throws InvalidArgumentException If URI doesn't start with "ui://"
     *
     * @example
     * ```php
     * $script = <<<'JS'
     * const button = document.createElement('ui-button');
     * button.setAttribute('label', 'Click me!');
     * button.addEventListener('press', () => {
     *     window.parent.postMessage({
     *         type: 'notify',
     *         payload: { message: 'Button clicked!' }
     *     }, '*');
     * });
     * root.appendChild(button);
     * JS;
     *
     * $resource = UIResource::remoteDom('ui://button/1', $script, 'react');
     * ```
     */
    public static function remoteDom(string $uri, string $script, string $flavor = 'react'): array
    {
        self::validateUri($uri);

        if (!in_array($flavor, ['react', 'webcomponents'], true)) {
            throw new InvalidArgumentException(
                "Invalid Remote DOM flavor: {$flavor}. Must be 'react' or 'webcomponents'."
            );
        }

        $mimeType = $flavor === 'react'
            ? self::MIME_REMOTE_DOM_REACT
            : self::MIME_REMOTE_DOM_WC;

        return [
            'type' => 'resource',
            'resource' => [
                'uri' => $uri,
                'mimeType' => $mimeType,
                'text' => $script,
            ],
        ];
    }

    /**
     * Generate JavaScript helper functions for UI actions.
     *
     * Include this script in your HTML to get easy-to-use functions
     * for communicating with the MCP host.
     *
     * @return string JavaScript code to include in HTML
     *
     * @example
     * ```php
     * $html = '<html><head><script>' . UIResource::actionScript() . '</script></head>
     *          <body><button onclick="mcpNotify(\'Hello!\')">Click</button></body></html>';
     * ```
     */
    public static function actionScript(): string
    {
        return <<<'JS'
        /**
         * Trigger a tool call on the MCP host.
         * @param {string} toolName - Name of the tool to call
         * @param {object} params - Parameters to pass to the tool
         * @param {string} [messageId] - Optional ID for async response handling
         */
        function mcpToolCall(toolName, params, messageId) {
            window.parent.postMessage({
                type: 'tool',
                payload: { toolName, params },
                messageId: messageId
            }, '*');
        }

        /**
         * Send a notification to the MCP host.
         * @param {string} message - Notification message
         */
        function mcpNotify(message) {
            window.parent.postMessage({
                type: 'notify',
                payload: { message }
            }, '*');
        }

        /**
         * Send a prompt/message to be added to the conversation.
         * @param {string} prompt - The prompt text
         */
        function mcpPrompt(prompt) {
            window.parent.postMessage({
                type: 'prompt',
                payload: { prompt }
            }, '*');
        }

        /**
         * Request the host to open a URL.
         * @param {string} url - URL to open
         */
        function mcpLink(url) {
            window.parent.postMessage({
                type: 'link',
                payload: { url }
            }, '*');
        }

        /**
         * Send an intent to the host for custom handling.
         * @param {string} intent - Intent identifier
         * @param {object} params - Intent parameters
         */
        function mcpIntent(intent, params) {
            window.parent.postMessage({
                type: 'intent',
                payload: { intent, params }
            }, '*');
        }

        /**
         * Listen for responses from the host (for async tool calls).
         * @param {string} messageId - The messageId to listen for
         * @param {function} callback - Function to call with the result
         */
        function mcpOnResponse(messageId, callback) {
            const handler = function(event) {
                if (event.data && event.data.messageId === messageId) {
                    window.removeEventListener('message', handler);
                    callback(event.data.result);
                }
            };
            window.addEventListener('message', handler);
        }
        JS;
    }

    /**
     * Validate that a URI starts with the required "ui://" prefix.
     *
     * @param string $uri The URI to validate
     *
     * @throws InvalidArgumentException If URI is invalid
     */
    private static function validateUri(string $uri): void
    {
        if (!str_starts_with($uri, 'ui://')) {
            throw new InvalidArgumentException(
                "UIResource URI must start with 'ui://', got: {$uri}"
            );
        }
    }
}

