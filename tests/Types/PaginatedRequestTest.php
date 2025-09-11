<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\PaginatedRequest;
use MCP\Types\Cursor;
use PHPUnit\Framework\TestCase;

/**
 * Test class for testing PaginatedRequest behavior.
 */
class PaginatedRequestTest extends TestCase
{
    /**
     * Test that getCursor returns null when no cursor is set.
     */
    public function testGetCursorReturnsNullWhenNotSet(): void
    {
        $request = new TestPaginatedRequest();
        $this->assertNull($request->getCursor());
    }

    /**
     * Test that getCursor returns cursor when set in params.
     */
    public function testGetCursorReturnsCursorWhenSet(): void
    {
        $request = new TestPaginatedRequest(['cursor' => 'page2']);
        $cursor = $request->getCursor();
        
        $this->assertNotNull($cursor);
        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertEquals('page2', $cursor->getValue());
    }

    /**
     * Test withCursor creates new instance with cursor.
     */
    public function testWithCursorCreatesNewInstanceWithCursor(): void
    {
        $request = new TestPaginatedRequest();
        $cursor = new Cursor('page3');
        $newRequest = $request->withCursor($cursor);
        
        $this->assertNotSame($request, $newRequest);
        $this->assertNull($request->getCursor());
        
        $newCursor = $newRequest->getCursor();
        $this->assertNotNull($newCursor);
        $this->assertEquals('page3', $newCursor->getValue());
    }

    /**
     * Test withoutCursor removes cursor from request.
     */
    public function testWithoutCursorRemovesCursor(): void
    {
        $request = new TestPaginatedRequest(['cursor' => 'page2', 'limit' => 10]);
        $newRequest = $request->withoutCursor();
        
        $this->assertNotSame($request, $newRequest);
        $this->assertNotNull($request->getCursor());
        $this->assertNull($newRequest->getCursor());
        
        // Other params should remain
        $params = $newRequest->getParams();
        $this->assertEquals(10, $params['limit'] ?? null);
    }

    /**
     * Test withoutCursor handles empty params correctly.
     */
    public function testWithoutCursorHandlesEmptyParams(): void
    {
        $request = new TestPaginatedRequest(['cursor' => 'page2']);
        $newRequest = $request->withoutCursor();
        
        $this->assertNull($newRequest->getParams());
    }

    /**
     * Test isValid method.
     */
    public function testIsValid(): void
    {
        $this->assertTrue(PaginatedRequest::isValid(['method' => 'test']));
        $this->assertFalse(PaginatedRequest::isValid(['no-method' => 'test']));
        $this->assertFalse(PaginatedRequest::isValid('not-array'));
    }
}

/**
 * Concrete implementation for testing.
 */
class TestPaginatedRequest extends PaginatedRequest
{
    public function __construct(?array $params = null)
    {
        parent::__construct('test/method', $params);
    }
}
