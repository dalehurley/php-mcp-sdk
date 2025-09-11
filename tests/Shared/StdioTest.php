<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use MCP\Shared\ReadBuffer;
use MCP\Shared\Stdio;
use MCP\Types\JsonRpc\JSONRPCRequest;
use MCP\Types\JsonRpc\JSONRPCNotification;
use MCP\Types\JsonRpc\JSONRPCResponse;
use MCP\Types\JsonRpc\JSONRPCError;
use MCP\Types\RequestId;
use MCP\Types\Result;
use MCP\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class StdioTest extends TestCase
{
    public function testReadBufferAppend(): void
    {
        $buffer = new ReadBuffer();

        $this->assertFalse($buffer->hasData());
        $this->assertNull($buffer->getBuffer());

        $buffer->append('test data');

        $this->assertTrue($buffer->hasData());
        $this->assertEquals('test data', $buffer->getBuffer());

        $buffer->append(' more data');
        $this->assertEquals('test data more data', $buffer->getBuffer());
    }

    public function testReadBufferClear(): void
    {
        $buffer = new ReadBuffer();
        $buffer->append('test data');

        $this->assertTrue($buffer->hasData());

        $buffer->clear();

        $this->assertFalse($buffer->hasData());
        $this->assertNull($buffer->getBuffer());
    }

    public function testReadMessageComplete(): void
    {
        $buffer = new ReadBuffer();

        $jsonMessage = '{"jsonrpc":"2.0","id":1,"method":"test","params":{}}' . "\n";
        $buffer->append($jsonMessage);

        $message = $buffer->readMessage();

        $this->assertInstanceOf(JSONRPCRequest::class, $message);
        $this->assertEquals('test', $message->getMethod());
        $this->assertEquals(1, $message->getId()->jsonSerialize());

        // Buffer should be empty after reading
        $this->assertFalse($buffer->hasData());
    }

    public function testReadMessagePartial(): void
    {
        $buffer = new ReadBuffer();

        // Append partial message (no newline)
        $buffer->append('{"jsonrpc":"2.0","id":1');

        $message = $buffer->readMessage();
        $this->assertNull($message);
        $this->assertTrue($buffer->hasData());

        // Complete the message
        $buffer->append(',"method":"test","params":{}}' . "\n");

        $message = $buffer->readMessage();
        $this->assertInstanceOf(JSONRPCRequest::class, $message);
        $this->assertEquals('test', $message->getMethod());
    }

    public function testReadMessageWithCarriageReturn(): void
    {
        $buffer = new ReadBuffer();

        $jsonMessage = '{"jsonrpc":"2.0","id":1,"method":"test","params":{}}' . "\r\n";
        $buffer->append($jsonMessage);

        $message = $buffer->readMessage();

        $this->assertInstanceOf(JSONRPCRequest::class, $message);
        $this->assertEquals('test', $message->getMethod());
    }

    public function testReadMultipleMessages(): void
    {
        $buffer = new ReadBuffer();

        $messages =
            '{"jsonrpc":"2.0","id":1,"method":"test1","params":{}}' . "\n" .
            '{"jsonrpc":"2.0","id":2,"method":"test2","params":{}}' . "\n";

        $buffer->append($messages);

        $message1 = $buffer->readMessage();
        $this->assertInstanceOf(JSONRPCRequest::class, $message1);
        $this->assertEquals('test1', $message1->getMethod());

        $message2 = $buffer->readMessage();
        $this->assertInstanceOf(JSONRPCRequest::class, $message2);
        $this->assertEquals('test2', $message2->getMethod());

        // No more messages
        $message3 = $buffer->readMessage();
        $this->assertNull($message3);
    }

    public function testDeserializeMessageRequest(): void
    {
        $json = '{"jsonrpc":"2.0","id":123,"method":"test","params":{"key":"value"}}';
        $message = ReadBuffer::deserializeMessage($json);

        $this->assertInstanceOf(JSONRPCRequest::class, $message);
        $this->assertEquals('test', $message->getMethod());
        $this->assertEquals(123, $message->getId()->jsonSerialize());
        $this->assertEquals(['key' => 'value'], $message->getParams());
    }

    public function testDeserializeMessageNotification(): void
    {
        $json = '{"jsonrpc":"2.0","method":"notification","params":{"data":"test"}}';
        $message = ReadBuffer::deserializeMessage($json);

        $this->assertInstanceOf(JSONRPCNotification::class, $message);
        $this->assertEquals('notification', $message->getMethod());
        $this->assertEquals(['data' => 'test'], $message->getParams());
    }

    public function testDeserializeMessageResponse(): void
    {
        $json = '{"jsonrpc":"2.0","id":123,"result":{"success":true}}';
        $message = ReadBuffer::deserializeMessage($json);

        $this->assertInstanceOf(JSONRPCResponse::class, $message);
        $this->assertEquals(123, $message->getId()->jsonSerialize());
        $result = $message->getResult();
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(['success' => true], $result->jsonSerialize());
    }

    public function testDeserializeMessageError(): void
    {
        $json = '{"jsonrpc":"2.0","id":123,"error":{"code":-32600,"message":"Invalid Request"}}';
        $message = ReadBuffer::deserializeMessage($json);

        $this->assertInstanceOf(JSONRPCError::class, $message);
        $this->assertEquals(123, $message->getId()->jsonSerialize());
        $this->assertEquals(-32600, $message->getCode());
        $this->assertEquals('Invalid Request', $message->getMessage());
    }

    public function testDeserializeMessageInvalidJson(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid JSON');

        ReadBuffer::deserializeMessage('invalid json');
    }

    public function testDeserializeMessageNotObject(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('JSON must decode to an object');

        ReadBuffer::deserializeMessage('"string"');
    }

    public function testSerializeMessageObject(): void
    {
        $request = new JSONRPCRequest(
            RequestId::fromInt(123),
            'test',
            ['param' => 'value']
        );

        $serialized = ReadBuffer::serializeMessage($request);

        $expected = '{"jsonrpc":"2.0","id":123,"method":"test","params":{"param":"value"}}' . "\n";
        $this->assertEquals($expected, $serialized);
    }

    public function testSerializeMessageArray(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'id' => 123,
            'method' => 'test',
            'params' => ['key' => 'value']
        ];

        $serialized = ReadBuffer::serializeMessage($message);

        $expected = '{"jsonrpc":"2.0","id":123,"method":"test","params":{"key":"value"}}' . "\n";
        $this->assertEquals($expected, $serialized);
    }

    public function testSerializeMessageWithUnicode(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => ['text' => 'Hello 世界']
        ];

        $serialized = ReadBuffer::serializeMessage($message);

        $this->assertStringContainsString('Hello 世界', $serialized);
        $this->assertStringEndsWith("\n", $serialized);
    }

    public function testSerializeMessageWithSpecialCharacters(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => ['path' => '/path/to/file', 'url' => 'https://example.com']
        ];

        $serialized = ReadBuffer::serializeMessage($message);

        // Should not escape slashes
        $this->assertStringContainsString('/path/to/file', $serialized);
        $this->assertStringContainsString('https://example.com', $serialized);
    }

    public function testStdioStreamOperations(): void
    {
        // Create temporary streams for testing
        $inputData = "test input data\n";
        $inputStream = fopen('php://temp', 'r+');
        fwrite($inputStream, $inputData);
        rewind($inputStream);

        $outputStream = fopen('php://temp', 'r+');

        // Test reading
        $data = Stdio::readNonBlocking($inputStream, 1024);
        $this->assertEquals($inputData, $data);

        // Test writing
        $written = Stdio::write($outputStream, "test output\n");
        $this->assertEquals(12, $written);

        // Verify written data
        rewind($outputStream);
        $output = fread($outputStream, 1024);
        $this->assertEquals("test output\n", $output);

        fclose($inputStream);
        fclose($outputStream);
    }

    public function testStdioHasDataAvailable(): void
    {
        // Skip this test as php://temp doesn't work properly with stream_select
        $this->markTestSkipped('php://temp streams do not work correctly with stream_select');
    }

    public function testCreateReadBuffer(): void
    {
        $buffer = Stdio::createReadBuffer();
        $this->assertInstanceOf(ReadBuffer::class, $buffer);
        $this->assertFalse($buffer->hasData());
    }
}
