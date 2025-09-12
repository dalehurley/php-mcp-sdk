<?php

declare(strict_types=1);

namespace MCP\Shared;

use MCP\Types\Request;
use MCP\Types\Notification;
use MCP\Types\RequestId;
use MCP\Types\RequestMeta;
use MCP\Validation\ValidationService;
use Amp\Future;

/**
 * Extra data given to request handlers.
 * 
 * @template SendRequestT of Request
 * @template SendNotificationT of Notification
 */
class RequestHandlerExtra
{
    /**
     * @param callable(SendNotificationT): Future<void> $sendNotification
     * @param callable(SendRequestT, ValidationService, RequestOptions|null): Future $sendRequest
     */
    public function __construct(
        public readonly \Revolt\EventLoop\Suspension $signal,
        public readonly mixed $authInfo,
        public readonly ?string $sessionId,
        public readonly ?RequestMeta $_meta,
        public readonly RequestId $requestId,
        public readonly ?array $requestInfo,
        public $sendNotification,
        public $sendRequest
    ) {}
}
