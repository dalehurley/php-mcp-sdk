<?php

declare(strict_types=1);

namespace Tests\Types\Results;

use MCP\Types\Resources\BlobResourceContents;
use MCP\Types\Resources\TextResourceContents;
use MCP\Types\Results\ReadResourceResult;
use PHPUnit\Framework\TestCase;

class ReadResourceResultTest extends TestCase
{
    public function testConstructorWithTextContent(): void
    {
        $contents = [new TextResourceContents('file:///test', 'Hello')];
        $result = new ReadResourceResult($contents);

        $this->assertCount(1, $result->getContents());
        $this->assertInstanceOf(TextResourceContents::class, $result->getContents()[0]);
    }

    public function testConstructorWithMeta(): void
    {
        $contents = [new TextResourceContents('file:///test', 'Hello')];
        $result = new ReadResourceResult($contents, ['version' => '1.0']);

        $this->assertEquals(['version' => '1.0'], $result->getMeta());
    }

    public function testFromArrayWithTextContent(): void
    {
        $result = ReadResourceResult::fromArray([
            'contents' => [
                [
                    'uri' => 'file:///path/to/file.txt',
                    'text' => 'File contents here',
                    'mimeType' => 'text/plain',
                ],
            ],
        ]);

        $this->assertCount(1, $result->getContents());
        $contents = $result->getContents()[0];
        $this->assertInstanceOf(TextResourceContents::class, $contents);
        $this->assertEquals('file:///path/to/file.txt', $contents->getUri());
        $this->assertEquals('File contents here', $contents->getText());
        $this->assertEquals('text/plain', $contents->getMimeType());
    }

    public function testFromArrayWithBlobContent(): void
    {
        $result = ReadResourceResult::fromArray([
            'contents' => [
                [
                    'uri' => 'file:///path/to/image.png',
                    'blob' => base64_encode('binary data'),
                    'mimeType' => 'image/png',
                ],
            ],
        ]);

        $this->assertCount(1, $result->getContents());
        $contents = $result->getContents()[0];
        $this->assertInstanceOf(BlobResourceContents::class, $contents);
        $this->assertEquals('file:///path/to/image.png', $contents->getUri());
    }

    public function testFromArrayWithMultipleContents(): void
    {
        $result = ReadResourceResult::fromArray([
            'contents' => [
                ['uri' => 'file:///file1.txt', 'text' => 'Content 1'],
                ['uri' => 'file:///file2.txt', 'text' => 'Content 2'],
            ],
        ]);

        $this->assertCount(2, $result->getContents());
    }

    public function testFromArrayWithMixedContents(): void
    {
        $result = ReadResourceResult::fromArray([
            'contents' => [
                ['uri' => 'file:///text.txt', 'text' => 'Text content'],
                ['uri' => 'file:///binary.bin', 'blob' => base64_encode('binary')],
            ],
        ]);

        $this->assertCount(2, $result->getContents());
        $this->assertInstanceOf(TextResourceContents::class, $result->getContents()[0]);
        $this->assertInstanceOf(BlobResourceContents::class, $result->getContents()[1]);
    }

    public function testFromArrayWithMeta(): void
    {
        $result = ReadResourceResult::fromArray([
            'contents' => [
                ['uri' => 'file:///test', 'text' => 'test'],
            ],
            '_meta' => ['timestamp' => 1234567890],
        ]);

        $this->assertEquals(['timestamp' => 1234567890], $result->getMeta());
    }

    public function testFromArrayThrowsWithMissingContents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ReadResourceResult must have a contents array');

        ReadResourceResult::fromArray([]);
    }

    public function testFromArrayThrowsWithNonArrayContents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ReadResourceResult must have a contents array');

        ReadResourceResult::fromArray(['contents' => 'not an array']);
    }

    public function testFromArrayThrowsWithInvalidContentType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resource contents type');

        ReadResourceResult::fromArray([
            'contents' => [
                ['uri' => 'file:///test'], // Neither text nor blob
            ],
        ]);
    }

    public function testJsonSerializeWithTextContent(): void
    {
        $result = new ReadResourceResult([
            new TextResourceContents('file:///test.txt', 'Hello world', 'text/plain'),
        ]);
        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('contents', $json);
        $this->assertCount(1, $json['contents']);
        $this->assertEquals('file:///test.txt', $json['contents'][0]['uri']);
        $this->assertEquals('Hello world', $json['contents'][0]['text']);
        $this->assertEquals('text/plain', $json['contents'][0]['mimeType']);
    }

    public function testJsonSerializeWithBlobContent(): void
    {
        $result = new ReadResourceResult([
            new BlobResourceContents('file:///image.png', base64_encode('PNG data'), 'image/png'),
        ]);
        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('contents', $json);
        $this->assertCount(1, $json['contents']);
        $this->assertEquals('file:///image.png', $json['contents'][0]['uri']);
        $this->assertArrayHasKey('blob', $json['contents'][0]);
    }

    public function testJsonSerializeWithMeta(): void
    {
        $result = new ReadResourceResult(
            [new TextResourceContents('file:///test', 'test')],
            ['key' => 'value']
        );
        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('_meta', $json);
        $this->assertEquals(['key' => 'value'], $json['_meta']);
    }

    public function testRoundTrip(): void
    {
        $original = new ReadResourceResult([
            new TextResourceContents('file:///doc.txt', 'Document content', 'text/plain'),
        ], ['version' => 1]);

        $json = $original->jsonSerialize();
        $restored = ReadResourceResult::fromArray($json);

        $this->assertCount(1, $restored->getContents());
        $restoredContent = $restored->getContents()[0];
        $this->assertEquals('file:///doc.txt', $restoredContent->getUri());
        $this->assertEquals('Document content', $restoredContent->getText());
        $this->assertEquals(['version' => 1], $restored->getMeta());
    }

    public function testGetContents(): void
    {
        $contents = [
            new TextResourceContents('file:///a', 'A'),
            new TextResourceContents('file:///b', 'B'),
        ];
        $result = new ReadResourceResult($contents);

        $retrieved = $result->getContents();
        $this->assertCount(2, $retrieved);
        $this->assertEquals('file:///a', $retrieved[0]->getUri());
        $this->assertEquals('file:///b', $retrieved[1]->getUri());
    }
}

