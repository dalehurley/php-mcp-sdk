<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\RequestId;
use PHPUnit\Framework\TestCase;

class RequestIdTest extends TestCase
{
    public function testCreateFromString(): void
    {
        $id = RequestId::fromString('request-abc');

        $this->assertInstanceOf(RequestId::class, $id);
        $this->assertSame('request-abc', $id->getValue());
        $this->assertTrue($id->isString());
        $this->assertFalse($id->isInt());
    }

    public function testCreateFromInt(): void
    {
        $id = RequestId::fromInt(12345);

        $this->assertInstanceOf(RequestId::class, $id);
        $this->assertSame(12345, $id->getValue());
        $this->assertFalse($id->isString());
        $this->assertTrue($id->isInt());
    }

    public function testCreateFromMixed(): void
    {
        $stringId = RequestId::from('mixed-string');
        $this->assertSame('mixed-string', $stringId->getValue());

        $intId = RequestId::from(67890);
        $this->assertSame(67890, $intId->getValue());
    }

    public function testCreateFromInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RequestId must be a string or integer, array given');

        RequestId::from(['invalid']);
    }

    public function testCreateFromNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RequestId must be a string or integer, NULL given');

        RequestId::from(null);
    }

    public function testToString(): void
    {
        $stringId = RequestId::fromString('id-string');
        $this->assertSame('id-string', (string) $stringId);

        $intId = RequestId::fromInt(999);
        $this->assertSame('999', (string) $intId);
    }

    public function testJsonSerialize(): void
    {
        $stringId = RequestId::fromString('json-id');
        $this->assertSame('"json-id"', json_encode($stringId));

        $intId = RequestId::fromInt(42);
        $this->assertSame('42', json_encode($intId));
    }

    public function testEquals(): void
    {
        $id1 = RequestId::fromString('same-id');
        $id2 = RequestId::fromString('same-id');
        $id3 = RequestId::fromString('different-id');
        $id4 = RequestId::fromInt(100);
        $id5 = RequestId::fromInt(100);
        $id6 = RequestId::fromInt(200);

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
        $this->assertTrue($id4->equals($id5));
        $this->assertFalse($id4->equals($id6));
        $this->assertFalse($id1->equals($id4)); // string vs int
    }

    public function testConstructorDirectly(): void
    {
        $stringId = new RequestId('direct-string-id');
        $this->assertSame('direct-string-id', $stringId->getValue());

        $intId = new RequestId(777);
        $this->assertSame(777, $intId->getValue());
    }

    public function testWithNegativeInteger(): void
    {
        $id = RequestId::fromInt(-123);

        $this->assertSame(-123, $id->getValue());
        $this->assertTrue($id->isInt());
    }

    public function testWithZero(): void
    {
        $id = RequestId::fromInt(0);

        $this->assertSame(0, $id->getValue());
        $this->assertTrue($id->isInt());
    }
}
