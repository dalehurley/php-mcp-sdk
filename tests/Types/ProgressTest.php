<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\Progress;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Progress.
 */
class ProgressTest extends TestCase
{
    /**
     * Test basic construction.
     */
    public function testBasicConstruction(): void
    {
        $progress = new Progress(50.0);
        
        $this->assertEquals(50.0, $progress->getProgress());
        $this->assertNull($progress->getTotal());
        $this->assertNull($progress->getMessage());
        $this->assertNull($progress->getPercentage());
        $this->assertFalse($progress->isComplete());
    }

    /**
     * Test construction with all parameters.
     */
    public function testFullConstruction(): void
    {
        $progress = new Progress(
            progress: 75.0,
            total: 100.0,
            message: 'Processing...'
        );
        
        $this->assertEquals(75.0, $progress->getProgress());
        $this->assertEquals(100.0, $progress->getTotal());
        $this->assertEquals('Processing...', $progress->getMessage());
        $this->assertEquals(75.0, $progress->getPercentage());
        $this->assertFalse($progress->isComplete());
    }

    /**
     * Test negative progress throws exception.
     */
    public function testNegativeProgressThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Progress cannot be negative');
        
        new Progress(-1.0);
    }

    /**
     * Test negative total throws exception.
     */
    public function testNegativeTotalThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total cannot be negative');
        
        new Progress(50.0, -1.0);
    }

    /**
     * Test percentage calculation.
     */
    public function testPercentageCalculation(): void
    {
        // Normal case
        $progress = new Progress(25.0, 50.0);
        $this->assertEquals(50.0, $progress->getPercentage());
        
        // Over 100%
        $progress = new Progress(150.0, 100.0);
        $this->assertEquals(100.0, $progress->getPercentage());
        
        // Zero total
        $progress = new Progress(50.0, 0.0);
        $this->assertNull($progress->getPercentage());
    }

    /**
     * Test isComplete method.
     */
    public function testIsComplete(): void
    {
        // Not complete
        $progress = new Progress(50.0, 100.0);
        $this->assertFalse($progress->isComplete());
        
        // Complete
        $progress = new Progress(100.0, 100.0);
        $this->assertTrue($progress->isComplete());
        
        // Over complete
        $progress = new Progress(150.0, 100.0);
        $this->assertTrue($progress->isComplete());
        
        // No total
        $progress = new Progress(100.0);
        $this->assertFalse($progress->isComplete());
    }

    /**
     * Test fromArray method.
     */
    public function testFromArray(): void
    {
        $data = [
            'progress' => 45.5,
            'total' => 100.0,
            'message' => 'Working...',
            'customField' => 'value'
        ];
        
        $progress = Progress::fromArray($data);
        
        $this->assertEquals(45.5, $progress->getProgress());
        $this->assertEquals(100.0, $progress->getTotal());
        $this->assertEquals('Working...', $progress->getMessage());
    }

    /**
     * Test fromArray with invalid data.
     */
    public function testFromArrayInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Progress must have a numeric progress property');
        
        Progress::fromArray(['no-progress' => true]);
    }

    /**
     * Test JSON serialization.
     */
    public function testJsonSerialization(): void
    {
        $progress = new Progress(
            progress: 30.0,
            total: 60.0,
            message: 'Half way'
        );
        
        $json = $progress->jsonSerialize();
        
        $this->assertEquals(30.0, $json['progress']);
        $this->assertEquals(60.0, $json['total']);
        $this->assertEquals('Half way', $json['message']);
    }

    /**
     * Test JSON serialization with additional properties.
     */
    public function testJsonSerializationWithAdditionalProperties(): void
    {
        $data = [
            'progress' => 10.0,
            'total' => 20.0,
            'stage' => 'initialization',
            'details' => ['files' => 5]
        ];
        
        $progress = Progress::fromArray($data);
        $json = $progress->jsonSerialize();
        
        $this->assertEquals(10.0, $json['progress']);
        $this->assertEquals(20.0, $json['total']);
        $this->assertEquals('initialization', $json['stage']);
        $this->assertEquals(['files' => 5], $json['details']);
    }
}
