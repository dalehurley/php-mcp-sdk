<?php

declare(strict_types=1);

namespace MCP\Tests\UI;

use MCP\UI\UIResource;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Tests for UIResource class.
 */
class UIResourceTest extends TestCase
{
    public function testHtmlCreatesValidResource(): void
    {
        $html = '<html><body><h1>Hello</h1></body></html>';
        $resource = UIResource::html('ui://test/widget', $html);

        $this->assertSame('resource', $resource['type']);
        $this->assertSame('ui://test/widget', $resource['resource']['uri']);
        $this->assertSame('text/html', $resource['resource']['mimeType']);
        $this->assertSame($html, $resource['resource']['text']);
        $this->assertArrayNotHasKey('blob', $resource['resource']);
    }

    public function testHtmlWithBase64Encoding(): void
    {
        $html = '<html><body><h1>Hello</h1></body></html>';
        $resource = UIResource::html('ui://test/widget', $html, base64: true);

        $this->assertSame('resource', $resource['type']);
        $this->assertSame(base64_encode($html), $resource['resource']['blob']);
        $this->assertArrayNotHasKey('text', $resource['resource']);
    }

    public function testHtmlThrowsOnInvalidUri(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must start with 'ui://'");

        UIResource::html('http://invalid/uri', '<html></html>');
    }

    public function testUrlCreatesValidResource(): void
    {
        $url = 'https://example.com/embed';
        $resource = UIResource::url('ui://test/external', $url);

        $this->assertSame('resource', $resource['type']);
        $this->assertSame('ui://test/external', $resource['resource']['uri']);
        $this->assertSame('text/uri-list', $resource['resource']['mimeType']);
        $this->assertSame($url, $resource['resource']['text']);
    }

    public function testUrlThrowsOnInvalidUri(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UIResource::url('invalid-uri', 'https://example.com');
    }

    public function testRemoteDomReactFlavor(): void
    {
        $script = 'const el = document.createElement("div");';
        $resource = UIResource::remoteDom('ui://test/dynamic', $script, 'react');

        $this->assertSame('resource', $resource['type']);
        $this->assertSame('application/vnd.mcp-ui.remote-dom+javascript; flavor=react', $resource['resource']['mimeType']);
        $this->assertSame($script, $resource['resource']['text']);
    }

    public function testRemoteDomWebComponentsFlavor(): void
    {
        $script = 'const el = document.createElement("div");';
        $resource = UIResource::remoteDom('ui://test/dynamic', $script, 'webcomponents');

        $this->assertSame('application/vnd.mcp-ui.remote-dom+javascript; flavor=webcomponents', $resource['resource']['mimeType']);
    }

    public function testRemoteDomThrowsOnInvalidFlavor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Remote DOM flavor");

        UIResource::remoteDom('ui://test/dynamic', 'script', 'invalid');
    }

    public function testActionScriptContainsHelperFunctions(): void
    {
        $script = UIResource::actionScript();

        $this->assertStringContainsString('function mcpToolCall', $script);
        $this->assertStringContainsString('function mcpNotify', $script);
        $this->assertStringContainsString('function mcpPrompt', $script);
        $this->assertStringContainsString('function mcpLink', $script);
        $this->assertStringContainsString('function mcpIntent', $script);
        $this->assertStringContainsString('function mcpOnResponse', $script);
        $this->assertStringContainsString('window.parent.postMessage', $script);
    }

    public function testMimeTypeConstants(): void
    {
        $this->assertSame('text/html', UIResource::MIME_HTML);
        $this->assertSame('text/uri-list', UIResource::MIME_URL);
        $this->assertStringContainsString('remote-dom', UIResource::MIME_REMOTE_DOM_REACT);
        $this->assertStringContainsString('react', UIResource::MIME_REMOTE_DOM_REACT);
        $this->assertStringContainsString('webcomponents', UIResource::MIME_REMOTE_DOM_WC);
    }

    /**
     * @dataProvider validUriProvider
     */
    public function testAcceptsValidUris(string $uri): void
    {
        $resource = UIResource::html($uri, '<html></html>');
        $this->assertSame($uri, $resource['resource']['uri']);
    }

    public static function validUriProvider(): array
    {
        return [
            ['ui://simple'],
            ['ui://namespace/resource'],
            ['ui://namespace/resource/id'],
            ['ui://app/widget/123'],
            ['ui://my-app/my-widget'],
            ['ui://app_name/widget_name'],
        ];
    }

    /**
     * @dataProvider invalidUriProvider
     */
    public function testRejectsInvalidUris(string $uri): void
    {
        $this->expectException(InvalidArgumentException::class);
        UIResource::html($uri, '<html></html>');
    }

    public static function invalidUriProvider(): array
    {
        return [
            ['http://example.com'],
            ['https://example.com'],
            ['file://local/path'],
            ['UI://uppercase'],
            ['ui//missing-colon'],
            ['ui:/single-slash'],
            ['not-a-uri'],
            [''],
        ];
    }
}

