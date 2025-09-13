<?php

declare(strict_types=1);

namespace MCP\Tests\Validation\Types;

use MCP\Validation\Types\CreateMessageResultValidator;
use MCP\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class CreateMessageResultValidatorTest extends TestCase
{
    private CreateMessageResultValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CreateMessageResultValidator();
    }

    public function testValidCreateMessageResult(): void
    {
        $data = [
            'model' => 'gpt-4',
            'role' => 'assistant',
            'content' => [
                'type' => 'text',
                'text' => 'Hello, world!'
            ]
        ];

        $result = $this->validator->validate($data);
        $this->assertEquals($data, $result);
        $this->assertTrue($this->validator->isValid($data));
    }

    public function testValidCreateMessageResultWithImage(): void
    {
        $data = [
            'model' => 'gpt-4-vision',
            'role' => 'assistant',
            'content' => [
                'type' => 'image',
                'data' => 'base64encodeddata',
                'mimeType' => 'image/png'
            ]
        ];

        $result = $this->validator->validate($data);
        $this->assertEquals($data, $result);
        $this->assertTrue($this->validator->isValid($data));
    }

    public function testInvalidCreateMessageResultMissingModel(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => [
                'type' => 'text',
                'text' => 'Hello, world!'
            ]
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($data);
        $this->assertFalse($this->validator->isValid($data));
    }

    public function testInvalidCreateMessageResultInvalidContentType(): void
    {
        $data = [
            'model' => 'gpt-4',
            'role' => 'assistant',
            'content' => [
                'type' => 'invalid',
                'text' => 'Hello, world!'
            ]
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($data);
        $this->assertFalse($this->validator->isValid($data));
    }
}
