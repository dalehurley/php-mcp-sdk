<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * STDIO utilities for reading and writing messages.
 */
class Stdio
{
    /**
     * Read data from STDIN in a non-blocking way.
     *
     * @param resource $stdin The STDIN resource
     * @param int $length Maximum bytes to read
     * @return string|false The data read or false on failure
     */
    public static function readNonBlocking($stdin, int $length = 8192): string|false
    {
        return fread($stdin, $length);
    }

    /**
     * Write data to STDOUT.
     *
     * @param resource $stdout The STDOUT resource
     * @param string $data The data to write
     * @return int|false The number of bytes written or false on error
     */
    public static function write($stdout, string $data): int|false
    {
        return fwrite($stdout, $data);
    }

    /**
     * Check if STDIN has data available to read.
     *
     * @param resource $stdin The STDIN resource
     * @param int $timeout Timeout in microseconds
     * @return bool
     */
    public static function hasDataAvailable($stdin, int $timeout = 0): bool
    {
        $read = [$stdin];
        $write = null;
        $except = null;

        $tv_sec = intval($timeout / 1000000);
        $tv_usec = $timeout % 1000000;

        $result = stream_select($read, $write, $except, $tv_sec, $tv_usec);

        return $result > 0;
    }

    /**
     * Create a read buffer for processing STDIO messages.
     *
     * @return ReadBuffer
     */
    public static function createReadBuffer(): ReadBuffer
    {
        return new ReadBuffer();
    }
}
