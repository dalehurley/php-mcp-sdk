<?php

declare(strict_types=1);

namespace MCP\UI;

use MCP\Client\Client;
use Amp\Future;

/**
 * Extended client helper for UI-aware tool calls.
 *
 * Wraps the MCP Client to provide convenient methods for working
 * with tools that return UI resources.
 *
 * @example
 * ```php
 * $client = new Client(new Implementation('my-app', '1.0.0'));
 * $uiClient = new UIResourceClient($client);
 *
 * // Get parsed response with separated text and UI
 * $result = $uiClient->callToolWithUI('get_weather', ['city' => 'Sydney']);
 * echo $result['text'];
 * foreach ($result['ui'] as $resource) {
 *     echo UIResourceRenderer::renderIframe($resource);
 * }
 *
 * // Or get JSON-ready response for API endpoints
 * $json = $uiClient->callToolForFrontend('get_weather', ['city' => 'Sydney']);
 * return response()->json($json);
 * ```
 */
class UIResourceClient
{
    /**
     * @param Client $client The MCP client to wrap
     */
    public function __construct(
        private Client $client
    ) {
    }

    /**
     * Get the underlying MCP client.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Call a tool and automatically parse UI resources.
     *
     * @param string               $name      Tool name
     * @param array<string, mixed> $arguments Tool arguments
     *
     * @return array{text: string, ui: array<UIResourceData>, raw: array<string, mixed>}
     *
     * @throws \Exception If the tool call fails
     */
    public function callToolWithUI(string $name, array $arguments = []): array
    {
        $response = $this->client->callToolByName($name, $arguments)->await();

        $parsed = UIResourceParser::parse($response);

        return [
            'text' => $this->extractText($parsed['text']),
            'ui' => $parsed['ui'],
            'raw' => $response,
        ];
    }

    /**
     * Call a tool and return response formatted for frontend/API consumption.
     *
     * Returns an array structure suitable for JSON encoding and sending
     * to a frontend application.
     *
     * @param string               $name      Tool name
     * @param array<string, mixed> $arguments Tool arguments
     *
     * @return array{text: string, ui: array<array<string, mixed>>, hasUI: bool}
     *
     * @throws \Exception If the tool call fails
     */
    public function callToolForFrontend(string $name, array $arguments = []): array
    {
        $result = $this->callToolWithUI($name, $arguments);

        return [
            'text' => $result['text'],
            'ui' => array_map(fn (UIResourceData $r) => $r->toArray(), $result['ui']),
            'hasUI' => count($result['ui']) > 0,
        ];
    }

    /**
     * Call a tool and render UI resources as HTML.
     *
     * Combines text and rendered UI into a single HTML output.
     *
     * @param string               $name        Tool name
     * @param array<string, mixed> $arguments   Tool arguments
     * @param array<string, mixed> $renderOptions Options for UIResourceRenderer::renderIframe
     *
     * @return array{text: string, html: string, ui: array<UIResourceData>}
     *
     * @throws \Exception If the tool call fails
     */
    public function callToolWithRenderedUI(
        string $name,
        array $arguments = [],
        array $renderOptions = []
    ): array {
        $result = $this->callToolWithUI($name, $arguments);

        $html = '';
        foreach ($result['ui'] as $resource) {
            $html .= UIResourceRenderer::renderIframe($resource, $renderOptions);
        }

        return [
            'text' => $result['text'],
            'html' => $html,
            'ui' => $result['ui'],
        ];
    }

    /**
     * Check if a tool response contains UI resources without fully parsing.
     *
     * @param string               $name      Tool name
     * @param array<string, mixed> $arguments Tool arguments
     *
     * @return bool True if the response contains UI resources
     *
     * @throws \Exception If the tool call fails
     */
    public function toolHasUI(string $name, array $arguments = []): bool
    {
        $response = $this->client->callToolByName($name, $arguments)->await();

        return UIResourceParser::hasUIResources($response);
    }

    /**
     * Call a tool and get only the text response (ignoring UI).
     *
     * Useful when you only need the text fallback and don't want
     * to process UI resources.
     *
     * @param string               $name      Tool name
     * @param array<string, mixed> $arguments Tool arguments
     *
     * @return string Text-only response
     *
     * @throws \Exception If the tool call fails
     */
    public function callToolTextOnly(string $name, array $arguments = []): string
    {
        $response = $this->client->callToolByName($name, $arguments)->await();

        return UIResourceParser::getTextOnly($response);
    }

    /**
     * Call a tool and get only the UI resources (ignoring text).
     *
     * @param string               $name      Tool name
     * @param array<string, mixed> $arguments Tool arguments
     *
     * @return array<UIResourceData> UI resources only
     *
     * @throws \Exception If the tool call fails
     */
    public function callToolUIOnly(string $name, array $arguments = []): array
    {
        $response = $this->client->callToolByName($name, $arguments)->await();

        return UIResourceParser::getUIResourcesOnly($response);
    }

    /**
     * Extract combined text from text content blocks.
     *
     * @param array<array<string, mixed>> $textBlocks Text content blocks
     *
     * @return string Combined text
     */
    private function extractText(array $textBlocks): string
    {
        return implode("\n", array_map(
            fn (array $b) => $b['text'] ?? '',
            $textBlocks
        ));
    }
}

