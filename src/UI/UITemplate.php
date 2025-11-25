<?php

declare(strict_types=1);

namespace MCP\UI;

/**
 * Pre-built UI templates for common patterns.
 *
 * Provides ready-to-use HTML templates for creating UI resources
 * without writing HTML from scratch.
 *
 * @example
 * ```php
 * // Create a card widget
 * $html = UITemplate::card([
 *     'title' => 'User Profile',
 *     'content' => '<p>John Doe</p><p>john@example.com</p>',
 *     'actions' => [
 *         ['label' => 'Edit', 'onclick' => "mcpToolCall('edit_user', {id: 123})"]
 *     ]
 * ]);
 *
 * return [
 *     'content' => [
 *         UIResource::html('ui://user/profile', $html)
 *     ]
 * ];
 * ```
 */
class UITemplate
{
    /**
     * Default gradient used across templates.
     */
    public const DEFAULT_GRADIENT = '#667eea, #764ba2';

    /**
     * Alternative gradients for variety.
     */
    public const GRADIENT_GREEN = '#11998e, #38ef7d';
    public const GRADIENT_PINK = '#f093fb, #f5576c';
    public const GRADIENT_BLUE = '#2c3e50, #3498db';
    public const GRADIENT_ORANGE = '#f5af19, #f12711';
    public const GRADIENT_DARK = '#232526, #414345';

    /**
     * Create a card-style widget.
     *
     * @param array<string, mixed> $options Card options:
     *                                      - title: Card title (required)
     *                                      - content: HTML content (required)
     *                                      - gradient: CSS gradient colors
     *                                      - actions: Array of action buttons
     *                                      - footer: Optional footer HTML
     *                                      - icon: Optional emoji/icon before title
     *
     * @return string Complete HTML document
     */
    public static function card(array $options): string
    {
        $title = htmlspecialchars($options['title'] ?? 'Card', ENT_QUOTES, 'UTF-8');
        $content = $options['content'] ?? '';
        $gradient = $options['gradient'] ?? self::DEFAULT_GRADIENT;
        $actions = $options['actions'] ?? [];
        $footer = $options['footer'] ?? '';
        $icon = $options['icon'] ?? '';

        $titleHtml = $icon ? "{$icon} {$title}" : $title;

        $actionButtons = '';
        foreach ($actions as $action) {
            $label = htmlspecialchars($action['label'] ?? 'Action', ENT_QUOTES, 'UTF-8');
            $onclick = htmlspecialchars($action['onclick'] ?? '', ENT_QUOTES, 'UTF-8');
            $class = $action['class'] ?? 'btn';
            $actionButtons .= "<button class=\"{$class}\" onclick=\"{$onclick}\">{$label}</button>";
        }

        $actionsHtml = $actionButtons ? "<div class=\"actions\">{$actionButtons}</div>" : '';
        $footerHtml = $footer ? "<div class=\"footer\">{$footer}</div>" : '';
        $script = self::_SCRIPT;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, {$gradient});
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .card {
                    background: white;
                    border-radius: 20px;
                    padding: 30px;
                    max-width: 400px;
                    width: 100%;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                }
                .title {
                    font-size: 24px;
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 20px;
                }
                .content {
                    color: #555;
                    line-height: 1.6;
                }
                .actions {
                    margin-top: 20px;
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .btn {
                    padding: 12px 20px;
                    background: linear-gradient(135deg, {$gradient});
                    color: white;
                    border: none;
                    border-radius: 10px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
                }
                .btn-secondary {
                    background: #f5f5f5;
                    color: #333;
                }
                .btn-secondary:hover {
                    background: #eee;
                }
                .footer {
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid #eee;
                    font-size: 14px;
                    color: #888;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="title">{$titleHtml}</div>
                <div class="content">{$content}</div>
                {$actionsHtml}
                {$footerHtml}
            </div>
            <script>
            {$script}
            </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Create a data table widget.
     *
     * @param string                    $title   Table title
     * @param array<string>             $headers Column headers
     * @param array<array<string|int>>  $rows    Table data rows
     * @param array<string, mixed>      $options Additional options:
     *                                           - gradient: Background gradient
     *                                           - striped: Zebra striping (default: true)
     *                                           - hoverable: Row hover effect (default: true)
     *                                           - actions: Per-row action callbacks
     *
     * @return string Complete HTML document
     */
    public static function table(
        string $title,
        array $headers,
        array $rows,
        array $options = []
    ): string {
        $gradient = $options['gradient'] ?? self::GRADIENT_BLUE;
        $striped = $options['striped'] ?? true;
        $hoverable = $options['hoverable'] ?? true;
        $titleHtml = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        $headerHtml = implode('', array_map(
            fn($h) => '<th>' . htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') . '</th>',
            $headers
        ));

        $rowsHtml = '';
        foreach ($rows as $index => $row) {
            $cells = implode('', array_map(
                fn($c) => '<td>' . htmlspecialchars((string) $c, ENT_QUOTES, 'UTF-8') . '</td>',
                $row
            ));
            $rowClass = $striped && $index % 2 === 1 ? ' class="striped"' : '';
            $rowsHtml .= "<tr{$rowClass}>{$cells}</tr>";
        }

        $hoverStyle = $hoverable ? 'tr:hover { background: #f0f7ff; }' : '';
        $script = self::_SCRIPT;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f5f5f5;
                    padding: 20px;
                }
                .container {
                    background: white;
                    border-radius: 12px;
                    padding: 25px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    overflow-x: auto;
                }
                h2 {
                    color: #333;
                    margin-bottom: 20px;
                    font-size: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 14px;
                }
                th {
                    background: linear-gradient(135deg, {$gradient});
                    color: white;
                    padding: 14px 12px;
                    text-align: left;
                    font-weight: 600;
                }
                th:first-child { border-radius: 8px 0 0 0; }
                th:last-child { border-radius: 0 8px 0 0; }
                td {
                    padding: 12px;
                    border-bottom: 1px solid #eee;
                    color: #555;
                }
                tr.striped { background: #fafafa; }
                {$hoverStyle}
            </style>
        </head>
        <body>
            <div class="container">
                <h2>{$titleHtml}</h2>
                <table>
                    <thead><tr>{$headerHtml}</tr></thead>
                    <tbody>{$rowsHtml}</tbody>
                </table>
            </div>
            <script>
            {$script}
            </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Create a stats/metrics dashboard widget.
     *
     * @param array<array<string, string|int>> $stats Array of stat items:
     *                                                - label: Stat label
     *                                                - value: Stat value
     *                                                - icon: Optional emoji
     *                                                - color: Optional accent color
     * @param array<string, mixed>             $options Additional options:
     *                                                  - title: Dashboard title
     *                                                  - columns: Grid columns (default: 3)
     *                                                  - gradient: Background gradient
     *
     * @return string Complete HTML document
     */
    public static function stats(array $stats, array $options = []): string
    {
        $title = $options['title'] ?? '';
        $columns = $options['columns'] ?? min(count($stats), 3);
        $gradient = $options['gradient'] ?? self::DEFAULT_GRADIENT;

        $titleHtml = $title
            ? '<h2 class="dashboard-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>'
            : '';

        $statsHtml = '';
        foreach ($stats as $stat) {
            $label = htmlspecialchars((string) ($stat['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars((string) ($stat['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $icon = $stat['icon'] ?? '';
            $color = $stat['color'] ?? '';

            $iconHtml = $icon ? "<span class=\"stat-icon\">{$icon}</span>" : '';
            $colorStyle = $color ? " style=\"color: {$color};\"" : '';

            $statsHtml .= <<<HTML
            <div class="stat-card">
                {$iconHtml}
                <div class="stat-value"{$colorStyle}>{$value}</div>
                <div class="stat-label">{$label}</div>
            </div>
            HTML;
        }
        $script = self::_SCRIPT;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, {$gradient});
                    min-height: 100vh;
                    padding: 20px;
                }
                .dashboard {
                    max-width: 800px;
                    margin: 0 auto;
                }
                .dashboard-title {
                    color: white;
                    text-align: center;
                    margin-bottom: 25px;
                    font-size: 24px;
                    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat({$columns}, 1fr);
                    gap: 20px;
                }
                .stat-card {
                    background: white;
                    border-radius: 16px;
                    padding: 25px;
                    text-align: center;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                    transition: transform 0.2s;
                }
                .stat-card:hover {
                    transform: translateY(-5px);
                }
                .stat-icon {
                    font-size: 32px;
                    margin-bottom: 10px;
                    display: block;
                }
                .stat-value {
                    font-size: 36px;
                    font-weight: 700;
                    color: #333;
                    margin-bottom: 5px;
                }
                .stat-label {
                    font-size: 14px;
                    color: #888;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                @media (max-width: 600px) {
                    .stats-grid { grid-template-columns: repeat(2, 1fr); }
                }
            </style>
        </head>
        <body>
            <div class="dashboard">
                {$titleHtml}
                <div class="stats-grid">
                    {$statsHtml}
                </div>
            </div>
            <script>
            {$script}
            </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Create a form widget.
     *
     * @param array<array<string, mixed>> $fields Form fields:
     *                                            - name: Field name (required)
     *                                            - label: Field label
     *                                            - type: Input type (text, email, number, textarea, select)
     *                                            - required: Is required
     *                                            - placeholder: Placeholder text
     *                                            - options: For select, array of options
     * @param array<string, mixed>        $options Form options:
     *                                             - title: Form title
     *                                             - submitLabel: Submit button text
     *                                             - submitTool: Tool to call on submit
     *                                             - gradient: Background gradient
     *
     * @return string Complete HTML document
     */
    public static function form(array $fields, array $options = []): string
    {
        $title = htmlspecialchars($options['title'] ?? 'Form', ENT_QUOTES, 'UTF-8');
        $submitLabel = htmlspecialchars($options['submitLabel'] ?? 'Submit', ENT_QUOTES, 'UTF-8');
        $submitTool = $options['submitTool'] ?? '';
        $gradient = $options['gradient'] ?? self::DEFAULT_GRADIENT;

        $fieldsHtml = '';
        foreach ($fields as $field) {
            $name = htmlspecialchars($field['name'] ?? '', ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($field['label'] ?? ucfirst($name), ENT_QUOTES, 'UTF-8');
            $type = $field['type'] ?? 'text';
            $required = ($field['required'] ?? false) ? 'required' : '';
            $placeholder = htmlspecialchars($field['placeholder'] ?? '', ENT_QUOTES, 'UTF-8');

            $inputHtml = match ($type) {
                'textarea' => "<textarea name=\"{$name}\" id=\"{$name}\" placeholder=\"{$placeholder}\" {$required}></textarea>",
                'select' => self::renderSelect($name, $field['options'] ?? [], $required),
                default => "<input type=\"{$type}\" name=\"{$name}\" id=\"{$name}\" placeholder=\"{$placeholder}\" {$required}>",
            };

            $requiredMark = $required ? '<span class="required">*</span>' : '';

            $fieldsHtml .= <<<HTML
            <div class="field">
                <label for="{$name}">{$label}{$requiredMark}</label>
                {$inputHtml}
            </div>
            HTML;
        }

        $submitToolJs = $submitTool
            ? "mcpToolCall('{$submitTool}', formData);"
            : "mcpNotify('Form submitted: ' + JSON.stringify(formData));";
        $script = self::_SCRIPT;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, {$gradient});
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .form-card {
                    background: white;
                    border-radius: 20px;
                    padding: 30px;
                    max-width: 450px;
                    width: 100%;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                }
                .form-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 25px;
                }
                .field {
                    margin-bottom: 20px;
                }
                label {
                    display: block;
                    font-size: 14px;
                    font-weight: 500;
                    color: #555;
                    margin-bottom: 8px;
                }
                .required { color: #e74c3c; margin-left: 2px; }
                input, textarea, select {
                    width: 100%;
                    padding: 12px 16px;
                    border: 2px solid #eee;
                    border-radius: 10px;
                    font-size: 14px;
                    transition: border-color 0.2s;
                    font-family: inherit;
                }
                input:focus, textarea:focus, select:focus {
                    outline: none;
                    border-color: #667eea;
                }
                textarea { min-height: 100px; resize: vertical; }
                .submit-btn {
                    width: 100%;
                    padding: 14px;
                    background: linear-gradient(135deg, {$gradient});
                    color: white;
                    border: none;
                    border-radius: 10px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .submit-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
                }
            </style>
        </head>
        <body>
            <div class="form-card">
                <div class="form-title">{$title}</div>
                <form id="mcpForm" onsubmit="return handleSubmit(event)">
                    {$fieldsHtml}
                    <button type="submit" class="submit-btn">{$submitLabel}</button>
                </form>
            </div>
            <script>
            {$script}
            function handleSubmit(e) {
                e.preventDefault();
                const form = document.getElementById('mcpForm');
                const formData = Object.fromEntries(new FormData(form));
                {$submitToolJs}
                return false;
            }
            </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Render a select element.
     */
    private static function renderSelect(string $name, array $options, string $required): string
    {
        $optionsHtml = '<option value="">Select...</option>';
        foreach ($options as $value => $label) {
            $val = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $lbl = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
            $optionsHtml .= "<option value=\"{$val}\">{$lbl}</option>";
        }

        return "<select name=\"{$name}\" id=\"{$name}\" {$required}>{$optionsHtml}</select>";
    }

    /**
     * JavaScript helper script placeholder.
     * Gets replaced with UIResource::actionScript() content.
     */
    private const _SCRIPT = <<<'JS'
    function mcpToolCall(toolName, params, messageId) {
        window.parent.postMessage({ type: 'tool', payload: { toolName, params }, messageId }, '*');
    }
    function mcpNotify(message) {
        window.parent.postMessage({ type: 'notify', payload: { message } }, '*');
    }
    function mcpPrompt(prompt) {
        window.parent.postMessage({ type: 'prompt', payload: { prompt } }, '*');
    }
    function mcpLink(url) {
        window.parent.postMessage({ type: 'link', payload: { url } }, '*');
    }
    JS;
}
