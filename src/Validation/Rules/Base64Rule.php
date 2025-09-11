<?php

declare(strict_types=1);

namespace MCP\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

/**
 * Validates that a string is valid base64 encoded data.
 */
class Base64Rule extends AbstractRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        // Empty string is not valid base64
        if ($input === '') {
            return false;
        }

        // Check if it's valid base64
        $decoded = base64_decode($input, true);
        if ($decoded === false) {
            return false;
        }

        // Re-encode and compare to ensure it's properly formatted base64
        return base64_encode($decoded) === $input;
    }
}
