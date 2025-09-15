<?php

declare(strict_types=1);

namespace MCP\Shared;

use MCP\Types\BaseMetadata;

/**
 * Utilities for working with BaseMetadata objects.
 */
class MetadataUtils
{
    /**
     * Gets the display name for an object with BaseMetadata.
     * For tools, the precedence is: title → annotations.title → name
     * For other objects: title → name
     * This implements the spec requirement: "if no title is provided, name should be used for display purposes".
     *
     * @param BaseMetadata|array $metadata The metadata object or array
     *
     * @return string The display name
     */
    public static function getDisplayName(BaseMetadata|array $metadata): string
    {
        // Handle array input
        if (is_array($metadata)) {
            // First check for title (not undefined and not empty string)
            if (isset($metadata['title']) && $metadata['title'] !== '') {
                return $metadata['title'];
            }

            // Then check for annotations.title (only present in Tool objects)
            if (isset($metadata['annotations']['title']) && $metadata['annotations']['title'] !== '') {
                return $metadata['annotations']['title'];
            }

            // Finally fall back to name
            return $metadata['name'] ?? '';
        }

        // Handle BaseMetadata object
        // First check for title (not null and not empty string)
        $title = $metadata->getTitle();
        if ($title !== null && $title !== '') {
            return $title;
        }

        // Then check for annotations.title (only present in Tool objects)
        // We'll check in additional properties
        $additionalProps = $metadata->getAdditionalProperties();
        if (isset($additionalProps['annotations']['title']) && $additionalProps['annotations']['title'] !== '') {
            return $additionalProps['annotations']['title'];
        }

        // Finally fall back to name
        return $metadata->getName();
    }

    /**
     * Check if metadata has a title.
     *
     * @param BaseMetadata|array $metadata
     *
     * @return bool
     */
    public static function hasTitle(BaseMetadata|array $metadata): bool
    {
        if (is_array($metadata)) {
            return isset($metadata['title']) && $metadata['title'] !== '';
        }

        $title = $metadata->getTitle();

        return $title !== null && $title !== '';
    }

    /**
     * Get the description from metadata.
     *
     * @param BaseMetadata|array $metadata
     *
     * @return string|null
     */
    public static function getDescription(BaseMetadata|array $metadata): ?string
    {
        if (is_array($metadata)) {
            return $metadata['description'] ?? null;
        }

        // BaseMetadata doesn't have a description property, but subclasses might
        // Check in additional properties
        $additionalProps = $metadata->getAdditionalProperties();

        return $additionalProps['description'] ?? null;
    }

    /**
     * Check if metadata has a description.
     *
     * @param BaseMetadata|array $metadata
     *
     * @return bool
     */
    public static function hasDescription(BaseMetadata|array $metadata): bool
    {
        $description = self::getDescription($metadata);

        return $description !== null && $description !== '';
    }

    /**
     * Create a display string with name and optional title.
     * Format: "name (title)" if title exists and differs from name, otherwise just "name".
     *
     * @param BaseMetadata|array $metadata
     *
     * @return string
     */
    public static function getDisplayString(BaseMetadata|array $metadata): string
    {
        $name = is_array($metadata) ? ($metadata['name'] ?? '') : $metadata->getName();
        $displayName = self::getDisplayName($metadata);

        if ($displayName !== $name && $displayName !== '') {
            return "{$name} ({$displayName})";
        }

        return $name;
    }
}
