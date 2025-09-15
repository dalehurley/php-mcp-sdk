<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * The severity of a log message.
 */
enum LoggingLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';

    /**
     * Get the numeric severity level (higher is more severe).
     */
    public function getSeverity(): int
    {
        return match ($this) {
            self::Debug => 0,
            self::Info => 1,
            self::Notice => 2,
            self::Warning => 3,
            self::Error => 4,
            self::Critical => 5,
            self::Alert => 6,
            self::Emergency => 7,
        };
    }

    /**
     * Check if this level is at least as severe as another level.
     */
    public function isAtLeast(self $other): bool
    {
        return $this->getSeverity() >= $other->getSeverity();
    }

    /**
     * Get all levels at or above this severity level.
     *
     * @return self[]
     */
    public function getHigherLevels(): array
    {
        $currentSeverity = $this->getSeverity();

        return array_filter(
            self::cases(),
            fn (self $level) => $level->getSeverity() >= $currentSeverity
        );
    }
}
