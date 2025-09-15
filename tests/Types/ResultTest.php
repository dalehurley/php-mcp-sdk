<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testCreateResult(): void
    {
        $result = new Result(['tracking' => 'abc123'], ['data' => 'value']);

        $this->assertSame(['tracking' => 'abc123'], $result->getMeta());
        $this->assertSame(['data' => 'value'], $result->getAdditionalProperties());
        $this->assertTrue($result->hasMeta());
    }

    public function testCreateResultWithoutMeta(): void
    {
        $result = new Result(null, ['data' => 'value']);

        $this->assertNull($result->getMeta());
        $this->assertFalse($result->hasMeta());
        $this->assertSame(['data' => 'value'], $result->getAdditionalProperties());
    }

    public function testFromArray(): void
    {
        $data = [
            '_meta' => ['session' => '12345'],
            'status' => 'success',
            'count' => 10,
        ];

        $result = Result::fromArray($data);

        $this->assertSame(['session' => '12345'], $result->getMeta());
        $this->assertSame(['status' => 'success', 'count' => 10], $result->getAdditionalProperties());
    }

    public function testFromArrayWithoutMeta(): void
    {
        $data = [
            'status' => 'success',
            'items' => ['a', 'b', 'c'],
        ];

        $result = Result::fromArray($data);

        $this->assertNull($result->getMeta());
        $this->assertSame($data, $result->getAdditionalProperties());
    }

    public function testGetAdditionalProperty(): void
    {
        $result = new Result(null, ['key1' => 'value1', 'key2' => 'value2']);

        $this->assertSame('value1', $result->getAdditionalProperty('key1'));
        $this->assertSame('value2', $result->getAdditionalProperty('key2'));
        $this->assertNull($result->getAdditionalProperty('nonexistent'));
    }

    public function testWithMeta(): void
    {
        $result = new Result(null, ['data' => 'value']);
        $newResult = $result->withMeta(['tracking' => 'xyz']);

        // Original unchanged
        $this->assertNull($result->getMeta());

        // New instance has meta
        $this->assertSame(['tracking' => 'xyz'], $newResult->getMeta());
        $this->assertSame(['data' => 'value'], $newResult->getAdditionalProperties());
    }

    public function testJsonSerialize(): void
    {
        $result = new Result(['meta' => 'data'], ['key' => 'value', 'count' => 5]);

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertSame(['meta' => 'data'], $decoded['_meta']);
        $this->assertSame('value', $decoded['key']);
        $this->assertSame(5, $decoded['count']);
    }

    public function testJsonSerializeWithoutMeta(): void
    {
        $result = new Result(null, ['key' => 'value']);

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertArrayNotHasKey('_meta', $decoded);
        $this->assertSame('value', $decoded['key']);
    }

    public function testJsonSerializeEmpty(): void
    {
        $result = new Result();

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertSame([], $decoded);
    }
}
