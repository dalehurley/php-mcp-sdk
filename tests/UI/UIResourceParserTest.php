<?php

declare(strict_types=1);

namespace MCP\Tests\UI;

use MCP\UI\UIResource;
use MCP\UI\UIResourceData;
use MCP\UI\UIResourceParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for UIResourceParser and UIResourceData classes.
 */
class UIResourceParserTest extends TestCase
{
    private function createMockResponse(array $content): array
    {
        return ['content' => $content];
    }

    public function testIsUIResourceReturnsTrueForValidUIResource(): void
    {
        $content = UIResource::html('ui://test/widget', '<html></html>');
        
        $this->assertTrue(UIResourceParser::isUIResource($content));
    }

    public function testIsUIResourceReturnsFalseForTextContent(): void
    {
        $content = ['type' => 'text', 'text' => 'Hello'];
        
        $this->assertFalse(UIResourceParser::isUIResource($content));
    }

    public function testIsUIResourceReturnsFalseForNonUIResource(): void
    {
        $content = [
            'type' => 'resource',
            'resource' => ['uri' => 'file://local/path']
        ];
        
        $this->assertFalse(UIResourceParser::isUIResource($content));
    }

    public function testIsUIResourceReturnsFalseForEmptyArray(): void
    {
        $this->assertFalse(UIResourceParser::isUIResource([]));
    }

    public function testHasUIResourcesReturnsTrueWhenPresent(): void
    {
        $response = $this->createMockResponse([
            ['type' => 'text', 'text' => 'Hello'],
            UIResource::html('ui://test/widget', '<html></html>')
        ]);

        $this->assertTrue(UIResourceParser::hasUIResources($response));
    }

    public function testHasUIResourcesReturnsFalseWhenAbsent(): void
    {
        $response = $this->createMockResponse([
            ['type' => 'text', 'text' => 'Hello']
        ]);

        $this->assertFalse(UIResourceParser::hasUIResources($response));
    }

    public function testCountUIResources(): void
    {
        $response = $this->createMockResponse([
            ['type' => 'text', 'text' => 'Hello'],
            UIResource::html('ui://test/widget1', '<html></html>'),
            ['type' => 'text', 'text' => 'World'],
            UIResource::html('ui://test/widget2', '<html></html>'),
            UIResource::url('ui://test/external', 'https://example.com'),
        ]);

        $this->assertSame(3, UIResourceParser::countUIResources($response));
    }

    public function testParseSeparatesTextAndUI(): void
    {
        $response = $this->createMockResponse([
            ['type' => 'text', 'text' => 'Hello'],
            UIResource::html('ui://test/widget', '<html>Content</html>'),
            ['type' => 'text', 'text' => 'World'],
        ]);

        $parsed = UIResourceParser::parse($response);

        $this->assertCount(2, $parsed['text']);
        $this->assertCount(1, $parsed['ui']);
        $this->assertSame('Hello', $parsed['text'][0]['text']);
        $this->assertSame('World', $parsed['text'][1]['text']);
        $this->assertInstanceOf(UIResourceData::class, $parsed['ui'][0]);
    }

    public function testParseResourceCreatesUIResourceData(): void
    {
        $html = '<html><body>Test</body></html>';
        $resource = UIResource::html('ui://test/widget', $html);

        $data = UIResourceParser::parseResource($resource['resource']);

        $this->assertInstanceOf(UIResourceData::class, $data);
        $this->assertSame('ui://test/widget', $data->uri);
        $this->assertSame('html', $data->type);
        $this->assertSame('text/html', $data->mimeType);
        $this->assertSame($html, $data->content);
        $this->assertSame('text', $data->encoding);
        $this->assertNull($data->flavor);
    }

    public function testParseResourceHandlesBase64Content(): void
    {
        $html = '<html><body>Test</body></html>';
        $resource = UIResource::html('ui://test/widget', $html, base64: true);

        $data = UIResourceParser::parseResource($resource['resource']);

        $this->assertSame($html, $data->content); // Should be decoded
        $this->assertSame('blob', $data->encoding);
    }

    public function testParseResourceHandlesUrlType(): void
    {
        $url = 'https://example.com/embed';
        $resource = UIResource::url('ui://test/external', $url);

        $data = UIResourceParser::parseResource($resource['resource']);

        $this->assertSame('url', $data->type);
        $this->assertSame('text/uri-list', $data->mimeType);
        $this->assertSame($url, $data->content);
    }

    public function testParseResourceHandlesRemoteDom(): void
    {
        $script = 'const el = document.createElement("div");';
        $resource = UIResource::remoteDom('ui://test/dynamic', $script, 'react');

        $data = UIResourceParser::parseResource($resource['resource']);

        $this->assertSame('remoteDom', $data->type);
        $this->assertSame('react', $data->flavor);
    }

    public function testDetermineType(): void
    {
        $this->assertSame('html', UIResourceParser::determineType('text/html'));
        $this->assertSame('url', UIResourceParser::determineType('text/uri-list'));
        $this->assertSame('remoteDom', UIResourceParser::determineType('application/vnd.mcp-ui.remote-dom+javascript; flavor=react'));
        $this->assertSame('unknown', UIResourceParser::determineType('application/json'));
    }

    public function testGetTextOnly(): void
    {
        $response = $this->createMockResponse([
            ['type' => 'text', 'text' => 'Hello'],
            UIResource::html('ui://test/widget', '<html></html>'),
            ['type' => 'text', 'text' => 'World'],
        ]);

        $text = UIResourceParser::getTextOnly($response);

        $this->assertSame("Hello\nWorld", $text);
    }

    public function testGetUIResourcesOnly(): void
    {
        $response = $this->createMockResponse([
            ['type' => 'text', 'text' => 'Hello'],
            UIResource::html('ui://test/widget1', '<html>1</html>'),
            UIResource::html('ui://test/widget2', '<html>2</html>'),
        ]);

        $resources = UIResourceParser::getUIResourcesOnly($response);

        $this->assertCount(2, $resources);
        $this->assertSame('ui://test/widget1', $resources[0]->uri);
        $this->assertSame('ui://test/widget2', $resources[1]->uri);
    }

    public function testFindByUri(): void
    {
        $response = $this->createMockResponse([
            UIResource::html('ui://test/widget1', '<html>1</html>'),
            UIResource::html('ui://test/widget2', '<html>2</html>'),
        ]);

        $found = UIResourceParser::findByUri($response, 'ui://test/widget2');
        $notFound = UIResourceParser::findByUri($response, 'ui://test/nonexistent');

        $this->assertInstanceOf(UIResourceData::class, $found);
        $this->assertSame('ui://test/widget2', $found->uri);
        $this->assertNull($notFound);
    }

    public function testFilterByType(): void
    {
        $response = $this->createMockResponse([
            UIResource::html('ui://test/html1', '<html>1</html>'),
            UIResource::url('ui://test/url1', 'https://example.com'),
            UIResource::html('ui://test/html2', '<html>2</html>'),
        ]);

        $htmlResources = UIResourceParser::filterByType($response, 'html');
        $urlResources = UIResourceParser::filterByType($response, 'url');

        $this->assertCount(2, $htmlResources);
        $this->assertCount(1, $urlResources);
    }
}

/**
 * Tests for UIResourceData class.
 */
class UIResourceDataTest extends TestCase
{
    private function createSampleData(): UIResourceData
    {
        return new UIResourceData(
            uri: 'ui://test/widget',
            type: 'html',
            mimeType: 'text/html',
            content: '<html><body>Test</body></html>',
            encoding: 'text',
            flavor: null
        );
    }

    public function testGetId(): void
    {
        $data = $this->createSampleData();

        $this->assertSame('test/widget', $data->getId());
    }

    public function testGetSafeId(): void
    {
        $data = new UIResourceData(
            uri: 'ui://test/widget with spaces',
            type: 'html',
            mimeType: 'text/html',
            content: '',
            encoding: 'text'
        );

        $this->assertSame('test%2Fwidget+with+spaces', $data->getSafeId());
    }

    public function testTypeCheckers(): void
    {
        $html = new UIResourceData('ui://a', 'html', 'text/html', '', 'text');
        $url = new UIResourceData('ui://a', 'url', 'text/uri-list', '', 'text');
        $remoteDom = new UIResourceData('ui://a', 'remoteDom', '', '', 'text', 'react');
        $unknown = new UIResourceData('ui://a', 'unknown', '', '', 'text');

        $this->assertTrue($html->isHtml());
        $this->assertFalse($html->isUrl());

        $this->assertTrue($url->isUrl());
        $this->assertFalse($url->isHtml());

        $this->assertTrue($remoteDom->isRemoteDom());
        $this->assertTrue($unknown->isUnknown());

        $this->assertTrue($html->isIframeRenderable());
        $this->assertTrue($url->isIframeRenderable());
        $this->assertFalse($remoteDom->isIframeRenderable());
    }

    public function testGetIframeSrcDoc(): void
    {
        $html = '<html>content</html>';
        $data = new UIResourceData('ui://a', 'html', 'text/html', $html, 'text');

        $this->assertSame($html, $data->getIframeSrcDoc());

        $urlData = new UIResourceData('ui://a', 'url', 'text/uri-list', 'https://example.com', 'text');
        $this->assertNull($urlData->getIframeSrcDoc());
    }

    public function testGetIframeSrc(): void
    {
        $url = 'https://example.com';
        $data = new UIResourceData('ui://a', 'url', 'text/uri-list', $url, 'text');

        $this->assertSame($url, $data->getIframeSrc());

        $htmlData = new UIResourceData('ui://a', 'html', 'text/html', '<html></html>', 'text');
        $this->assertNull($htmlData->getIframeSrc());
    }

    public function testGetContentLength(): void
    {
        $content = '<html>test content</html>';
        $data = new UIResourceData('ui://a', 'html', 'text/html', $content, 'text');

        $this->assertSame(strlen($content), $data->getContentLength());
    }

    public function testWasBase64Encoded(): void
    {
        $textEncoded = new UIResourceData('ui://a', 'html', 'text/html', '', 'text');
        $blobEncoded = new UIResourceData('ui://a', 'html', 'text/html', '', 'blob');

        $this->assertFalse($textEncoded->wasBase64Encoded());
        $this->assertTrue($blobEncoded->wasBase64Encoded());
    }

    public function testToArray(): void
    {
        $data = new UIResourceData(
            uri: 'ui://test/widget',
            type: 'html',
            mimeType: 'text/html',
            content: '<html></html>',
            encoding: 'text',
            flavor: null
        );

        $array = $data->toArray();

        $this->assertSame('ui://test/widget', $array['uri']);
        $this->assertSame('test/widget', $array['id']);
        $this->assertSame('html', $array['type']);
        $this->assertSame('text/html', $array['mimeType']);
        $this->assertSame('<html></html>', $array['content']);
        $this->assertSame('text', $array['encoding']);
        $this->assertArrayNotHasKey('flavor', $array);
    }

    public function testToArrayWithFlavor(): void
    {
        $data = new UIResourceData(
            uri: 'ui://test/widget',
            type: 'remoteDom',
            mimeType: 'application/vnd.mcp-ui.remote-dom',
            content: 'script',
            encoding: 'text',
            flavor: 'react'
        );

        $array = $data->toArray();

        $this->assertSame('react', $array['flavor']);
    }

    public function testToJson(): void
    {
        $data = $this->createSampleData();
        $json = $data->toJson();

        $decoded = json_decode($json, true);

        $this->assertSame('ui://test/widget', $decoded['uri']);
        $this->assertSame('html', $decoded['type']);
    }

    public function testFromArray(): void
    {
        $array = [
            'uri' => 'ui://test/widget',
            'type' => 'html',
            'mimeType' => 'text/html',
            'content' => '<html></html>',
            'encoding' => 'text',
            'flavor' => null,
        ];

        $data = UIResourceData::fromArray($array);

        $this->assertSame('ui://test/widget', $data->uri);
        $this->assertSame('html', $data->type);
        $this->assertTrue($data->isHtml());
    }

    public function testFromArrayWithFlavor(): void
    {
        $array = [
            'uri' => 'ui://test/widget',
            'type' => 'remoteDom',
            'mimeType' => 'application/vnd.mcp-ui.remote-dom',
            'content' => 'script',
            'encoding' => 'text',
            'flavor' => 'react',
        ];

        $data = UIResourceData::fromArray($array);

        $this->assertSame('react', $data->flavor);
    }
}

