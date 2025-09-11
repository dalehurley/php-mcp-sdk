<?php

declare(strict_types=1);

namespace MCP\Types;

/**
 * Protocol version constants and configuration for MCP.
 */
final class Protocol
{
    /**
     * The latest version of the Model Context Protocol.
     */
    public const LATEST_PROTOCOL_VERSION = '2025-06-18';

    /**
     * The default negotiated protocol version.
     */
    public const DEFAULT_NEGOTIATED_PROTOCOL_VERSION = '2025-03-26';

    /**
     * Supported protocol versions in order of preference.
     */
    public const SUPPORTED_PROTOCOL_VERSIONS = [
        self::LATEST_PROTOCOL_VERSION,
        '2025-03-26',
        '2024-11-05',
        '2024-10-07',
    ];

    /**
     * JSON-RPC version used by MCP.
     */
    public const JSONRPC_VERSION = '2.0';

    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Check if a protocol version is supported.
     */
    public static function isVersionSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED_PROTOCOL_VERSIONS, true);
    }

    /**
     * Get the best supported version from a list of versions.
     *
     * @param string[] $versions
     */
    public static function getBestSupportedVersion(array $versions): ?string
    {
        foreach (self::SUPPORTED_PROTOCOL_VERSIONS as $supportedVersion) {
            if (in_array($supportedVersion, $versions, true)) {
                return $supportedVersion;
            }
        }

        return null;
    }
}
