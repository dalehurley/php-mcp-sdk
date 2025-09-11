# PHP MCP SDK Test Suite

This directory contains the comprehensive test suite for the PHP MCP SDK.

## Running Tests

### Run all tests

```bash
php vendor/bin/phpunit
```

### Run specific test file

```bash
php vendor/bin/phpunit tests/Types/ProgressTokenTest.php
```

### Run tests for a specific directory

```bash
php vendor/bin/phpunit tests/Types/
```

### Run tests with coverage

```bash
XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-html coverage
```

## Test Organization

### Types Tests (`tests/Types/`)

Tests for all type definitions converted from TypeScript:

- **Base Types**

  - `ProgressTokenTest.php` - Tests for string|int progress tokens
  - `CursorTest.php` - Tests for pagination cursors
  - `RequestIdTest.php` - Tests for JSON-RPC request IDs

- **Request/Response Types**

  - `RequestTest.php` - Tests for base request class
  - `ResultTest.php` - Tests for base result class
  - `EmptyResultTest.php` - Tests for empty results

- **JSON-RPC Types** (`tests/Types/JsonRpc/`)

  - `JSONRPCRequestTest.php` - Tests for JSON-RPC requests
  - Additional JSON-RPC message type tests

- **Other Types**
  - `LoggingLevelTest.php` - Tests for logging level enum
  - `ImplementationTest.php` - Tests for implementation info

## Test Coverage

Current test coverage includes:

1. **Object Creation** - Testing constructors and factory methods
2. **Serialization** - JSON encoding/decoding
3. **Validation** - Input validation and error handling
4. **Type Safety** - Union types, enums, type checking
5. **Edge Cases** - Null values, empty strings, invalid data

## Writing New Tests

When adding new tests, follow these guidelines:

1. **Naming Convention**: Test classes should match the class they test with `Test` suffix
2. **Method Names**: Test methods should describe what they test (e.g., `testCreateFromString`)
3. **Assertions**: Use specific assertions (`assertSame`, `assertInstanceOf`, etc.)
4. **Edge Cases**: Always test error conditions and edge cases
5. **Data Providers**: Use data providers for testing multiple similar cases

Example test structure:

```php
class MyTypeTest extends TestCase
{
    public function testCreateInstance(): void
    {
        // Test object creation
    }

    public function testFromArray(): void
    {
        // Test factory method
    }

    public function testJsonSerialize(): void
    {
        // Test JSON serialization
    }

    public function testValidation(): void
    {
        // Test validation rules
    }

    public function testEdgeCases(): void
    {
        // Test error conditions
    }
}
```

## Continuous Integration

Tests are automatically run on:

- Every commit
- Pull requests
- Before releases

Ensure all tests pass before submitting PRs.
