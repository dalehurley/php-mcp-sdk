<?php

declare(strict_types=1);

namespace MCP\Shared;

use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\JsonRpc\JSONRPCMessage;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCResponse;
use MCP\Validation\ValidationException;

/**
 * Buffers a continuous stdio stream into discrete JSON-RPC messages.
 */
class ReadBuffer
{
    private ?string $buffer = null;

    /**
     * Append data to the buffer.
     *
     * @param string $chunk
     */
    public function append(string $chunk): void
    {
        if ($this->buffer === null) {
            $this->buffer = $chunk;
        } else {
            $this->buffer .= $chunk;
        }
    }

    /**
     * Get the buffer contents.
     *
     * @return string|null
     */
    public function getBuffer(): ?string
    {
        return $this->buffer;
    }

    /**
     * Clear the buffer.
     */
    public function clear(): void
    {
        $this->buffer = null;
    }

    /**
     * Check if the buffer has data.
     *
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->buffer !== null && strlen($this->buffer) > 0;
    }

    /**
     * Try to read a complete message from the buffer.
     *
     * @return JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError|null
     */
    public function readMessage(): JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError|null
    {
        if ($this->buffer === null) {
            return null;
        }

        // Find the first newline
        $newlinePos = strpos($this->buffer, "\n");
        if ($newlinePos === false) {
            return null;
        }

        // Extract the message
        $line = substr($this->buffer, 0, $newlinePos);
        $this->buffer = substr($this->buffer, $newlinePos + 1);

        // Trim any carriage return
        $line = rtrim($line, "\r");

        if (empty($line)) {
            return null;
        }

        try {
            return self::deserializeMessage($line);
        } catch (\Throwable $e) {
            // Invalid message, skip it
            return null;
        }
    }

    /**
     * Deserialize a JSON string into a JSON-RPC message.
     *
     * @param string $json
     *
     * @return JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
     *
     * @throws ValidationException
     */
    public static function deserializeMessage(string $json): JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException('Invalid JSON: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new ValidationException('JSON must decode to an object');
        }

        return JSONRPCMessage::fromArray($data);
    }

    /**
     * Serialize a JSON-RPC message to a string with newline delimiter.
     *
     * @param JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError|array $message
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public static function serializeMessage(JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError|array $message): string
    {
        if (is_object($message) && method_exists($message, 'jsonSerialize')) {
            $data = $message->jsonSerialize();
        } else {
            $data = $message;
        }

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException(
                'Failed to encode JSON: ' . json_last_error_msg()
            );
        }

        return $json . "\n";
    }
}
