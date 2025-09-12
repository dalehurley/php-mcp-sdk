<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Shared\RequestHandlerExtra;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\ServerRequest;
use MCP\Types\ServerNotification;

/**
 * Callback for a prompt handler.
 * 
 * @template Args
 * @param Args $args The parsed and validated arguments (if schema provided)
 * @param RequestHandlerExtra<ServerRequest, ServerNotification> $extra
 * @return GetPromptResult|\Amp\Future<GetPromptResult>
 */
interface PromptCallback
{
    /**
     * @param mixed $argsOrExtra Either the parsed arguments or the RequestHandlerExtra (if no args schema)
     * @param RequestHandlerExtra|null $extra Only provided if args schema exists
     */
    public function __invoke($argsOrExtra, $extra = null);
}
