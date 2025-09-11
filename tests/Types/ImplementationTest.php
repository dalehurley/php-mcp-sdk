<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\Implementation;
use PHPUnit\Framework\TestCase;

class ImplementationTest extends TestCase
{
    public function testImplementationCreation(): void
    {
        $impl = new Implementation('test-server', '1.0.0', 'Test Server');

        $this->assertEquals('test-server', $impl->getName());
        $this->assertEquals('1.0.0', $impl->getVersion());
        $this->assertEquals('Test Server', $impl->getTitle());
    }

    public function testImplementationToArray(): void
    {
        $impl = new Implementation('test-server', '1.0.0', 'Test Server');
        $array = $impl->toArray();

        $this->assertEquals([
            'name' => 'test-server',
            'version' => '1.0.0',
            'title' => 'Test Server',
        ], $array);
    }

    public function testImplementationFromArray(): void
    {
        $data = [
            'name' => 'test-server',
            'version' => '1.0.0',
            'title' => 'Test Server',
        ];

        $impl = Implementation::fromArray($data);

        $this->assertEquals('test-server', $impl->getName());
        $this->assertEquals('1.0.0', $impl->getVersion());
        $this->assertEquals('Test Server', $impl->getTitle());
    }

    public function testImplementationWithoutTitle(): void
    {
        $impl = new Implementation('test-server', '1.0.0');

        $this->assertNull($impl->getTitle());

        $array = $impl->toArray();
        $this->assertArrayNotHasKey('title', $array);
    }
}
