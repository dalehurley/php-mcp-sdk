<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\ProgressToken;
use PHPUnit\Framework\TestCase;

class ProgressTokenTest extends TestCase
{
    public function testCreateFromString(): void
    {
        $token = ProgressToken::fromString('progress-123');

        $this->assertInstanceOf(ProgressToken::class, $token);
        $this->assertSame('progress-123', $token->getValue());
        $this->assertTrue($token->isString());
        $this->assertFalse($token->isInt());
    }

    public function testCreateFromInt(): void
    {
        $token = ProgressToken::fromInt(456);

        $this->assertInstanceOf(ProgressToken::class, $token);
        $this->assertSame(456, $token->getValue());
        $this->assertFalse($token->isString());
        $this->assertTrue($token->isInt());
    }

    public function testCreateFromMixed(): void
    {
        $stringToken = ProgressToken::from('test-token');
        $this->assertSame('test-token', $stringToken->getValue());

        $intToken = ProgressToken::from(789);
        $this->assertSame(789, $intToken->getValue());
    }

    public function testCreateFromInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ProgressToken must be a string or integer, double given');

        ProgressToken::from(12.34);
    }

    public function testToString(): void
    {
        $stringToken = ProgressToken::fromString('token');
        $this->assertSame('token', (string) $stringToken);

        $intToken = ProgressToken::fromInt(123);
        $this->assertSame('123', (string) $intToken);
    }

    public function testJsonSerialize(): void
    {
        $stringToken = ProgressToken::fromString('json-token');
        $this->assertSame('"json-token"', json_encode($stringToken));

        $intToken = ProgressToken::fromInt(999);
        $this->assertSame('999', json_encode($intToken));
    }

    public function testEquals(): void
    {
        $token1 = ProgressToken::fromString('same');
        $token2 = ProgressToken::fromString('same');
        $token3 = ProgressToken::fromString('different');
        $token4 = ProgressToken::fromInt(123);
        $token5 = ProgressToken::fromInt(123);

        $this->assertTrue($token1->equals($token2));
        $this->assertFalse($token1->equals($token3));
        $this->assertTrue($token4->equals($token5));
        $this->assertFalse($token1->equals($token4));
    }

    public function testConstructorDirectly(): void
    {
        $stringToken = new ProgressToken('direct-string');
        $this->assertSame('direct-string', $stringToken->getValue());

        $intToken = new ProgressToken(42);
        $this->assertSame(42, $intToken->getValue());
    }
}
