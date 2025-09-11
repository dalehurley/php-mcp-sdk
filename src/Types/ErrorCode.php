<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Error codes defined by the MCP protocol.
 */
enum ErrorCode: int
{
    // SDK error codes
    case ConnectionClosed = -32000;
    case RequestTimeout = -32001;

        // Standard JSON-RPC error codes
    case ParseError = -32700;
    case InvalidRequest = -32600;
    case MethodNotFound = -32601;
    case InvalidParams = -32602;
    case InternalError = -32603;
}
