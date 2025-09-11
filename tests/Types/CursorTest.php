<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\Cursor;
use PHPUnit\Framework\TestCase;

class CursorTest extends TestCase
{
    public function testCreateCursor(): void
    {
        $cursor = new Cursor('page-2');

        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertSame('page-2', $cursor->getValue());
    }

    public function testCreateFromStaticMethod(): void
    {
        $cursor = Cursor::from('next-cursor');

        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertSame('next-cursor', $cursor->getValue());
    }

    public function testEmptyCursorThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cursor value cannot be empty');

        new Cursor('');
    }

    public function testToString(): void
    {
        $cursor = Cursor::from('cursor-123');

        $this->assertSame('cursor-123', (string) $cursor);
        $this->assertSame('cursor-123', $cursor->__toString());
    }

    public function testJsonSerialize(): void
    {
        $cursor = Cursor::from('json-cursor');

        $this->assertSame('"json-cursor"', json_encode($cursor));
        $this->assertSame('json-cursor', $cursor->jsonSerialize());
    }

    public function testEquals(): void
    {
        $cursor1 = Cursor::from('same-cursor');
        $cursor2 = Cursor::from('same-cursor');
        $cursor3 = Cursor::from('different-cursor');

        $this->assertTrue($cursor1->equals($cursor2));
        $this->assertFalse($cursor1->equals($cursor3));
    }

    public function testWithSpecialCharacters(): void
    {
        $specialCursor = Cursor::from('cursor/with/slashes-and_underscores.123');

        $this->assertSame('cursor/with/slashes-and_underscores.123', $specialCursor->getValue());
    }

    public function testWithBase64EncodedValue(): void
    {
        $base64Cursor = Cursor::from(base64_encode('some data'));

        $this->assertSame(base64_encode('some data'), $base64Cursor->getValue());
    }
}
