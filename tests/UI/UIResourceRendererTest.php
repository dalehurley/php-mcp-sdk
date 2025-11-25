<?php

declare(strict_types=1);

namespace MCP\Tests\UI;

use MCP\UI\UIResourceData;
use MCP\UI\UIResourceRenderer;
use MCP\UI\UITemplate;
use PHPUnit\Framework\TestCase;

/**
 * Tests for UIResourceRenderer class.
 */
class UIResourceRendererTest extends TestCase
{
    private function createHtmlResource(): UIResourceData
    {
        return new UIResourceData(
            uri: 'ui://test/widget',
            type: 'html',
            mimeType: 'text/html',
            content: '<html><body>Test</body></html>',
            encoding: 'text'
        );
    }

    private function createUrlResource(): UIResourceData
    {
        return new UIResourceData(
            uri: 'ui://test/external',
            type: 'url',
            mimeType: 'text/uri-list',
            content: 'https://example.com/embed',
            encoding: 'text'
        );
    }

    public function testRenderIframeForHtmlResource(): void
    {
        $resource = $this->createHtmlResource();
        $html = UIResourceRenderer::renderIframe($resource);

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('srcdoc=', $html);
        $this->assertStringContainsString('id="mcp-ui-', $html);
        $this->assertStringContainsString('sandbox="allow-scripts allow-forms"', $html);
        $this->assertStringContainsString('border-radius: 12px', $html);
    }

    public function testRenderIframeForUrlResource(): void
    {
        $resource = $this->createUrlResource();
        $html = UIResourceRenderer::renderIframe($resource);

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('src="https://example.com/embed"', $html);
        $this->assertStringNotContainsString('srcdoc=', $html);
    }

    public function testRenderIframeWithCustomOptions(): void
    {
        $resource = $this->createHtmlResource();
        $html = UIResourceRenderer::renderIframe($resource, [
            'width' => '500px',
            'height' => '300px',
            'sandbox' => 'allow-scripts',
            'class' => 'custom-class',
            'title' => 'Custom Title',
            'loading' => 'eager',
        ]);

        $this->assertStringContainsString('width: 500px', $html);
        $this->assertStringContainsString('height: 300px', $html);
        $this->assertStringContainsString('sandbox="allow-scripts"', $html);
        $this->assertStringContainsString('class="custom-class"', $html);
        $this->assertStringContainsString('title="Custom Title"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
    }

    public function testRenderIframeForRemoteDomShowsPlaceholder(): void
    {
        $resource = new UIResourceData(
            uri: 'ui://test/dynamic',
            type: 'remoteDom',
            mimeType: 'application/vnd.mcp-ui.remote-dom',
            content: 'script content',
            encoding: 'text',
            flavor: 'react'
        );

        $html = UIResourceRenderer::renderIframe($resource);

        $this->assertStringContainsString('data-mcp-ui-type="remoteDom"', $html);
        $this->assertStringContainsString('data-mcp-ui-flavor="react"', $html);
        $this->assertStringContainsString('@mcp-ui/client', $html);
    }

    public function testRenderIframeForUnknownTypeShowsUnsupported(): void
    {
        $resource = new UIResourceData(
            uri: 'ui://test/unknown',
            type: 'unknown',
            mimeType: 'application/x-unknown',
            content: 'content',
            encoding: 'text'
        );

        $html = UIResourceRenderer::renderIframe($resource);

        $this->assertStringContainsString('mcp-ui-unsupported', $html);
        $this->assertStringContainsString('Unsupported UI resource type', $html);
    }

    public function testRenderAll(): void
    {
        $resources = [
            $this->createHtmlResource(),
            $this->createUrlResource(),
        ];

        $html = UIResourceRenderer::renderAll($resources);

        $this->assertStringContainsString('srcdoc=', $html);
        $this->assertStringContainsString('src="https://example.com', $html);
        $this->assertSame(2, substr_count($html, '<iframe'));
    }

    public function testRenderGrid(): void
    {
        $resources = [
            $this->createHtmlResource(),
            $this->createHtmlResource(),
        ];

        $html = UIResourceRenderer::renderGrid($resources, [], [
            'columns' => 2,
            'gap' => '15px',
            'class' => 'my-grid'
        ]);

        $this->assertStringContainsString('class="my-grid"', $html);
        $this->assertStringContainsString('grid-template-columns: repeat(2, 1fr)', $html);
        $this->assertStringContainsString('gap: 15px', $html);
    }

    public function testActionHandlerScript(): void
    {
        $script = UIResourceRenderer::actionHandlerScript([
            'endpoint' => '/api/mcp/action',
            'debug' => true,
        ]);

        $this->assertStringContainsString('<script>', $script);
        $this->assertStringContainsString("fetch('/api/mcp/action'", $script);
        $this->assertStringContainsString('MCP_UI_DEBUG = true', $script);
        $this->assertStringContainsString("addEventListener('message'", $script);
        $this->assertStringContainsString('postMessage', $script);
    }

    public function testActionHandlerScriptWithCustomCallback(): void
    {
        $script = UIResourceRenderer::actionHandlerScript([
            'endpoint' => '/api/action',
            'onAction' => 'myCustomHandler',
        ]);

        $this->assertStringContainsString('myCustomHandler', $script);
    }

    public function testStyles(): void
    {
        $styles = UIResourceRenderer::styles();

        $this->assertStringContainsString('<style>', $styles);
        $this->assertStringContainsString('.mcp-ui-frame', $styles);
        $this->assertStringContainsString('.mcp-ui-grid', $styles);
        $this->assertStringContainsString('.mcp-ui-unsupported', $styles);
    }

    public function testStylesWithCustomPrefix(): void
    {
        $styles = UIResourceRenderer::styles(['prefix' => 'my-ui']);

        $this->assertStringContainsString('.my-ui-frame', $styles);
        $this->assertStringContainsString('.my-ui-grid', $styles);
    }

    public function testSandboxConstants(): void
    {
        $this->assertSame('allow-scripts allow-forms', UIResourceRenderer::DEFAULT_SANDBOX);
        $this->assertSame('', UIResourceRenderer::STRICT_SANDBOX);
        $this->assertStringContainsString('allow-scripts', UIResourceRenderer::PERMISSIVE_SANDBOX);
        $this->assertStringContainsString('allow-same-origin', UIResourceRenderer::PERMISSIVE_SANDBOX);
    }
}

/**
 * Tests for UITemplate class.
 */
class UITemplateTest extends TestCase
{
    public function testCardTemplate(): void
    {
        $html = UITemplate::card([
            'title' => 'Test Card',
            'content' => '<p>Test content</p>',
            'icon' => '🔥',
        ]);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Test Card', $html);
        $this->assertStringContainsString('<p>Test content</p>', $html);
        $this->assertStringContainsString('🔥', $html);
    }

    public function testCardTemplateWithActions(): void
    {
        $html = UITemplate::card([
            'title' => 'Card',
            'content' => 'Content',
            'actions' => [
                ['label' => 'Save', 'onclick' => "mcpNotify('saved')"],
                ['label' => 'Cancel', 'onclick' => "mcpNotify('cancelled')", 'class' => 'btn btn-secondary'],
            ],
        ]);

        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('Cancel', $html);
        $this->assertStringContainsString("mcpNotify('saved')", $html);
        $this->assertStringContainsString('btn-secondary', $html);
    }

    public function testCardTemplateWithFooter(): void
    {
        $html = UITemplate::card([
            'title' => 'Card',
            'content' => 'Content',
            'footer' => 'Footer text',
        ]);

        $this->assertStringContainsString('Footer text', $html);
        $this->assertStringContainsString('class="footer"', $html);
    }

    public function testCardTemplateWithCustomGradient(): void
    {
        $html = UITemplate::card([
            'title' => 'Card',
            'content' => 'Content',
            'gradient' => '#ff0000, #00ff00',
        ]);

        $this->assertStringContainsString('#ff0000, #00ff00', $html);
    }

    public function testTableTemplate(): void
    {
        $html = UITemplate::table(
            'Users',
            ['ID', 'Name', 'Email'],
            [
                [1, 'Alice', 'alice@example.com'],
                [2, 'Bob', 'bob@example.com'],
            ]
        );

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Users', $html);
        $this->assertStringContainsString('<th>ID</th>', $html);
        $this->assertStringContainsString('<th>Name</th>', $html);
        $this->assertStringContainsString('<td>Alice</td>', $html);
        $this->assertStringContainsString('alice@example.com', $html);
    }

    public function testTableTemplateWithOptions(): void
    {
        $html = UITemplate::table(
            'Data',
            ['Col1', 'Col2'],
            [[1, 2]],
            [
                'gradient' => UITemplate::GRADIENT_GREEN,
                'striped' => true,
                'hoverable' => true,
            ]
        );

        $this->assertStringContainsString(UITemplate::GRADIENT_GREEN, $html);
    }

    public function testStatsTemplate(): void
    {
        $html = UITemplate::stats([
            ['label' => 'Revenue', 'value' => '$1,234', 'icon' => '💰'],
            ['label' => 'Users', 'value' => '567', 'icon' => '👥'],
        ], [
            'title' => 'Dashboard',
            'columns' => 2,
        ]);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringContainsString('Revenue', $html);
        $this->assertStringContainsString('$1,234', $html);
        $this->assertStringContainsString('💰', $html);
        $this->assertStringContainsString('grid-template-columns: repeat(2, 1fr)', $html);
    }

    public function testStatsTemplateWithColors(): void
    {
        $html = UITemplate::stats([
            ['label' => 'Test', 'value' => '100', 'color' => '#ff0000'],
        ]);

        $this->assertStringContainsString('color: #ff0000', $html);
    }

    public function testFormTemplate(): void
    {
        $html = UITemplate::form([
            ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea'],
        ], [
            'title' => 'Contact Form',
            'submitLabel' => 'Send',
        ]);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Contact Form', $html);
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('Send', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function testFormTemplateWithSelect(): void
    {
        $html = UITemplate::form([
            [
                'name' => 'role',
                'label' => 'Role',
                'type' => 'select',
                'options' => [
                    'user' => 'User',
                    'admin' => 'Admin',
                ],
            ],
        ]);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('value="user"', $html);
        $this->assertStringContainsString('>User<', $html);
        $this->assertStringContainsString('value="admin"', $html);
        $this->assertStringContainsString('>Admin<', $html);
    }

    public function testFormTemplateWithSubmitTool(): void
    {
        $html = UITemplate::form([
            ['name' => 'data', 'type' => 'text'],
        ], [
            'submitTool' => 'process_form',
        ]);

        $this->assertStringContainsString("mcpToolCall('process_form'", $html);
    }

    public function testGradientConstants(): void
    {
        $this->assertNotEmpty(UITemplate::DEFAULT_GRADIENT);
        $this->assertNotEmpty(UITemplate::GRADIENT_GREEN);
        $this->assertNotEmpty(UITemplate::GRADIENT_PINK);
        $this->assertNotEmpty(UITemplate::GRADIENT_BLUE);
        $this->assertNotEmpty(UITemplate::GRADIENT_ORANGE);
        $this->assertNotEmpty(UITemplate::GRADIENT_DARK);
    }

    public function testTemplatesIncludeActionScript(): void
    {
        $card = UITemplate::card(['title' => 'T', 'content' => 'C']);
        $table = UITemplate::table('T', ['H'], [[1]]);
        $stats = UITemplate::stats([['label' => 'L', 'value' => 'V']]);
        $form = UITemplate::form([['name' => 'n']]);

        foreach ([$card, $table, $stats, $form] as $html) {
            $this->assertStringContainsString('mcpToolCall', $html);
            $this->assertStringContainsString('mcpNotify', $html);
        }
    }

    public function testTemplatesEscapeHtml(): void
    {
        $html = UITemplate::card([
            'title' => '<script>alert("xss")</script>',
            'content' => 'Safe content',
        ]);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testTableEscapesCellContent(): void
    {
        $html = UITemplate::table(
            'Test',
            ['Header'],
            [['<script>alert("xss")</script>']]
        );

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}

