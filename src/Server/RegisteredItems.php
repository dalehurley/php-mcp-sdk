<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Shared\RequestHandlerExtra;
use MCP\Types\ServerRequest;
use MCP\Types\ServerNotification;
use MCP\Types\Results\CallToolResult;
use MCP\Types\Results\GetPromptResult;
use MCP\Types\Results\ReadResourceResult;
use MCP\Server\ToolCallback;
use MCP\Server\RegisteredTool;
use MCP\Server\ReadResourceCallback;
use MCP\Server\RegisteredResource;
use MCP\Server\ReadResourceTemplateCallback;
use MCP\Server\RegisteredResourceTemplate;
use MCP\Server\PromptCallback;
use MCP\Server\RegisteredPrompt;
use MCP\Types\Tools\ToolAnnotations;
