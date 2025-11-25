<?php

declare(strict_types=1);

namespace MCP\UI;

/**
 * Render UIResources for web output.
 *
 * This class provides methods to render UI resources as HTML,
 * typically as sandboxed iframes that can be embedded in web pages.
 *
 * @example
 * ```php
 * $resources = UIResourceParser::getUIResourcesOnly($response);
 * foreach ($resources as $resource) {
 *     echo UIResourceRenderer::renderIframe($resource, [
 *         'width' => '100%',
 *         'height' => '400px'
 *     ]);
 * }
 * ```
 */
class UIResourceRenderer
{
    /**
     * Default sandbox permissions for iframes.
     * Allows scripts and forms but restricts other capabilities.
     */
    public const DEFAULT_SANDBOX = 'allow-scripts allow-forms';

    /**
     * Strict sandbox permissions (no scripts).
     */
    public const STRICT_SANDBOX = '';

    /**
     * Permissive sandbox (allows most features except top navigation).
     */
    public const PERMISSIVE_SANDBOX = 'allow-scripts allow-forms allow-popups allow-modals allow-same-origin';

    /**
     * Render a UIResource as a sandboxed iframe.
     *
     * @param UIResourceData       $resource The resource to render
     * @param array<string, mixed> $options  Rendering options:
     *                                       - width: CSS width (default: '100%')
     *                                       - height: CSS height (default: '400px')
     *                                       - sandbox: Sandbox permissions (default: DEFAULT_SANDBOX)
     *                                       - class: CSS class(es) to add
     *                                       - style: Additional inline styles
     *                                       - title: Iframe title for accessibility
     *                                       - loading: 'lazy' or 'eager' (default: 'lazy')
     *
     * @return string HTML iframe element
     */
    public static function renderIframe(UIResourceData $resource, array $options = []): string
    {
        $width = $options['width'] ?? '100%';
        $height = $options['height'] ?? '400px';
        $sandbox = $options['sandbox'] ?? self::DEFAULT_SANDBOX;
        $class = $options['class'] ?? 'mcp-ui-frame';
        $extraStyle = $options['style'] ?? '';
        $title = $options['title'] ?? 'MCP UI Resource';
        $loading = $options['loading'] ?? 'lazy';

        $id = htmlspecialchars($resource->getSafeId(), ENT_QUOTES, 'UTF-8');
        $titleAttr = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $classAttr = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

        $style = "width: {$width}; height: {$height}; border: none; border-radius: 12px;";
        if ($extraStyle) {
            $style .= ' ' . $extraStyle;
        }

        $sandboxAttr = $sandbox ? " sandbox=\"{$sandbox}\"" : '';

        if ($resource->isHtml()) {
            $srcDoc = htmlspecialchars($resource->content, ENT_QUOTES, 'UTF-8');

            return <<<HTML
            <iframe
                id="mcp-ui-{$id}"
                class="{$classAttr}"
                srcdoc="{$srcDoc}"
                title="{$titleAttr}"
                loading="{$loading}"
                {$sandboxAttr}
                style="{$style}"
            ></iframe>
            HTML;
        }

        if ($resource->isUrl()) {
            $src = htmlspecialchars($resource->content, ENT_QUOTES, 'UTF-8');

            return <<<HTML
            <iframe
                id="mcp-ui-{$id}"
                class="{$classAttr}"
                src="{$src}"
                title="{$titleAttr}"
                loading="{$loading}"
                {$sandboxAttr}
                style="{$style}"
            ></iframe>
            HTML;
        }

        if ($resource->isRemoteDom()) {
            return self::renderRemoteDomPlaceholder($resource, $options);
        }

        // Unknown type - render a placeholder
        return <<<HTML
        <div class="mcp-ui-unsupported" style="{$style}">
            <p>Unsupported UI resource type: {$resource->type}</p>
        </div>
        HTML;
    }

    /**
     * Render multiple UIResources.
     *
     * @param array<UIResourceData> $resources Array of resources to render
     * @param array<string, mixed>  $options   Options passed to each iframe
     * @param string                $separator HTML separator between iframes
     *
     * @return string Combined HTML
     */
    public static function renderAll(
        array $resources,
        array $options = [],
        string $separator = "\n"
    ): string {
        return implode($separator, array_map(
            fn (UIResourceData $r) => self::renderIframe($r, $options),
            $resources
        ));
    }

    /**
     * Render a container with multiple UIResources in a grid layout.
     *
     * @param array<UIResourceData> $resources      Resources to render
     * @param array<string, mixed>  $options        iframe options
     * @param array<string, mixed>  $containerOptions Container options:
     *                                                - columns: Number of columns (default: 2)
     *                                                - gap: CSS gap value (default: '20px')
     *                                                - class: Container CSS class
     *
     * @return string HTML grid container
     */
    public static function renderGrid(
        array $resources,
        array $options = [],
        array $containerOptions = []
    ): string {
        $columns = $containerOptions['columns'] ?? 2;
        $gap = $containerOptions['gap'] ?? '20px';
        $class = $containerOptions['class'] ?? 'mcp-ui-grid';
        $classAttr = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

        $items = self::renderAll($resources, $options, "\n");

        return <<<HTML
        <div class="{$classAttr}" style="display: grid; grid-template-columns: repeat({$columns}, 1fr); gap: {$gap};">
            {$items}
        </div>
        HTML;
    }

    /**
     * Generate the JavaScript handler for UI actions.
     *
     * Include this script once on pages that render MCP UI resources.
     * It listens for postMessage events from iframes and forwards them
     * to your backend endpoint.
     *
     * @param array<string, mixed> $options Configuration options:
     *                                      - endpoint: Backend URL for actions (default: '/mcp/ui-action')
     *                                      - onAction: Custom JS callback function name
     *                                      - debug: Enable console logging (default: false)
     *
     * @return string JavaScript wrapped in script tags
     */
    public static function actionHandlerScript(array $options = []): string
    {
        $endpoint = $options['endpoint'] ?? '/mcp/ui-action';
        $onAction = $options['onAction'] ?? null;
        $debug = ($options['debug'] ?? false) ? 'true' : 'false';

        $customCallback = $onAction
            ? "if (typeof {$onAction} === 'function') { {$onAction}(event.data, result); }"
            : '';

        return <<<JS
        <script>
        (function() {
            const MCP_UI_DEBUG = {$debug};

            window.addEventListener('message', async function(event) {
                // Validate message structure
                if (!event.data || !event.data.type || !event.data.payload) {
                    return;
                }

                const { type, payload, messageId } = event.data;

                if (MCP_UI_DEBUG) {
                    console.log('[MCP-UI] Received action:', type, payload);
                }

                try {
                    const response = await fetch('{$endpoint}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ type, payload, messageId })
                    });

                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    const result = await response.json();

                    if (MCP_UI_DEBUG) {
                        console.log('[MCP-UI] Action result:', result);
                    }

                    // Send response back to iframe if messageId provided
                    if (messageId && event.source) {
                        event.source.postMessage({
                            messageId: messageId,
                            result: result
                        }, '*');
                    }

                    {$customCallback}

                } catch (error) {
                    console.error('[MCP-UI] Action failed:', error);

                    // Send error back to iframe
                    if (messageId && event.source) {
                        event.source.postMessage({
                            messageId: messageId,
                            error: error.message
                        }, '*');
                    }
                }
            });

            if (MCP_UI_DEBUG) {
                console.log('[MCP-UI] Action handler initialized');
            }
        })();
        </script>
        JS;
    }

    /**
     * Generate CSS styles for MCP UI resources.
     *
     * @param array<string, mixed> $options Style options:
     *                                      - prefix: CSS class prefix (default: 'mcp-ui')
     *
     * @return string CSS wrapped in style tags
     */
    public static function styles(array $options = []): string
    {
        $prefix = $options['prefix'] ?? 'mcp-ui';

        return <<<CSS
        <style>
        .{$prefix}-frame {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.2s ease;
        }
        .{$prefix}-frame:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .{$prefix}-grid {
            display: grid;
            gap: 20px;
        }
        .{$prefix}-unsupported {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border-radius: 12px;
            color: #666;
            font-family: system-ui, sans-serif;
        }
        .{$prefix}-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 12px;
        }
        </style>
        CSS;
    }

    /**
     * Render a placeholder for Remote DOM resources.
     *
     * Remote DOM requires special client-side handling that is typically
     * done by the @mcp-ui/client package in JavaScript.
     *
     * @param UIResourceData       $resource The Remote DOM resource
     * @param array<string, mixed> $options  Rendering options
     *
     * @return string HTML placeholder with data attributes
     */
    private static function renderRemoteDomPlaceholder(
        UIResourceData $resource,
        array $options
    ): string {
        $width = $options['width'] ?? '100%';
        $height = $options['height'] ?? '400px';
        $id = htmlspecialchars($resource->getSafeId(), ENT_QUOTES, 'UTF-8');
        $flavor = htmlspecialchars($resource->flavor ?? 'react', ENT_QUOTES, 'UTF-8');
        $script = htmlspecialchars($resource->content, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div
            id="mcp-ui-{$id}"
            class="mcp-ui-remote-dom"
            data-mcp-ui-type="remoteDom"
            data-mcp-ui-flavor="{$flavor}"
            data-mcp-ui-script="{$script}"
            style="width: {$width}; height: {$height};"
        >
            <p style="text-align: center; color: #666; padding: 20px;">
                Remote DOM resource requires @mcp-ui/client for rendering.
            </p>
        </div>
        HTML;
    }
}

