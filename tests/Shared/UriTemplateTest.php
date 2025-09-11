<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use MCP\Shared\UriTemplate;
use PHPUnit\Framework\TestCase;

class UriTemplateTest extends TestCase
{
    public function testIsTemplate(): void
    {
        $this->assertTrue(UriTemplate::isTemplate('/users/{id}'));
        $this->assertTrue(UriTemplate::isTemplate('/search{?q,page}'));
        $this->assertTrue(UriTemplate::isTemplate('/{+path}'));
        $this->assertFalse(UriTemplate::isTemplate('/users/123'));
        $this->assertFalse(UriTemplate::isTemplate(''));
        $this->assertFalse(UriTemplate::isTemplate('{ }'));
    }

    public function testSimpleStringExpansion(): void
    {
        $template = new UriTemplate('/users/{id}');
        $result = $template->expand(['id' => '123']);
        $this->assertEquals('/users/123', $result);
    }

    public function testMultipleVariables(): void
    {
        $template = new UriTemplate('/users/{userId}/posts/{postId}');
        $result = $template->expand(['userId' => '123', 'postId' => '456']);
        $this->assertEquals('/users/123/posts/456', $result);
    }

    public function testMissingVariables(): void
    {
        $template = new UriTemplate('/users/{id}');
        $result = $template->expand([]);
        $this->assertEquals('/users/', $result);
    }

    public function testQueryStringExpansion(): void
    {
        $template = new UriTemplate('/search{?q,page}');
        $result = $template->expand(['q' => 'hello world', 'page' => '2']);
        $this->assertEquals('/search?q=hello%20world&page=2', $result);
    }

    public function testQueryStringWithMissingValues(): void
    {
        $template = new UriTemplate('/search{?q,page}');
        $result = $template->expand(['q' => 'test']);
        $this->assertEquals('/search?q=test', $result);
    }

    public function testFragmentExpansion(): void
    {
        $template = new UriTemplate('/page{#section}');
        $result = $template->expand(['section' => 'intro']);
        $this->assertEquals('/page#intro', $result);
    }

    public function testPathSegmentExpansion(): void
    {
        $template = new UriTemplate('/files{/path}');
        $result = $template->expand(['path' => 'docs/readme']);
        $this->assertEquals('/files/docs%2Freadme', $result);
    }

    public function testDotPrefixedExpansion(): void
    {
        $template = new UriTemplate('/file{.ext}');
        $result = $template->expand(['ext' => 'json']);
        $this->assertEquals('/file.json', $result);
    }

    public function testReservedExpansion(): void
    {
        $template = new UriTemplate('/path{+reserved}');
        $result = $template->expand(['reserved' => '/foo/bar']);
        $this->assertEquals('/path/foo/bar', $result);
    }

    public function testArrayValues(): void
    {
        $template = new UriTemplate('/search{?tags}');
        $result = $template->expand(['tags' => ['php', 'testing']]);
        $this->assertEquals('/search?tags=php,testing', $result);
    }

    public function testContinuationOperator(): void
    {
        $template = new UriTemplate('/search?fixed=1{&q,page}');
        $result = $template->expand(['q' => 'test', 'page' => '2']);
        $this->assertEquals('/search?fixed=1&q=test&page=2', $result);
    }

    public function testGetVariableNames(): void
    {
        $template = new UriTemplate('/users/{userId}/posts/{postId}{?filter,sort}');
        $names = $template->getVariableNames();
        $this->assertEquals(['userId', 'postId', 'filter', 'sort'], $names);
    }

    public function testMatch(): void
    {
        $template = new UriTemplate('/users/{id}');
        $result = $template->match('/users/123');
        $this->assertEquals(['id' => '123'], $result);
    }

    public function testMatchMultipleVariables(): void
    {
        $template = new UriTemplate('/users/{userId}/posts/{postId}');
        $result = $template->match('/users/123/posts/456');
        $this->assertEquals(['userId' => '123', 'postId' => '456'], $result);
    }

    public function testMatchQueryString(): void
    {
        $template = new UriTemplate('/search{?q,page}');
        $result = $template->match('/search?q=hello&page=2');
        $this->assertEquals(['q' => 'hello', 'page' => '2'], $result);
    }

    public function testMatchNoMatch(): void
    {
        $template = new UriTemplate('/users/{id}');
        $result = $template->match('/posts/123');
        $this->assertNull($result);
    }

    public function testMatchWithSpecialCharacters(): void
    {
        $template = new UriTemplate('/files/{name}.{ext}');
        $result = $template->match('/files/document.pdf');
        $this->assertEquals(['name' => 'document', 'ext' => 'pdf'], $result);
    }

    public function testMaxTemplateLengthValidation(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Template exceeds maximum length');
        new UriTemplate(str_repeat('a', 1000001));
    }

    public function testMaxVariableLengthValidation(): void
    {
        $template = new UriTemplate('/test/{var}');
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Variable value exceeds maximum length');
        $template->expand(['var' => str_repeat('a', 1000001)]);
    }

    public function testUnclosedTemplateExpression(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Unclosed template expression');
        new UriTemplate('/users/{id');
    }

    public function testMaxTemplateExpressions(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Template contains too many expressions');
        $template = '';
        for ($i = 0; $i < 10001; $i++) {
            $template .= "{var$i}";
        }
        new UriTemplate($template);
    }

    public function testToString(): void
    {
        $template = new UriTemplate('/users/{id}');
        $this->assertEquals('/users/{id}', (string) $template);
    }
}
