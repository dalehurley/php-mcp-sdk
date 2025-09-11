<?php

declare(strict_types=1);

namespace MCP\Shared;

use MCP\Types\JsonRpc\JSONRPCMessage;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\JsonRpc\JSONRPCResponse;
use MCP\Types\JsonRpc\JSONRPCError;

/**
 * Utility class for handling message framing in different transport protocols
 * Provides methods for serializing, deserializing, and validating JSON-RPC messages
 */
class MessageFraming
{
    /**
     * Maximum message size in bytes (4MB by default)
     */
    public const DEFAULT_MAX_MESSAGE_SIZE = 4 * 1024 * 1024;

    /**
     * Serialize a JSON-RPC message for transmission over newline-delimited JSON transport
     * 
     * @param array|JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError $message The message to serialize
     * @param bool $validate Whether to validate the message structure
     * @return string The serialized message with newline terminator
     * @throws \InvalidArgumentException If message is invalid
     * @throws \JsonException If JSON encoding fails
     */
    public static function serializeMessage(array|JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError $message, bool $validate = true): string
    {
        if (is_array($message)) {
            $messageArray = $message;
        } else {
            // It's a JSONRPCRequest, JSONRPCNotification, JSONRPCResponse, or JSONRPCError
            $messageArray = $message instanceof \JsonSerializable ? $message->jsonSerialize() : (array) $message;
        }

        if ($validate) {
            self::validateMessageStructure($messageArray);
        }

        $json = json_encode($messageArray, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        // Ensure the message doesn't exceed size limits
        if (strlen($json) > self::DEFAULT_MAX_MESSAGE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Message size (%d bytes) exceeds maximum allowed size (%d bytes)',
                    strlen($json),
                    self::DEFAULT_MAX_MESSAGE_SIZE
                )
            );
        }

        return $json . "\n";
    }

    /**
     * Deserialize a JSON-RPC message from string
     * 
     * @param string $data The JSON string to deserialize
     * @param bool $validate Whether to validate the message structure
     * @return JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError The deserialized message
     * @throws \InvalidArgumentException If message is invalid
     * @throws \JsonException If JSON decoding fails
     */
    public static function deserializeMessage(string $data, bool $validate = true): JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
    {
        $messageArray = json_decode(trim($data), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($messageArray)) {
            throw new \InvalidArgumentException('Message must be a JSON object');
        }

        if ($validate) {
            self::validateMessageStructure($messageArray);
        }

        return JSONRPCMessage::fromArray($messageArray);
    }

    /**
     * Parse multiple messages from a newline-delimited JSON stream
     * 
     * @param string $data The stream data containing multiple messages
     * @param bool $validate Whether to validate each message
     * @return array<JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError> Array of parsed messages
     * @throws \InvalidArgumentException If any message is invalid
     * @throws \JsonException If JSON decoding fails
     */
    public static function parseMessages(string $data, bool $validate = true): array
    {
        $lines = explode("\n", $data);
        $messages = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue; // Skip empty lines
            }

            $messages[] = self::deserializeMessage($line, $validate);
        }

        return $messages;
    }

    /**
     * Validate the structure of a JSON-RPC message array
     * 
     * @param array $message The message array to validate
     * @throws \InvalidArgumentException If message structure is invalid
     */
    public static function validateMessageStructure(array $message): void
    {
        // Check for required jsonrpc field
        if (!isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
            throw new \InvalidArgumentException('Message must have jsonrpc field set to "2.0"');
        }

        // Determine message type and validate accordingly
        if (isset($message['method'])) {
            // Request or Notification
            if (!is_string($message['method']) || empty($message['method'])) {
                throw new \InvalidArgumentException('Method must be a non-empty string');
            }

            if (isset($message['id'])) {
                // Request - must have id
                self::validateRequestId($message['id']);
            }
            // Notification - no id field
        } elseif (isset($message['result']) || isset($message['error'])) {
            // Response or Error
            if (!isset($message['id'])) {
                throw new \InvalidArgumentException('Response messages must have an id field');
            }

            self::validateRequestId($message['id']);

            if (isset($message['result']) && isset($message['error'])) {
                throw new \InvalidArgumentException('Response cannot have both result and error fields');
            }

            if (!isset($message['result']) && !isset($message['error'])) {
                throw new \InvalidArgumentException('Response must have either result or error field');
            }
        } else {
            throw new \InvalidArgumentException('Message must be a request, notification, or response');
        }
    }

    /**
     * Validate a request ID
     * 
     * @param mixed $id The ID to validate
     * @throws \InvalidArgumentException If ID is invalid
     */
    private static function validateRequestId(mixed $id): void
    {
        if (!is_string($id) && !is_int($id) && $id !== null) {
            throw new \InvalidArgumentException('Request ID must be a string, number, or null');
        }
    }

    /**
     * Create a chunked message stream for large messages
     * Splits large messages into smaller chunks for transmission
     * 
     * @param array|JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError $message The message to chunk
     * @param int $chunkSize Maximum size of each chunk in bytes
     * @return array<string> Array of serialized message chunks
     * @throws \InvalidArgumentException If message is invalid
     */
    public static function createChunkedStream(
        array|JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError $message,
        int $chunkSize = 1024
    ): array {
        $serialized = self::serializeMessage($message, true);

        if (strlen($serialized) <= $chunkSize) {
            return [$serialized];
        }

        $chunks = [];
        $offset = 0;
        $length = strlen($serialized);

        while ($offset < $length) {
            $chunks[] = substr($serialized, $offset, $chunkSize);
            $offset += $chunkSize;
        }

        return $chunks;
    }

    /**
     * Reconstruct a message from chunks
     * 
     * @param array<string> $chunks Array of message chunks
     * @param bool $validate Whether to validate the reconstructed message
     * @return JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError The reconstructed message
     * @throws \InvalidArgumentException If reconstruction fails
     */
    public static function reconstructFromChunks(array $chunks, bool $validate = true): JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError
    {
        $reconstructed = implode('', $chunks);
        return self::deserializeMessage($reconstructed, $validate);
    }

    /**
     * Check if a string contains a complete JSON-RPC message
     * 
     * @param string $data The data to check
     * @return bool True if data contains a complete message
     */
    public static function isCompleteMessage(string $data): bool
    {
        $trimmed = trim($data);
        if (empty($trimmed)) {
            return false;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) && isset($decoded['jsonrpc']);
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Extract complete messages from a partial stream buffer
     * Returns complete messages and remaining buffer data
     * 
     * @param string $buffer The buffer containing partial messages
     * @return array{messages: array<JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError>, remaining: string}
     */
    public static function extractCompleteMessages(string $buffer): array
    {
        $lines = explode("\n", $buffer);
        $messages = [];
        $remaining = '';

        // Process all lines except the last one (which might be incomplete)
        for ($i = 0; $i < count($lines) - 1; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            try {
                $messages[] = self::deserializeMessage($line, true);
            } catch (\Throwable) {
                // Skip invalid messages but continue processing
                continue;
            }
        }

        // The last line might be incomplete, so keep it as remaining buffer
        $lastLine = end($lines);
        if ($lastLine !== false && !str_ends_with($buffer, "\n")) {
            $remaining = $lastLine;
        }

        return [
            'messages' => $messages,
            'remaining' => $remaining
        ];
    }
}
