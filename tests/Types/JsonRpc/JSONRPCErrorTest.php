<?php

declare(strict_types=1);

namespace Tests\Types\JsonRpc;

use MCP\Types\ErrorCode;
use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\RequestId;
use PHPUnit\Framework\TestCase;

class JSONRPCErrorTest extends TestCase
{
    public function testConstructor(): void
    {
        $error = new JSONRPCError(
            id: RequestId::fromInt(1),
            code: -32600,
            message: 'Invalid Request'
        );

        $this->assertEquals(1, $error->getId()->getValue());
        $this->assertEquals(-32600, $error->getCode());
        $this->assertEquals('Invalid Request', $error->getMessage());
        $this->assertNull($error->getData());
        $this->assertEquals('2.0', $error->getJsonrpc());
    }

    public function testConstructorWithData(): void
    {
        $error = new JSONRPCError(
            id: RequestId::fromString('req-1'),
            code: -32000,
            message: 'Server error',
            data: ['details' => 'Something went wrong']
        );

        $this->assertEquals(['details' => 'Something went wrong'], $error->getData());
        $this->assertTrue($error->hasData());
    }

    public function testConstructorWithNullData(): void
    {
        $error = new JSONRPCError(
            id: RequestId::fromInt(1),
            code: -32600,
            message: 'Test'
        );

        $this->assertFalse($error->hasData());
    }

    public function testFromArray(): void
    {
        $error = JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32601,
                'message' => 'Method not found',
            ],
        ]);

        $this->assertEquals(1, $error->getId()->getValue());
        $this->assertEquals(-32601, $error->getCode());
        $this->assertEquals('Method not found', $error->getMessage());
    }

    public function testFromArrayWithStringId(): void
    {
        $error = JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 'string-id',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
        ]);

        $this->assertEquals('string-id', $error->getId()->getValue());
    }

    public function testFromArrayWithErrorData(): void
    {
        $error = JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32000,
                'message' => 'Custom error',
                'data' => ['trace' => 'stack trace'],
            ],
        ]);

        $this->assertEquals(['trace' => 'stack trace'], $error->getData());
    }

    public function testFromArrayThrowsForMissingJsonrpc(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or missing jsonrpc version');

        JSONRPCError::fromArray([
            'id' => 1,
            'error' => ['code' => -32600, 'message' => 'Test'],
        ]);
    }

    public function testFromArrayThrowsForWrongJsonrpc(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or missing jsonrpc version');

        JSONRPCError::fromArray([
            'jsonrpc' => '1.0',
            'id' => 1,
            'error' => ['code' => -32600, 'message' => 'Test'],
        ]);
    }

    public function testFromArrayThrowsForMissingId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSONRPCError must have an id property');

        JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32600, 'message' => 'Test'],
        ]);
    }

    public function testFromArrayThrowsForMissingError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSONRPCError must have an error object');

        JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
        ]);
    }

    public function testFromArrayThrowsForNonArrayError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSONRPCError must have an error object');

        JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => 'not an array',
        ]);
    }

    public function testFromArrayThrowsForMissingCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Error object must have an integer code');

        JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['message' => 'Test'],
        ]);
    }

    public function testFromArrayThrowsForNonIntegerCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Error object must have an integer code');

        JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => 'string', 'message' => 'Test'],
        ]);
    }

    public function testFromArrayThrowsForMissingMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Error object must have a string message');

        JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => -32600],
        ]);
    }

    public function testFromArrayThrowsForNonStringMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Error object must have a string message');

        JSONRPCError::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => -32600, 'message' => 123],
        ]);
    }

    public function testParseError(): void
    {
        $error = JSONRPCError::parseError(RequestId::fromInt(1));

        $this->assertEquals(ErrorCode::ParseError->value, $error->getCode());
        $this->assertEquals('Parse error', $error->getMessage());
    }

    public function testParseErrorWithCustomMessage(): void
    {
        $error = JSONRPCError::parseError(
            RequestId::fromInt(1),
            'Invalid JSON',
            ['position' => 42]
        );

        $this->assertEquals('Invalid JSON', $error->getMessage());
        $this->assertEquals(['position' => 42], $error->getData());
    }

    public function testInvalidRequest(): void
    {
        $error = JSONRPCError::invalidRequest(RequestId::fromInt(1));

        $this->assertEquals(ErrorCode::InvalidRequest->value, $error->getCode());
        $this->assertEquals('Invalid Request', $error->getMessage());
    }

    public function testInvalidRequestWithCustomMessage(): void
    {
        $error = JSONRPCError::invalidRequest(
            RequestId::fromInt(1),
            'Missing method'
        );

        $this->assertEquals('Missing method', $error->getMessage());
    }

    public function testMethodNotFound(): void
    {
        $error = JSONRPCError::methodNotFound(RequestId::fromInt(1));

        $this->assertEquals(ErrorCode::MethodNotFound->value, $error->getCode());
        $this->assertEquals('Method not found', $error->getMessage());
    }

    public function testMethodNotFoundWithCustomMessage(): void
    {
        $error = JSONRPCError::methodNotFound(
            RequestId::fromInt(1),
            'Unknown method: foo'
        );

        $this->assertEquals('Unknown method: foo', $error->getMessage());
    }

    public function testInvalidParams(): void
    {
        $error = JSONRPCError::invalidParams(RequestId::fromInt(1));

        $this->assertEquals(ErrorCode::InvalidParams->value, $error->getCode());
        $this->assertEquals('Invalid params', $error->getMessage());
    }

    public function testInvalidParamsWithCustomMessage(): void
    {
        $error = JSONRPCError::invalidParams(
            RequestId::fromInt(1),
            'Missing required parameter: name'
        );

        $this->assertEquals('Missing required parameter: name', $error->getMessage());
    }

    public function testInternalError(): void
    {
        $error = JSONRPCError::internalError(RequestId::fromInt(1));

        $this->assertEquals(ErrorCode::InternalError->value, $error->getCode());
        $this->assertEquals('Internal error', $error->getMessage());
    }

    public function testInternalErrorWithCustomMessage(): void
    {
        $error = JSONRPCError::internalError(
            RequestId::fromInt(1),
            'Database connection failed'
        );

        $this->assertEquals('Database connection failed', $error->getMessage());
    }

    public function testJsonSerialize(): void
    {
        $error = new JSONRPCError(
            id: RequestId::fromInt(1),
            code: -32600,
            message: 'Invalid Request'
        );
        $json = $error->jsonSerialize();

        $this->assertEquals('2.0', $json['jsonrpc']);
        $this->assertEquals(1, $json['id']);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals(-32600, $json['error']['code']);
        $this->assertEquals('Invalid Request', $json['error']['message']);
        $this->assertArrayNotHasKey('data', $json['error']);
    }

    public function testJsonSerializeWithData(): void
    {
        $error = new JSONRPCError(
            id: RequestId::fromInt(1),
            code: -32000,
            message: 'Error with data',
            data: ['debug' => 'info']
        );
        $json = $error->jsonSerialize();

        $this->assertArrayHasKey('data', $json['error']);
        $this->assertEquals(['debug' => 'info'], $json['error']['data']);
    }

    public function testIsValidWithValidError(): void
    {
        $valid = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
        ];

        $this->assertTrue(JSONRPCError::isValid($valid));
    }

    public function testIsValidWithStringId(): void
    {
        $valid = [
            'jsonrpc' => '2.0',
            'id' => 'string-id',
            'error' => [
                'code' => -32600,
                'message' => 'Test',
            ],
        ];

        $this->assertTrue(JSONRPCError::isValid($valid));
    }

    public function testIsValidWithData(): void
    {
        $valid = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32000,
                'message' => 'Test',
                'data' => ['extra' => 'info'],
            ],
        ];

        $this->assertTrue(JSONRPCError::isValid($valid));
    }

    public function testIsValidWithNonArrayValue(): void
    {
        $this->assertFalse(JSONRPCError::isValid('not an array'));
        $this->assertFalse(JSONRPCError::isValid(123));
        $this->assertFalse(JSONRPCError::isValid(null));
    }

    public function testIsValidWithMissingJsonrpc(): void
    {
        $invalid = [
            'id' => 1,
            'error' => ['code' => -32600, 'message' => 'Test'],
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithWrongJsonrpc(): void
    {
        $invalid = [
            'jsonrpc' => '1.0',
            'id' => 1,
            'error' => ['code' => -32600, 'message' => 'Test'],
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithMissingId(): void
    {
        $invalid = [
            'jsonrpc' => '2.0',
            'error' => ['code' => -32600, 'message' => 'Test'],
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithInvalidIdType(): void
    {
        $invalid = [
            'jsonrpc' => '2.0',
            'id' => ['not' => 'valid'],
            'error' => ['code' => -32600, 'message' => 'Test'],
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithMissingError(): void
    {
        $invalid = [
            'jsonrpc' => '2.0',
            'id' => 1,
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithNonArrayError(): void
    {
        $invalid = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => 'not an array',
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithMissingCode(): void
    {
        $invalid = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['message' => 'Test'],
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithNonIntegerCode(): void
    {
        $invalid = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => 'string', 'message' => 'Test'],
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithMissingMessage(): void
    {
        $invalid = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => -32600],
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testIsValidWithNonStringMessage(): void
    {
        $invalid = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => -32600, 'message' => 123],
        ];

        $this->assertFalse(JSONRPCError::isValid($invalid));
    }

    public function testRoundTrip(): void
    {
        $original = new JSONRPCError(
            id: RequestId::fromInt(42),
            code: -32001,
            message: 'Custom error',
            data: ['key' => 'value']
        );

        $json = $original->jsonSerialize();
        $restored = JSONRPCError::fromArray($json);

        $this->assertEquals($original->getId()->getValue(), $restored->getId()->getValue());
        $this->assertEquals($original->getCode(), $restored->getCode());
        $this->assertEquals($original->getMessage(), $restored->getMessage());
        $this->assertEquals($original->getData(), $restored->getData());
    }
}

