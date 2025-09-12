<?php

declare(strict_types=1);

namespace MCP\Shared;

use MCP\Types\RequestId;

/**
 * Options that can be given per notification.
 */
class NotificationOptions
{
    public function __construct(
        public readonly ?RequestId $relatedRequestId = null
    ) {}
}
