<?php

declare(strict_types=1);

namespace MCP\Tests\Validation;

use PHPUnit\Framework\TestCase;
use MCP\Validation\ValidationException;

class ValidationExceptionTest extends TestCase
{
    public function testBasicException(): void
    {
        $exception = new ValidationException('Validation failed');

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new ValidationException('Invalid input', [], 400);

        $this->assertEquals('Invalid input', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \InvalidArgumentException('Previous error');
        $exception = new ValidationException('Validation failed', [], 0, $previous);

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithErrors(): void
    {
        $errors = [
            'name' => ['Name is required'],
            'email' => ['Email format is invalid', 'Email is required'],
            'age' => ['Age must be a positive integer']
        ];

        $exception = new ValidationException('Multiple validation errors', $errors);

        $this->assertEquals('Multiple validation errors', $exception->getMessage());
        $this->assertEquals($errors, $exception->getErrors());
    }

    public function testGetErrors(): void
    {
        $errors = [
            'field1' => ['Error 1', 'Error 2'],
            'field2' => ['Error 3']
        ];

        $exception = new ValidationException('Test error', $errors);

        $this->assertEquals($errors, $exception->getErrors());
        $this->assertCount(2, $exception->getErrors()['field1']);
        $this->assertCount(1, $exception->getErrors()['field2']);
    }

    public function testGetErrorsWithoutErrors(): void
    {
        $exception = new ValidationException('Test error');

        $this->assertEquals([], $exception->getErrors());
    }

    public function testGetErrorMessages(): void
    {
        $errors = [
            'name' => ['Name is required', 'Name must be string'],
            'email' => ['Email is invalid']
        ];

        $exception = new ValidationException('Test', $errors);

        $messages = $exception->getErrorMessages();

        $this->assertIsArray($messages);
        $this->assertCount(3, $messages);
        $this->assertContains('name: Name is required', $messages);
        $this->assertContains('name: Name must be string', $messages);
        $this->assertContains('email: Email is invalid', $messages);
    }

    public function testChainedExceptions(): void
    {
        $root = new \RuntimeException('Root cause');
        $middle = new \InvalidArgumentException('Middle error', 0, $root);
        $validation = new ValidationException('Validation error', [], 0, $middle);

        $this->assertSame($middle, $validation->getPrevious());
        $this->assertSame($root, $validation->getPrevious()->getPrevious());
    }
}
