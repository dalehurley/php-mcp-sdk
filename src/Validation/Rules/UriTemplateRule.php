<?php

declare(strict_types=1);

namespace MCP\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

/**
 * Validates that a string is a valid URI template according to RFC 6570.
 */
class UriTemplateRule extends AbstractRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        // Basic validation for URI template
        // This is a simplified check - a full RFC 6570 validator would be more complex

        // Check for balanced braces
        $openBraces = substr_count($input, '{');
        $closeBraces = substr_count($input, '}');

        if ($openBraces !== $closeBraces) {
            return false;
        }

        // Check that braces are properly paired
        $depth = 0;
        for ($i = 0; $i < strlen($input); $i++) {
            if ($input[$i] === '{') {
                $depth++;
            } elseif ($input[$i] === '}') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
            }
        }

        // Check for valid variable names inside braces
        if (preg_match_all('/\{([^}]+)\}/', $input, $matches)) {
            foreach ($matches[1] as $variable) {
                // Variable expressions can have operators and modifiers
                // This is a simplified validation
                if (!preg_match('/^[+#.\/;?&]?[a-zA-Z0-9_]+(:[0-9]+)?(\*)?([,.]?[a-zA-Z0-9_]+(:[0-9]+)?(\*)?)*$/', $variable)) {
                    return false;
                }
            }
        }

        return true;
    }
}
