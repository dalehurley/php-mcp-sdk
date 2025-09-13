<?php

declare(strict_types=1);

namespace MCP\Client\Validation;

/**
 * A compiled JSON schema validator that provides optimized validation
 * for specific schemas by pre-compiling validation rules.
 */
class CompiledValidator implements CompiledValidatorInterface
{
    private array $errors = [];
    private array $validationRules = [];
    private bool $isValid = true;

    public function __construct(
        private readonly array $schema,
        private readonly string $schemaHash
    ) {
    }

    public function validate(mixed $data): bool
    {
        $this->errors = [];

        try {
            // Execute pre-compiled validation rules in order
            foreach ($this->validationRules as $rule) {
                if (!$this->executeRule($rule, $data)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            $this->errors[] = "Validation error: {$e->getMessage()}";
            return false;
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getSchemaHash(): string
    {
        return $this->schemaHash;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Add a type validation rule.
     */
    public function addTypeCheck(string $expectedType): void
    {
        $this->validationRules[] = [
            'type' => 'type_check',
            'expected_type' => $expectedType
        ];
    }

    /**
     * Add a required properties validation rule.
     */
    public function addRequiredCheck(array $requiredProperties): void
    {
        $this->validationRules[] = [
            'type' => 'required_check',
            'required' => $requiredProperties
        ];
    }

    /**
     * Add a property validation rule.
     */
    public function addPropertyCheck(string $property, array $propertySchema): void
    {
        $this->validationRules[] = [
            'type' => 'property_check',
            'property' => $property,
            'schema' => $propertySchema
        ];
    }

    /**
     * Add an array items validation rule.
     */
    public function addItemsCheck(array $itemsSchema): void
    {
        $this->validationRules[] = [
            'type' => 'items_check',
            'items_schema' => $itemsSchema
        ];
    }

    /**
     * Add an enum validation rule.
     */
    public function addEnumCheck(array $allowedValues): void
    {
        $this->validationRules[] = [
            'type' => 'enum_check',
            'allowed_values' => $allowedValues
        ];
    }

    /**
     * Add a pattern validation rule.
     */
    public function addPatternCheck(string $pattern): void
    {
        $this->validationRules[] = [
            'type' => 'pattern_check',
            'pattern' => $pattern
        ];
    }

    /**
     * Add a minimum value validation rule.
     */
    public function addMinimumCheck(float $minimum): void
    {
        $this->validationRules[] = [
            'type' => 'minimum_check',
            'minimum' => $minimum
        ];
    }

    /**
     * Add a maximum value validation rule.
     */
    public function addMaximumCheck(float $maximum): void
    {
        $this->validationRules[] = [
            'type' => 'maximum_check',
            'maximum' => $maximum
        ];
    }

    /**
     * Add a minimum length validation rule.
     */
    public function addMinLengthCheck(int $minLength): void
    {
        $this->validationRules[] = [
            'type' => 'min_length_check',
            'min_length' => $minLength
        ];
    }

    /**
     * Add a maximum length validation rule.
     */
    public function addMaxLengthCheck(int $maxLength): void
    {
        $this->validationRules[] = [
            'type' => 'max_length_check',
            'max_length' => $maxLength
        ];
    }

    /**
     * Execute a validation rule against data.
     */
    private function executeRule(array $rule, mixed $data): bool
    {
        switch ($rule['type']) {
            case 'type_check':
                return $this->validateType($data, $rule['expected_type']);

            case 'required_check':
                return $this->validateRequired($data, $rule['required']);

            case 'property_check':
                return $this->validateProperty($data, $rule['property'], $rule['schema']);

            case 'items_check':
                return $this->validateItems($data, $rule['items_schema']);

            case 'enum_check':
                return $this->validateEnum($data, $rule['allowed_values']);

            case 'pattern_check':
                return $this->validatePattern($data, $rule['pattern']);

            case 'minimum_check':
                return $this->validateMinimum($data, $rule['minimum']);

            case 'maximum_check':
                return $this->validateMaximum($data, $rule['maximum']);

            case 'min_length_check':
                return $this->validateMinLength($data, $rule['min_length']);

            case 'max_length_check':
                return $this->validateMaxLength($data, $rule['max_length']);

            default:
                $this->errors[] = "Unknown validation rule: {$rule['type']}";
                return false;
        }
    }

    private function validateType(mixed $data, string $expectedType): bool
    {
        $actualType = $this->getJsonType($data);

        if ($actualType !== $expectedType) {
            $this->errors[] = "Expected type '{$expectedType}', got '{$actualType}'";
            return false;
        }

        return true;
    }

    private function validateRequired(mixed $data, array $required): bool
    {
        if (!is_array($data) && !is_object($data)) {
            $this->errors[] = 'Required properties check requires object or array';
            return false;
        }

        $data = (array)$data;

        foreach ($required as $property) {
            if (!array_key_exists($property, $data)) {
                $this->errors[] = "Required property '{$property}' is missing";
                return false;
            }
        }

        return true;
    }

    private function validateProperty(mixed $data, string $property, array $schema): bool
    {
        if (!is_array($data) && !is_object($data)) {
            return true; // Skip property validation for non-objects
        }

        $data = (array)$data;

        if (!array_key_exists($property, $data)) {
            return true; // Property is optional if not in required list
        }

        // For now, do basic type checking on the property value
        if (isset($schema['type'])) {
            $propertyValue = $data[$property];
            $actualType = $this->getJsonType($propertyValue);

            if ($actualType !== $schema['type']) {
                $this->errors[] = "Property '{$property}' expected type '{$schema['type']}', got '{$actualType}'";
                return false;
            }
        }

        return true;
    }

    private function validateItems(mixed $data, array $itemsSchema): bool
    {
        if (!is_array($data)) {
            $this->errors[] = 'Items validation requires array data';
            return false;
        }

        foreach ($data as $index => $item) {
            if (isset($itemsSchema['type'])) {
                $actualType = $this->getJsonType($item);
                if ($actualType !== $itemsSchema['type']) {
                    $this->errors[] = "Array item at index {$index} expected type '{$itemsSchema['type']}', got '{$actualType}'";
                    return false;
                }
            }
        }

        return true;
    }

    private function validateEnum(mixed $data, array $allowedValues): bool
    {
        if (!in_array($data, $allowedValues, true)) {
            $allowed = implode(', ', array_map('json_encode', $allowedValues));
            $actual = json_encode($data);
            $this->errors[] = "Value {$actual} is not one of allowed values: {$allowed}";
            return false;
        }

        return true;
    }

    private function validatePattern(mixed $data, string $pattern): bool
    {
        if (!is_string($data)) {
            $this->errors[] = 'Pattern validation requires string data';
            return false;
        }

        if (!preg_match("/{$pattern}/", $data)) {
            $this->errors[] = "String does not match pattern: {$pattern}";
            return false;
        }

        return true;
    }

    private function validateMinimum(mixed $data, float $minimum): bool
    {
        if (!is_numeric($data)) {
            $this->errors[] = 'Minimum validation requires numeric data';
            return false;
        }

        if ((float)$data < $minimum) {
            $this->errors[] = "Value {$data} is less than minimum {$minimum}";
            return false;
        }

        return true;
    }

    private function validateMaximum(mixed $data, float $maximum): bool
    {
        if (!is_numeric($data)) {
            $this->errors[] = 'Maximum validation requires numeric data';
            return false;
        }

        if ((float)$data > $maximum) {
            $this->errors[] = "Value {$data} is greater than maximum {$maximum}";
            return false;
        }

        return true;
    }

    private function validateMinLength(mixed $data, int $minLength): bool
    {
        if (is_string($data)) {
            $length = mb_strlen($data);
        } elseif (is_array($data)) {
            $length = count($data);
        } else {
            $this->errors[] = 'MinLength validation requires string or array data';
            return false;
        }

        if ($length < $minLength) {
            $this->errors[] = "Length {$length} is less than minimum length {$minLength}";
            return false;
        }

        return true;
    }

    private function validateMaxLength(mixed $data, int $maxLength): bool
    {
        if (is_string($data)) {
            $length = mb_strlen($data);
        } elseif (is_array($data)) {
            $length = count($data);
        } else {
            $this->errors[] = 'MaxLength validation requires string or array data';
            return false;
        }

        if ($length > $maxLength) {
            $this->errors[] = "Length {$length} is greater than maximum length {$maxLength}";
            return false;
        }

        return true;
    }

    /**
     * Get the JSON schema type of a PHP value.
     */
    private function getJsonType(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) => array_is_list($value) ? 'array' : 'object',
            is_object($value) => 'object',
            default => 'unknown'
        };
    }
}
