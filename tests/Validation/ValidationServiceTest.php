<?php

declare(strict_types=1);

namespace MCP\Tests\Validation;

use PHPUnit\Framework\TestCase;
use MCP\Validation\ValidationService;
use MCP\Validation\ValidationException;

class ValidationServiceTest extends TestCase
{
    private ValidationService $validator;

    protected function setUp(): void
    {
        $this->validator = new ValidationService();
    }

    public function testValidateProgressToken(): void
    {
        $result = $this->validator->validateProgressToken('task-123');
        $this->assertEquals('task-123', $result);

        $result = $this->validator->validateProgressToken(456);
        $this->assertEquals(456, $result);
    }

    public function testValidateRequestId(): void
    {
        $result = $this->validator->validateRequestId('request-123');
        $this->assertEquals('request-123', $result);

        $result = $this->validator->validateRequestId(789);
        $this->assertEquals(789, $result);
    }

    public function testValidateCursor(): void
    {
        $result = $this->validator->validateCursor('cursor-value');
        $this->assertEquals('cursor-value', $result);
    }

    public function testValidateJSONRPCRequest(): void
    {
        $validRequest = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'test/method',
            'params' => ['key' => 'value']
        ];

        $result = $this->validator->validateJSONRPCRequest($validRequest);
        $this->assertIsArray($result);
        $this->assertEquals('2.0', $result['jsonrpc']);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('test/method', $result['method']);
    }

    public function testIsValid(): void
    {
        $this->assertTrue($this->validator->isValid('progressToken', 'task-123'));
        $this->assertTrue($this->validator->isValid('requestId', 456));
        $this->assertTrue($this->validator->isValid('cursor', 'cursor-value'));

        // Test with invalid type
        $this->assertFalse($this->validator->isValid('nonexistent-type', 'value'));
    }

    public function testValidate(): void
    {
        $result = $this->validator->validate('progressToken', 'task-123');
        $this->assertIsArray($result);
    }

    public function testGetValidator(): void
    {
        $validator = $this->validator->getValidator('progressToken');
        $this->assertInstanceOf(\MCP\Validation\TypeValidator::class, $validator);
    }

    public function testGetValidatorThrowsForUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No validator registered for type: unknown-type');

        $this->validator->getValidator('unknown-type');
    }

    public function testRegisterValidator(): void
    {
        $customValidator = $this->createMock(\MCP\Validation\TypeValidator::class);
        $customValidator->method('validate')->willReturn(['validated' => true]);
        $customValidator->method('isValid')->willReturn(true);

        $this->validator->registerValidator('custom', $customValidator);

        $result = $this->validator->validate('custom', 'test-data');
        $this->assertEquals(['validated' => true], $result);

        $this->assertTrue($this->validator->isValid('custom', 'test-data'));
    }

    public function testValidationExceptionOnInvalidData(): void
    {
        $this->expectException(ValidationException::class);

        // Try to validate invalid JSON-RPC (missing required fields)
        $this->validator->validateJSONRPCRequest(['invalid' => 'data']);
    }

    public function testValidationExceptionOnInvalidProgressToken(): void
    {
        $this->expectException(ValidationException::class);

        // Try to validate invalid progress token (empty array)
        $this->validator->validateProgressToken([]);
    }

    public function testValidationExceptionOnInvalidCursor(): void
    {
        $this->expectException(ValidationException::class);

        // Try to validate invalid cursor (number instead of string)
        $this->validator->validateCursor(123);
    }
}
