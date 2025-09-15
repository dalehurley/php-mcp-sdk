<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use MCP\Shared\HttpTransportAdapter;
use PHPUnit\Framework\TestCase;

class HttpTransportAdapterTest extends TestCase
{
    public function testHttpTransportAdapterInterface(): void
    {
        // Test that the interface exists and has the expected methods
        $this->assertTrue(interface_exists(HttpTransportAdapter::class));

        $reflection = new \ReflectionClass(HttpTransportAdapter::class);
        $this->assertTrue($reflection->hasMethod('handlePsr7Request'));
        $this->assertTrue($reflection->hasMethod('handlePsr7RequestAsync'));
    }

    public function testStreamableHttpTransportAdapterExists(): void
    {
        // Test that the implementation class exists
        $this->assertTrue(class_exists(\MCP\Shared\StreamableHttpTransportAdapter::class));
    }
}
