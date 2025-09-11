<?php

declare(strict_types=1);

namespace MCP\Tests\Types\Supporting;

use MCP\Types\Supporting\RequestInfo;
use PHPUnit\Framework\TestCase;

/**
 * Test class for RequestInfo.
 */
class RequestInfoTest extends TestCase
{
    /**
     * Test construction and getters.
     */
    public function testConstructionAndGetters(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token123',
            'X-Custom' => ['value1', 'value2']
        ];
        
        $requestInfo = new RequestInfo($headers);
        
        $this->assertEquals($headers, $requestInfo->getHeaders());
        $this->assertEquals('application/json', $requestInfo->getHeader('Content-Type'));
        $this->assertEquals('Bearer token123', $requestInfo->getHeader('Authorization'));
        $this->assertEquals(['value1', 'value2'], $requestInfo->getHeader('X-Custom'));
    }

    /**
     * Test getHeader with non-existent header.
     */
    public function testGetHeaderNonExistent(): void
    {
        $requestInfo = new RequestInfo(['Content-Type' => 'text/plain']);
        
        $this->assertNull($requestInfo->getHeader('Authorization'));
    }

    /**
     * Test hasHeader method.
     */
    public function testHasHeader(): void
    {
        $requestInfo = new RequestInfo([
            'Content-Type' => 'application/json',
            'X-Empty' => null
        ]);
        
        $this->assertTrue($requestInfo->hasHeader('Content-Type'));
        $this->assertTrue($requestInfo->hasHeader('X-Empty'));
        $this->assertFalse($requestInfo->hasHeader('Authorization'));
    }

    /**
     * Test fromArray method.
     */
    public function testFromArray(): void
    {
        $data = [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'MCP Client/1.0'
            ]
        ];
        
        $requestInfo = RequestInfo::fromArray($data);
        
        $this->assertEquals('application/json', $requestInfo->getHeader('Accept'));
        $this->assertEquals('MCP Client/1.0', $requestInfo->getHeader('User-Agent'));
    }

    /**
     * Test fromArray without headers.
     */
    public function testFromArrayWithoutHeaders(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RequestInfo must have a headers property');
        
        RequestInfo::fromArray(['other' => 'data']);
    }

    /**
     * Test fromArray with non-array headers.
     */
    public function testFromArrayWithNonArrayHeaders(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RequestInfo must have a headers property');
        
        RequestInfo::fromArray(['headers' => 'not-array']);
    }
}
