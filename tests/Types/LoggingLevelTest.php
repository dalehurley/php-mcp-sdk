<?php

declare(strict_types=1);

namespace MCP\Tests\Types;

use MCP\Types\LoggingLevel;
use PHPUnit\Framework\TestCase;

class LoggingLevelTest extends TestCase
{
    public function testEnumCases(): void
    {
        $this->assertSame('debug', LoggingLevel::Debug->value);
        $this->assertSame('info', LoggingLevel::Info->value);
        $this->assertSame('notice', LoggingLevel::Notice->value);
        $this->assertSame('warning', LoggingLevel::Warning->value);
        $this->assertSame('error', LoggingLevel::Error->value);
        $this->assertSame('critical', LoggingLevel::Critical->value);
        $this->assertSame('alert', LoggingLevel::Alert->value);
        $this->assertSame('emergency', LoggingLevel::Emergency->value);
    }

    public function testGetSeverity(): void
    {
        $this->assertSame(0, LoggingLevel::Debug->getSeverity());
        $this->assertSame(1, LoggingLevel::Info->getSeverity());
        $this->assertSame(2, LoggingLevel::Notice->getSeverity());
        $this->assertSame(3, LoggingLevel::Warning->getSeverity());
        $this->assertSame(4, LoggingLevel::Error->getSeverity());
        $this->assertSame(5, LoggingLevel::Critical->getSeverity());
        $this->assertSame(6, LoggingLevel::Alert->getSeverity());
        $this->assertSame(7, LoggingLevel::Emergency->getSeverity());
    }

    public function testIsAtLeast(): void
    {
        $warning = LoggingLevel::Warning;

        // Warning is at least debug, info, notice, and warning
        $this->assertTrue($warning->isAtLeast(LoggingLevel::Debug));
        $this->assertTrue($warning->isAtLeast(LoggingLevel::Info));
        $this->assertTrue($warning->isAtLeast(LoggingLevel::Notice));
        $this->assertTrue($warning->isAtLeast(LoggingLevel::Warning));

        // Warning is NOT at least error, critical, alert, or emergency
        $this->assertFalse($warning->isAtLeast(LoggingLevel::Error));
        $this->assertFalse($warning->isAtLeast(LoggingLevel::Critical));
        $this->assertFalse($warning->isAtLeast(LoggingLevel::Alert));
        $this->assertFalse($warning->isAtLeast(LoggingLevel::Emergency));
    }

    public function testGetHigherLevels(): void
    {
        $levels = LoggingLevel::Warning->getHigherLevels();

        $this->assertCount(5, $levels);
        $this->assertContains(LoggingLevel::Warning, $levels);
        $this->assertContains(LoggingLevel::Error, $levels);
        $this->assertContains(LoggingLevel::Critical, $levels);
        $this->assertContains(LoggingLevel::Alert, $levels);
        $this->assertContains(LoggingLevel::Emergency, $levels);

        $this->assertNotContains(LoggingLevel::Debug, $levels);
        $this->assertNotContains(LoggingLevel::Info, $levels);
        $this->assertNotContains(LoggingLevel::Notice, $levels);
    }

    public function testGetHigherLevelsForDebug(): void
    {
        $levels = LoggingLevel::Debug->getHigherLevels();

        // Debug includes all levels
        $this->assertCount(8, $levels);
        $this->assertSame(LoggingLevel::cases(), $levels);
    }

    public function testGetHigherLevelsForEmergency(): void
    {
        $levels = LoggingLevel::Emergency->getHigherLevels();

        // Emergency only includes itself
        $this->assertCount(1, $levels);
        $this->assertContains(LoggingLevel::Emergency, $levels);
    }

    public function testFrom(): void
    {
        $this->assertSame(LoggingLevel::Debug, LoggingLevel::from('debug'));
        $this->assertSame(LoggingLevel::Info, LoggingLevel::from('info'));
        $this->assertSame(LoggingLevel::Notice, LoggingLevel::from('notice'));
        $this->assertSame(LoggingLevel::Warning, LoggingLevel::from('warning'));
        $this->assertSame(LoggingLevel::Error, LoggingLevel::from('error'));
        $this->assertSame(LoggingLevel::Critical, LoggingLevel::from('critical'));
        $this->assertSame(LoggingLevel::Alert, LoggingLevel::from('alert'));
        $this->assertSame(LoggingLevel::Emergency, LoggingLevel::from('emergency'));
    }

    public function testFromInvalid(): void
    {
        $this->expectException(\ValueError::class);
        LoggingLevel::from('invalid-level');
    }

    public function testTryFrom(): void
    {
        $this->assertSame(LoggingLevel::Debug, LoggingLevel::tryFrom('debug'));
        $this->assertSame(LoggingLevel::Error, LoggingLevel::tryFrom('error'));
        $this->assertNull(LoggingLevel::tryFrom('invalid'));
        $this->assertNull(LoggingLevel::tryFrom(''));
    }

    public function testJsonSerialize(): void
    {
        $level = LoggingLevel::Warning;
        $json = json_encode(['level' => $level]);

        $this->assertSame('{"level":"warning"}', $json);
    }
}
