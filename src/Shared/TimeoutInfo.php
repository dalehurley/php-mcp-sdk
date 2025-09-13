<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * Information about a request's timeout state
 */
class TimeoutInfo
{
    public function __construct(
        public string $timeoutId,
        public int $startTime,
        public int $timeout,
        public ?int $maxTotalTimeout,
        public bool $resetTimeoutOnProgress,
        public \Closure $onTimeout
    ) {
    }
}
