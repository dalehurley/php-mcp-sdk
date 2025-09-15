<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Implementation;
use MCP\Types\Request;

/**
 * This request is sent from the client to the server when it first connects,
 * asking it to begin initialization.
 */
final class InitializeRequest extends Request
{
    public const METHOD = 'initialize';

    /**
     * @param array<string, mixed>|null|string $methodOrParams For backward compatibility, can be params array or method string
     * @param array<string, mixed>|null $params Only used when first parameter is method string
     */
    public function __construct($methodOrParams = null, ?array $params = null)
    {
        // Handle backward compatibility: if first param is array, treat as params
        if (is_array($methodOrParams)) {
            parent::__construct(self::METHOD, $methodOrParams);
        } else {
            // If method is null, use default. If method is provided, it should match our expected method.
            $method = $methodOrParams ?? self::METHOD;
            if ($method !== self::METHOD) {
                throw new \InvalidArgumentException("Invalid method for InitializeRequest: expected '" . self::METHOD . "', got '$method'");
            }
            parent::__construct($method, $params);
        }
    }

    /**
     * Create a new initialize request.
     */
    public static function create(
        string $protocolVersion,
        ClientCapabilities $capabilities,
        Implementation $clientInfo
    ): self {
        return new self([
            'protocolVersion' => $protocolVersion,
            'capabilities' => $capabilities->jsonSerialize(),
            'clientInfo' => $clientInfo->jsonSerialize(),
        ]);
    }

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): static
    {
        return new static($data['params'] ?? null);
    }

    /**
     * Get the protocol version.
     */
    public function getProtocolVersion(): ?string
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['protocolVersion'])) {
            return null;
        }

        return is_string($params['protocolVersion']) ? $params['protocolVersion'] : null;
    }

    /**
     * Get the client capabilities.
     */
    public function getCapabilities(): ?ClientCapabilities
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['capabilities']) || !is_array($params['capabilities'])) {
            return null;
        }

        return ClientCapabilities::fromArray($params['capabilities']);
    }

    /**
     * Get the client info.
     */
    public function getClientInfo(): ?Implementation
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['clientInfo']) || !is_array($params['clientInfo'])) {
            return null;
        }

        return Implementation::fromArray($params['clientInfo']);
    }

    /**
     * Check if this is a valid initialize request.
     */
    public static function isValid(mixed $value): bool
    {
        if (!parent::isValid($value)) {
            return false;
        }

        if (!is_array($value) || ($value['method'] ?? null) !== self::METHOD) {
            return false;
        }

        $params = $value['params'] ?? null;
        if (!is_array($params)) {
            return false;
        }

        return isset($params['protocolVersion'])
            && isset($params['capabilities'])
            && isset($params['clientInfo']);
    }
}
