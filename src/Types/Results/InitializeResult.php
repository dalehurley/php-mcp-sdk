<?php

declare(strict_types=1);

namespace MCP\Types\Results;

use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Types\Implementation;
use MCP\Types\Result;

/**
 * After receiving an initialize request from the client, the server sends this response.
 */
final class InitializeResult extends Result
{
    /**
     * @param array<string, mixed>|null $_meta
     */
    public function __construct(
        private readonly string $protocolVersion,
        private readonly ServerCapabilities $capabilities,
        private readonly Implementation $serverInfo,
        private readonly ?string $instructions = null,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['protocolVersion']) || !is_string($data['protocolVersion'])) {
            throw new \InvalidArgumentException('InitializeResult must have a protocolVersion property');
        }

        if (!isset($data['capabilities']) || !is_array($data['capabilities'])) {
            throw new \InvalidArgumentException('InitializeResult must have a capabilities property');
        }

        if (!isset($data['serverInfo']) || !is_array($data['serverInfo'])) {
            throw new \InvalidArgumentException('InitializeResult must have a serverInfo property');
        }

        return new self(
            protocolVersion: $data['protocolVersion'],
            capabilities: ServerCapabilities::fromArray($data['capabilities']),
            serverInfo: Implementation::fromArray($data['serverInfo']),
            instructions: isset($data['instructions']) && is_string($data['instructions'])
                ? $data['instructions']
                : null,
            _meta: isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null
        );
    }

    /**
     * Get the protocol version.
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * Get the server capabilities.
     */
    public function getCapabilities(): ServerCapabilities
    {
        return $this->capabilities;
    }

    /**
     * Get the server info.
     */
    public function getServerInfo(): Implementation
    {
        return $this->serverInfo;
    }

    /**
     * Get the instructions.
     */
    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    /**
     * @return array{
     *     protocolVersion: string,
     *     capabilities: array<string, mixed>,
     *     serverInfo: array{name: string, version: string},
     *     instructions?: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        $data = [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities->jsonSerialize(),
            'serverInfo' => $this->serverInfo->jsonSerialize(),
        ];

        if ($this->instructions !== null) {
            $data['instructions'] = $this->instructions;
        }

        $meta = $this->getMeta();
        if ($meta !== null) {
            $data['_meta'] = $meta;
        }

        return $data;
    }
}
