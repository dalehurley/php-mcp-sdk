<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\EmptyResult;
use PHPUnit\Framework\TestCase;

class EmptyResultTest extends TestCase
{
    public function testCreateEmptyResult(): void
    {
        $result = new EmptyResult();

        $this->assertInstanceOf(EmptyResult::class, $result);
        $this->assertNull($result->getMeta());
        $this->assertSame([], $result->getAdditionalProperties());
    }

    public function testCreateEmptyResultWithMeta(): void
    {
        $result = new EmptyResult(['tracking' => 'abc']);

        $this->assertSame(['tracking' => 'abc'], $result->getMeta());
        $this->assertTrue($result->hasMeta());
        $this->assertSame([], $result->getAdditionalProperties());
    }

    public function testFromArray(): void
    {
        $result = EmptyResult::fromArray([]);

        $this->assertInstanceOf(EmptyResult::class, $result);
        $this->assertNull($result->getMeta());
    }

    public function testFromArrayWithMeta(): void
    {
        $result = EmptyResult::fromArray(['_meta' => ['session' => '123']]);

        $this->assertSame(['session' => '123'], $result->getMeta());
    }

    public function testFromArrayWithExtraProperties(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EmptyResult should not have any properties other than _meta');

        EmptyResult::fromArray(['extra' => 'property']);
    }

    public function testJsonSerialize(): void
    {
        $result = new EmptyResult();

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertSame([], $decoded);
    }

    public function testJsonSerializeWithMeta(): void
    {
        $result = new EmptyResult(['tracking' => 'xyz']);

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertSame(['_meta' => ['tracking' => 'xyz']], $decoded);
        $this->assertCount(1, $decoded);
    }

    public function testInheritance(): void
    {
        $result = new EmptyResult();

        // EmptyResult extends Result
        $this->assertInstanceOf(\MCP\Types\Result::class, $result);
    }

    public function testStrictness(): void
    {
        // EmptyResult should not allow additional properties through constructor
        // This is enforced by the parent constructor accepting empty array
        $result = new EmptyResult(['meta' => 'data']);

        $this->assertSame([], $result->getAdditionalProperties());
        $this->assertNull($result->getAdditionalProperty('anything'));
    }
}
