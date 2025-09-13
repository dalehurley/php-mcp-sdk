<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Shared\RequestHandlerExtra;
use MCP\Types\Results\ReadResourceResult;
use MCP\Types\ServerRequest;
use MCP\Types\ServerNotification;

/**
 * Callback to read a resource at a given URI, following a filled-in URI template.
 *
 * @param \URL|string $uri The resource URI
 * @param array<string, string> $variables The template variables
 * @param RequestHandlerExtra<ServerRequest, ServerNotification> $extra
 * @return ReadResourceResult|\Amp\Future<ReadResourceResult>
 */
interface ReadResourceTemplateCallback
{
    public function __invoke($uri, array $variables, RequestHandlerExtra $extra);
}
