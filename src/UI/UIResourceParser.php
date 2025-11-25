<?php

declare(strict_types=1);

namespace MCP\UI;

/**
 * Parse and handle UIResource content blocks from MCP tool responses.
 *
 * This class provides utilities for extracting and processing UI resources
 * from MCP tool call responses, making it easy to separate text content
 * from interactive UI elements.
 *
 * @example
 * ```php
 * $response = $client->callTool('get_weather', ['city' => 'Sydney']);
 * $parsed = UIResourceParser::parse($response);
 *
 * echo $parsed['text'][0]['text']; // "Weather for Sydney: 25°C"
 * foreach ($parsed['ui'] as $resource) {
 *     echo UIResourceRenderer::renderIframe($resource);
 * }
 * ```
 */
class UIResourceParser
{
    /**
     * Check if a content block is a UIResource.
     *
     * A content block is considered a UIResource if it has type "resource"
     * and a URI starting with "ui://".
     *
     * @param array<string, mixed> $content The content block to check
     *
     * @return bool True if this is a UIResource
     */
    public static function isUIResource(array $content): bool
    {
        if (($content['type'] ?? '') !== 'resource') {
            return false;
        }

        $uri = $content['resource']['uri'] ?? '';

        return str_starts_with($uri, 'ui://');
    }

    /**
     * Check if a tool response contains any UI resources.
     *
     * @param array<string, mixed> $response The tool call response
     *
     * @return bool True if the response contains at least one UIResource
     */
    public static function hasUIResources(array $response): bool
    {
        foreach ($response['content'] ?? [] as $block) {
            if (self::isUIResource($block)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count the number of UI resources in a response.
     *
     * @param array<string, mixed> $response The tool call response
     *
     * @return int Number of UI resources
     */
    public static function countUIResources(array $response): int
    {
        $count = 0;
        foreach ($response['content'] ?? [] as $block) {
            if (self::isUIResource($block)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Extract all UIResources from a tool response.
     *
     * Separates the response content into text blocks and UI resources,
     * parsing each UI resource into a UIResourceData object.
     *
     * @param array<string, mixed> $response The full tool call response
     *
     * @return array{text: array<array<string, mixed>>, ui: array<UIResourceData>}
     */
    public static function parse(array $response): array
    {
        $result = [
            'text' => [],
            'ui' => [],
        ];

        foreach ($response['content'] ?? [] as $block) {
            if (self::isUIResource($block)) {
                $result['ui'][] = self::parseResource($block['resource']);
            } else {
                $result['text'][] = $block;
            }
        }

        return $result;
    }

    /**
     * Parse a single UIResource into a structured UIResourceData object.
     *
     * @param array<string, mixed> $resource The resource data from a content block
     *
     * @return UIResourceData Parsed resource data
     */
    public static function parseResource(array $resource): UIResourceData
    {
        $uri = $resource['uri'];
        $mimeType = $resource['mimeType'] ?? 'text/html';

        // Handle blob (base64) vs text content
        if (isset($resource['blob'])) {
            $content = base64_decode($resource['blob'], true);
            if ($content === false) {
                $content = $resource['blob']; // Fall back to raw if decode fails
            }
            $encoding = 'blob';
        } else {
            $content = $resource['text'] ?? '';
            $encoding = 'text';
        }

        // Determine content type from mimeType
        $type = self::determineType($mimeType);

        // Extract flavor for remote-dom
        $flavor = null;
        if ($type === 'remoteDom' && preg_match('/flavor=(\w+)/', $mimeType, $matches)) {
            $flavor = $matches[1];
        }

        return new UIResourceData(
            uri: $uri,
            type: $type,
            mimeType: $mimeType,
            content: $content,
            encoding: $encoding,
            flavor: $flavor
        );
    }

    /**
     * Determine the content type from a MIME type string.
     *
     * @param string $mimeType The MIME type
     *
     * @return string One of: 'html', 'url', 'remoteDom', 'unknown'
     */
    public static function determineType(string $mimeType): string
    {
        return match (true) {
            $mimeType === 'text/html' => 'html',
            $mimeType === 'text/uri-list' => 'url',
            str_contains($mimeType, 'remote-dom') => 'remoteDom',
            default => 'unknown',
        };
    }

    /**
     * Get just the text content from a response (ignoring UI resources).
     *
     * Useful when you need the plain text response for logging or
     * fallback display.
     *
     * @param array<string, mixed> $response The tool call response
     *
     * @return string Combined text content
     */
    public static function getTextOnly(array $response): string
    {
        $texts = [];
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'];
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Get only the UI resources from a response.
     *
     * @param array<string, mixed> $response The tool call response
     *
     * @return array<UIResourceData> Array of parsed UI resources
     */
    public static function getUIResourcesOnly(array $response): array
    {
        $resources = [];
        foreach ($response['content'] ?? [] as $block) {
            if (self::isUIResource($block)) {
                $resources[] = self::parseResource($block['resource']);
            }
        }

        return $resources;
    }

    /**
     * Find a specific UI resource by URI.
     *
     * @param array<string, mixed> $response The tool call response
     * @param string               $uri      The URI to find (e.g., "ui://weather/sydney")
     *
     * @return UIResourceData|null The found resource, or null
     */
    public static function findByUri(array $response, string $uri): ?UIResourceData
    {
        foreach ($response['content'] ?? [] as $block) {
            if (self::isUIResource($block) && ($block['resource']['uri'] ?? '') === $uri) {
                return self::parseResource($block['resource']);
            }
        }

        return null;
    }

    /**
     * Filter UI resources by type.
     *
     * @param array<string, mixed> $response The tool call response
     * @param string               $type     The type to filter by: 'html', 'url', 'remoteDom'
     *
     * @return array<UIResourceData> Filtered resources
     */
    public static function filterByType(array $response, string $type): array
    {
        $resources = [];
        foreach ($response['content'] ?? [] as $block) {
            if (self::isUIResource($block)) {
                $resource = self::parseResource($block['resource']);
                if ($resource->type === $type) {
                    $resources[] = $resource;
                }
            }
        }

        return $resources;
    }
}

