<?php

declare(strict_types=1);

namespace MCP\Types\Capabilities;

/**
 * Capabilities that a server may support.
 * Known capabilities are defined here, but this is not a closed set:
 * any server can define its own, additional capabilities.
 */
final class ServerCapabilities implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|null $experimental
     * @param array<string, mixed>|null $logging
     * @param array<string, mixed>|null $completions
     * @param array<string, mixed>|null $prompts
     * @param array<string, mixed>|null $resources
     * @param array<string, mixed>|null $tools
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?array $experimental = null,
        private readonly ?array $logging = null,
        private readonly ?array $completions = null,
        private readonly ?array $prompts = null,
        private readonly ?array $resources = null,
        private readonly ?array $tools = null,
        private readonly array $additionalProperties = []
    ) {}

    /**
     * Create from an array of data.
     */
    public static function fromArray(array $data): self
    {
        $experimental = isset($data['experimental']) && is_array($data['experimental']) ? $data['experimental'] : null;
        $logging = isset($data['logging']) && is_array($data['logging']) ? $data['logging'] : null;
        $completions = isset($data['completions']) && is_array($data['completions']) ? $data['completions'] : null;
        $prompts = isset($data['prompts']) && is_array($data['prompts']) ? $data['prompts'] : null;
        $resources = isset($data['resources']) && is_array($data['resources']) ? $data['resources'] : null;
        $tools = isset($data['tools']) && is_array($data['tools']) ? $data['tools'] : null;

        // Remove known properties to collect additional properties
        unset($data['experimental'], $data['logging'], $data['completions'], $data['prompts'], $data['resources'], $data['tools']);

        return new self(
            experimental: $experimental,
            logging: $logging,
            completions: $completions,
            prompts: $prompts,
            resources: $resources,
            tools: $tools,
            additionalProperties: $data
        );
    }

    /**
     * Get experimental, non-standard capabilities that the server supports.
     *
     * @return array<string, mixed>|null
     */
    public function getExperimental(): ?array
    {
        return $this->experimental;
    }

    /**
     * Check if the server supports experimental capabilities.
     */
    public function hasExperimental(): bool
    {
        return $this->experimental !== null;
    }

    /**
     * Get logging capability if the server supports sending log messages to the client.
     *
     * @return array<string, mixed>|null
     */
    public function getLogging(): ?array
    {
        return $this->logging;
    }

    /**
     * Check if the server supports logging.
     */
    public function hasLogging(): bool
    {
        return $this->logging !== null;
    }

    /**
     * Get completions capability if the server supports sending completions to the client.
     *
     * @return array<string, mixed>|null
     */
    public function getCompletions(): ?array
    {
        return $this->completions;
    }

    /**
     * Check if the server supports completions.
     */
    public function hasCompletions(): bool
    {
        return $this->completions !== null;
    }

    /**
     * Get prompts capability if the server offers any prompt templates.
     *
     * @return array<string, mixed>|null
     */
    public function getPrompts(): ?array
    {
        return $this->prompts;
    }

    /**
     * Check if the server supports prompts.
     */
    public function hasPrompts(): bool
    {
        return $this->prompts !== null;
    }

    /**
     * Check if the server supports prompts list changed notifications.
     */
    public function supportsPromptsListChanged(): bool
    {
        return $this->prompts !== null
            && isset($this->prompts['listChanged'])
            && $this->prompts['listChanged'] === true;
    }

    /**
     * Get resources capability if the server offers any resources to read.
     *
     * @return array<string, mixed>|null
     */
    public function getResources(): ?array
    {
        return $this->resources;
    }

    /**
     * Check if the server supports resources.
     */
    public function hasResources(): bool
    {
        return $this->resources !== null;
    }

    /**
     * Check if the server supports resource subscriptions.
     */
    public function supportsResourceSubscriptions(): bool
    {
        return $this->resources !== null
            && isset($this->resources['subscribe'])
            && $this->resources['subscribe'] === true;
    }

    /**
     * Check if the server supports resources list changed notifications.
     */
    public function supportsResourcesListChanged(): bool
    {
        return $this->resources !== null
            && isset($this->resources['listChanged'])
            && $this->resources['listChanged'] === true;
    }

    /**
     * Get tools capability if the server offers any tools to call.
     *
     * @return array<string, mixed>|null
     */
    public function getTools(): ?array
    {
        return $this->tools;
    }

    /**
     * Check if the server supports tools.
     */
    public function hasTools(): bool
    {
        return $this->tools !== null;
    }

    /**
     * Check if the server supports tools list changed notifications.
     */
    public function supportsToolsListChanged(): bool
    {
        return $this->tools !== null
            && isset($this->tools['listChanged'])
            && $this->tools['listChanged'] === true;
    }

    /**
     * Get additional properties.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalProperties(): array
    {
        return $this->additionalProperties;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = $this->additionalProperties;

        if ($this->experimental !== null) {
            $data['experimental'] = $this->experimental;
        }

        if ($this->logging !== null) {
            $data['logging'] = $this->logging;
        }

        if ($this->completions !== null) {
            $data['completions'] = $this->completions;
        }

        if ($this->prompts !== null) {
            $data['prompts'] = $this->prompts;
        }

        if ($this->resources !== null) {
            $data['resources'] = $this->resources;
        }

        if ($this->tools !== null) {
            $data['tools'] = $this->tools;
        }

        return $data;
    }
}
