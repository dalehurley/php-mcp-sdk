<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Shared\RequestHandlerExtra;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\ServerNotification;
use MCP\Types\ServerRequest;

/**
 * Callback to read a resource at a given URI.
 *
 * @param \URL|string $uri The resource URI
 * @param RequestHandlerExtra<ServerRequest, ServerNotification> $extra
 *
 * @return ReadResourceResult|\Amp\Future<ReadResourceResult>
 */
interface ReadResourceCallback
{
    public function __invoke($uri, RequestHandlerExtra $extra);
}
