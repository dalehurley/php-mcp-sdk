<?php

declare(strict_types=1);

namespace MCP\Shared;

/**
 * RFC 6570 URI Template implementation
 * 
 * This is a Claude-authored implementation of a subset of RFC 6570 URI Templates.
 * It supports simple string expansion with {variable} syntax.
 */
class UriTemplate
{
    private const MAX_TEMPLATE_LENGTH = 1000000; // 1MB
    private const MAX_VARIABLE_LENGTH = 1000000; // 1MB
    private const MAX_TEMPLATE_EXPRESSIONS = 10000;
    private const MAX_REGEX_LENGTH = 1000000; // 1MB

    private string $template;
    /** @var array<int, string|array{name: string, operator: string, names: array<string>, exploded: bool}> */
    private array $parts;

    /**
     * Returns true if the given string contains any URI template expressions.
     * A template expression is a sequence of characters enclosed in curly braces,
     * like {foo} or {?bar}.
     */
    public static function isTemplate(string $str): bool
    {
        // Look for any sequence of characters between curly braces
        // that isn't just whitespace
        return preg_match('/\{[^}\s]+\}/', $str) === 1;
    }

    private static function validateLength(string $str, int $max, string $context): void
    {
        if (strlen($str) > $max) {
            throw new \Error(
                "{$context} exceeds maximum length of {$max} characters (got " . strlen($str) . ")"
            );
        }
    }

    public function __construct(string $template)
    {
        self::validateLength($template, self::MAX_TEMPLATE_LENGTH, "Template");
        $this->template = $template;
        $this->parts = $this->parse($template);
    }

    public function __toString(): string
    {
        return $this->template;
    }

    /**
     * @return array<string>
     */
    public function getVariableNames(): array
    {
        $names = [];
        foreach ($this->parts as $part) {
            if (is_array($part)) {
                $names = array_merge($names, $part['names']);
            }
        }
        return $names;
    }

    /**
     * @return array<int, string|array{name: string, operator: string, names: array<string>, exploded: bool}>
     */
    private function parse(string $template): array
    {
        $parts = [];
        $currentText = "";
        $i = 0;
        $expressionCount = 0;

        while ($i < strlen($template)) {
            if ($template[$i] === "{") {
                if ($currentText !== "") {
                    $parts[] = $currentText;
                    $currentText = "";
                }
                $end = strpos($template, "}", $i);
                if ($end === false) {
                    throw new \Error("Unclosed template expression");
                }

                $expressionCount++;
                if ($expressionCount > self::MAX_TEMPLATE_EXPRESSIONS) {
                    throw new \Error(
                        "Template contains too many expressions (max " . self::MAX_TEMPLATE_EXPRESSIONS . ")"
                    );
                }

                $expr = substr($template, $i + 1, $end - $i - 1);
                $operator = $this->getOperator($expr);
                $exploded = str_contains($expr, "*");
                $names = $this->getNames($expr);
                $name = $names[0] ?? "";

                // Validate variable name length
                foreach ($names as $varName) {
                    self::validateLength(
                        $varName,
                        self::MAX_VARIABLE_LENGTH,
                        "Variable name"
                    );
                }

                $parts[] = [
                    'name' => $name,
                    'operator' => $operator,
                    'names' => $names,
                    'exploded' => $exploded
                ];
                $i = $end + 1;
            } else {
                $currentText .= $template[$i];
                $i++;
            }
        }

        if ($currentText !== "") {
            $parts[] = $currentText;
        }

        return $parts;
    }

    private function getOperator(string $expr): string
    {
        $operators = ["+", "#", ".", "/", "?", "&"];
        foreach ($operators as $op) {
            if (str_starts_with($expr, $op)) {
                return $op;
            }
        }
        return "";
    }

    /**
     * @return array<string>
     */
    private function getNames(string $expr): array
    {
        $operator = $this->getOperator($expr);
        $expr = substr($expr, strlen($operator));
        $names = explode(",", $expr);
        $result = [];

        foreach ($names as $name) {
            $name = str_replace("*", "", trim($name));
            if ($name !== "") {
                $result[] = $name;
            }
        }

        return $result;
    }

    private function encodeValue(string $value, string $operator): string
    {
        self::validateLength($value, self::MAX_VARIABLE_LENGTH, "Variable value");
        if ($operator === "+" || $operator === "#") {
            return str_replace(['%2F'], ['/'], rawurlencode($value));
        }
        return rawurlencode($value);
    }

    /**
     * @param array{name: string, operator: string, names: array<string>, exploded: bool} $part
     * @param array<string, string|array<string>> $variables
     */
    private function expandPart(array $part, array $variables): string
    {
        if ($part['operator'] === "?" || $part['operator'] === "&") {
            $pairs = [];
            foreach ($part['names'] as $name) {
                if (!isset($variables[$name])) {
                    continue;
                }
                $value = $variables[$name];
                $encoded = is_array($value)
                    ? implode(",", array_map(fn($v) => $this->encodeValue($v, $part['operator']), $value))
                    : $this->encodeValue((string) $value, $part['operator']);
                $pairs[] = "{$name}={$encoded}";
            }

            if (count($pairs) === 0) {
                return "";
            }
            $separator = $part['operator'] === "?" ? "?" : "&";
            return $separator . implode("&", $pairs);
        }

        if (count($part['names']) > 1) {
            $values = [];
            foreach ($part['names'] as $name) {
                if (isset($variables[$name])) {
                    $v = $variables[$name];
                    $values[] = is_array($v) ? $v[0] : $v;
                }
            }
            if (count($values) === 0) {
                return "";
            }
            return implode(",", $values);
        }

        if (!isset($variables[$part['name']])) {
            return "";
        }

        $value = $variables[$part['name']];
        $values = is_array($value) ? $value : [$value];
        $encoded = array_map(fn($v) => $this->encodeValue((string) $v, $part['operator']), $values);

        switch ($part['operator']) {
            case "":
                return implode(",", $encoded);
            case "+":
                return implode(",", $encoded);
            case "#":
                return "#" . implode(",", $encoded);
            case ".":
                return "." . implode(".", $encoded);
            case "/":
                return "/" . implode("/", $encoded);
            default:
                return implode(",", $encoded);
        }
    }

    /**
     * @param array<string, string|array<string>> $variables
     */
    public function expand(array $variables): string
    {
        $result = "";
        $hasQueryParam = false;

        foreach ($this->parts as $part) {
            if (is_string($part)) {
                $result .= $part;
                continue;
            }

            $expanded = $this->expandPart($part, $variables);
            if ($expanded === "") {
                continue;
            }

            // Convert ? to & if we already have a query parameter
            if (($part['operator'] === "?" || $part['operator'] === "&") && $hasQueryParam) {
                $result .= str_replace("?", "&", $expanded);
            } else {
                $result .= $expanded;
            }

            if ($part['operator'] === "?" || $part['operator'] === "&") {
                $hasQueryParam = true;
            }
        }

        return $result;
    }

    private function escapeRegExp(string $str): string
    {
        return preg_quote($str, '/');
    }

    /**
     * @param array{name: string, operator: string, names: array<string>, exploded: bool} $part
     * @return array<array{pattern: string, name: string}>
     */
    private function partToRegExp(array $part): array
    {
        $patterns = [];

        // Validate variable name length for matching
        foreach ($part['names'] as $name) {
            self::validateLength($name, self::MAX_VARIABLE_LENGTH, "Variable name");
        }

        if ($part['operator'] === "?" || $part['operator'] === "&") {
            for ($i = 0; $i < count($part['names']); $i++) {
                $name = $part['names'][$i];
                $prefix = $i === 0 ? "\\" . $part['operator'] : "&";
                $patterns[] = [
                    'pattern' => $prefix . $this->escapeRegExp($name) . "=([^&]+)",
                    'name' => $name
                ];
            }
            return $patterns;
        }

        $name = $part['name'];

        switch ($part['operator']) {
            case "":
                $pattern = $part['exploded'] ? "([^/]+(?:,[^/]+)*)" : "([^/,]+)";
                break;
            case "+":
            case "#":
                $pattern = "(.+)";
                break;
            case ".":
                $pattern = "\\.([^/,]+)";
                break;
            case "/":
                $pattern = "/" . ($part['exploded'] ? "([^/]+(?:,[^/]+)*)" : "([^/,]+)");
                break;
            default:
                $pattern = "([^/]+)";
        }

        $patterns[] = ['pattern' => $pattern, 'name' => $name];
        return $patterns;
    }

    /**
     * @return array<string, string|array<string>>|null
     */
    public function match(string $uri): ?array
    {
        self::validateLength($uri, self::MAX_TEMPLATE_LENGTH, "URI");
        $pattern = "^";
        $names = [];

        foreach ($this->parts as $part) {
            if (is_string($part)) {
                $pattern .= $this->escapeRegExp($part);
            } else {
                $patterns = $this->partToRegExp($part);
                foreach ($patterns as $p) {
                    $pattern .= $p['pattern'];
                    $names[] = ['name' => $p['name'], 'exploded' => $part['exploded']];
                }
            }
        }

        $pattern .= "$";
        self::validateLength(
            $pattern,
            self::MAX_REGEX_LENGTH,
            "Generated regex pattern"
        );

        $matches = [];
        if (!preg_match("#{$pattern}#", $uri, $matches)) {
            return null;
        }

        $result = [];
        for ($i = 0; $i < count($names); $i++) {
            $name = $names[$i]['name'];
            $exploded = $names[$i]['exploded'];
            $value = $matches[$i + 1];
            $cleanName = str_replace("*", "", $name);

            // Decode the value based on encoding
            $value = urldecode($value);

            if ($exploded && str_contains($value, ",")) {
                $result[$cleanName] = explode(",", $value);
            } else {
                $result[$cleanName] = $value;
            }
        }

        return $result;
    }
}
