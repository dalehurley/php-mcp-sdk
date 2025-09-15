<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\Cursor;
use MCP\Types\PaginatedResult;
use PHPUnit\Framework\TestCase;

/**
 * Test class for PaginatedResult.
 */
class PaginatedResultTest extends TestCase
{
    /**
     * Test construction without cursor.
     */
    public function testConstructionWithoutCursor(): void
    {
        $result = new TestPaginatedResult();

        $this->assertNull($result->getNextCursor());
        $this->assertFalse($result->hasMore());
    }

    /**
     * Test construction with cursor.
     */
    public function testConstructionWithCursor(): void
    {
        $cursor = new Cursor('next-page');
        $result = new TestPaginatedResult($cursor);

        $this->assertNotNull($result->getNextCursor());
        $this->assertEquals('next-page', $result->getNextCursor()->getValue());
        $this->assertTrue($result->hasMore());
    }

    /**
     * Test JSON serialization without cursor.
     */
    public function testJsonSerializationWithoutCursor(): void
    {
        $result = new TestPaginatedResult();
        $json = $result->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertArrayNotHasKey('nextCursor', $json);
    }

    /**
     * Test JSON serialization with cursor.
     */
    public function testJsonSerializationWithCursor(): void
    {
        $cursor = new Cursor('page3');
        $result = new TestPaginatedResult($cursor);
        $json = $result->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertArrayHasKey('nextCursor', $json);
        $this->assertEquals('page3', $json['nextCursor']);
    }

    /**
     * Test extractNextCursor method.
     */
    public function testExtractNextCursor(): void
    {
        // Test with cursor
        $data = ['nextCursor' => 'page4'];
        $cursor = TestPaginatedResult::extractNextCursorPublic($data);
        $this->assertNotNull($cursor);
        $this->assertEquals('page4', $cursor->getValue());

        // Test without cursor
        $data = ['other' => 'data'];
        $cursor = TestPaginatedResult::extractNextCursorPublic($data);
        $this->assertNull($cursor);

        // Test with non-string cursor
        $data = ['nextCursor' => 123];
        $cursor = TestPaginatedResult::extractNextCursorPublic($data);
        $this->assertNull($cursor);
    }
}

/**
 * Concrete implementation for testing.
 */
class TestPaginatedResult extends PaginatedResult
{
    public function __construct(?Cursor $nextCursor = null, ?array $_meta = null)
    {
        parent::__construct($nextCursor, $_meta);
    }

    /**
     * Public wrapper for protected method.
     */
    public static function extractNextCursorPublic(array $data): ?Cursor
    {
        return self::extractNextCursor($data);
    }
}
