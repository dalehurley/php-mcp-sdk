<?php

declare(strict_types=1);

namespace MCP\Shared;

use MCP\Types\RequestId;

/**
 * Options that can be given per request.
 */
class RequestOptions
{
    public function __construct(
        public readonly mixed $onprogress = null,
        public readonly ?\Revolt\EventLoop\Suspension $signal = null,
        public readonly ?int $timeout = null,
        public readonly bool $resetTimeoutOnProgress = false,
        public readonly ?int $maxTotalTimeout = null,
        public readonly ?RequestId $relatedRequestId = null,
        public readonly ?string $resumptionToken = null,
        public readonly mixed $onresumptiontoken = null
    ) {
    }
}
