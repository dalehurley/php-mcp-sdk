<?php

declare(strict_types=1);

namespace MCP\Types\Requests;

use MCP\Types\Request;
use MCP\Types\Sampling\SamplingMessage;
use MCP\Types\Sampling\ModelPreferences;

/**
 * A request from the server to sample an LLM via the client. The client has
 * full discretion over which model to select. The client should also inform
 * the user before beginning sampling, to allow them to inspect the request
 * (human in the loop) and decide whether to approve it.
 */
final class CreateMessageRequest extends Request
{
    public const METHOD = 'sampling/createMessage';

    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(?array $params = null)
    {
        parent::__construct(self::METHOD, $params);
    }

    /**
     * Create a new create message request.
     *
     * @param SamplingMessage[] $messages
     * @param array<string, mixed>|null $metadata
     * @param string[] $stopSequences
     */
    public static function create(
        array $messages,
        int $maxTokens,
        ?string $systemPrompt = null,
        ?string $includeContext = null,
        ?float $temperature = null,
        ?array $stopSequences = null,
        ?array $metadata = null,
        ?ModelPreferences $modelPreferences = null
    ): self {
        $params = [
            'messages' => array_map(fn(SamplingMessage $msg) => $msg->jsonSerialize(), $messages),
            'maxTokens' => $maxTokens,
        ];

        if ($systemPrompt !== null) {
            $params['systemPrompt'] = $systemPrompt;
        }

        if ($includeContext !== null) {
            $params['includeContext'] = $includeContext;
        }

        if ($temperature !== null) {
            $params['temperature'] = $temperature;
        }

        if ($stopSequences !== null) {
            $params['stopSequences'] = $stopSequences;
        }

        if ($metadata !== null) {
            $params['metadata'] = $metadata;
        }

        if ($modelPreferences !== null) {
            $params['modelPreferences'] = $modelPreferences->jsonSerialize();
        }

        return new self($params);
    }

    /**
     * Get the messages.
     *
     * @return SamplingMessage[]|null
     */
    public function getMessages(): ?array
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['messages']) || !is_array($params['messages'])) {
            return null;
        }

        $messages = [];
        foreach ($params['messages'] as $message) {
            if (is_array($message)) {
                $messages[] = SamplingMessage::fromArray($message);
            }
        }

        return $messages;
    }

    /**
     * Get the system prompt.
     */
    public function getSystemPrompt(): ?string
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['systemPrompt'])) {
            return null;
        }

        return is_string($params['systemPrompt']) ? $params['systemPrompt'] : null;
    }

    /**
     * Get the include context setting.
     */
    public function getIncludeContext(): ?string
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['includeContext'])) {
            return null;
        }

        return is_string($params['includeContext']) ? $params['includeContext'] : null;
    }

    /**
     * Get the temperature.
     */
    public function getTemperature(): ?float
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['temperature'])) {
            return null;
        }

        return is_numeric($params['temperature']) ? (float) $params['temperature'] : null;
    }

    /**
     * Get the maximum tokens.
     */
    public function getMaxTokens(): ?int
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['maxTokens'])) {
            return null;
        }

        return is_int($params['maxTokens']) ? $params['maxTokens'] : null;
    }

    /**
     * Get the stop sequences.
     *
     * @return string[]|null
     */
    public function getStopSequences(): ?array
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['stopSequences'])) {
            return null;
        }

        if (is_array($params['stopSequences'])) {
            return array_filter($params['stopSequences'], 'is_string');
        }

        return null;
    }

    /**
     * Get the metadata.
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['metadata'])) {
            return null;
        }

        return is_array($params['metadata']) ? $params['metadata'] : null;
    }

    /**
     * Get the model preferences.
     */
    public function getModelPreferences(): ?ModelPreferences
    {
        $params = $this->getParams();
        if ($params === null || !isset($params['modelPreferences']) || !is_array($params['modelPreferences'])) {
            return null;
        }

        return ModelPreferences::fromArray($params['modelPreferences']);
    }

    /**
     * Check if this is a valid create message request.
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

        return isset($params['messages']) && isset($params['maxTokens']);
    }
}
