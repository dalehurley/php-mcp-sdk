<?php

declare(strict_types=1);

namespace Tests\Types\Notifications;

use MCP\Types\LoggingLevel;
use MCP\Types\Notifications\LoggingMessageNotification;
use PHPUnit\Framework\TestCase;

class LoggingMessageNotificationTest extends TestCase
{
    public function testMethodConstant(): void
    {
        $this->assertEquals('notifications/message', LoggingMessageNotification::METHOD);
    }

    public function testConstructor(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 'info',
            'data' => 'Test message',
        ]);

        $this->assertEquals(LoggingMessageNotification::METHOD, $notification->getMethod());
    }

    public function testConstructorWithNullParams(): void
    {
        $notification = new LoggingMessageNotification(null);

        $this->assertNull($notification->getLevel());
        $this->assertNull($notification->getData());
        $this->assertNull($notification->getLogger());
    }

    public function testCreate(): void
    {
        $notification = LoggingMessageNotification::create(
            LoggingLevel::Info,
            'Log message'
        );

        $this->assertEquals(LoggingLevel::Info, $notification->getLevel());
        $this->assertEquals('Log message', $notification->getData());
        $this->assertNull($notification->getLogger());
    }

    public function testCreateWithLogger(): void
    {
        $notification = LoggingMessageNotification::create(
            LoggingLevel::Warning,
            'Warning message',
            'MyApp'
        );

        $this->assertEquals(LoggingLevel::Warning, $notification->getLevel());
        $this->assertEquals('Warning message', $notification->getData());
        $this->assertEquals('MyApp', $notification->getLogger());
    }

    public function testCreateWithDifferentLevels(): void
    {
        $levels = [
            LoggingLevel::Debug,
            LoggingLevel::Info,
            LoggingLevel::Notice,
            LoggingLevel::Warning,
            LoggingLevel::Error,
            LoggingLevel::Critical,
            LoggingLevel::Alert,
            LoggingLevel::Emergency,
        ];

        foreach ($levels as $level) {
            $notification = LoggingMessageNotification::create($level, 'Test');
            $this->assertEquals($level, $notification->getLevel());
        }
    }

    public function testCreateWithComplexData(): void
    {
        $complexData = [
            'message' => 'Error occurred',
            'context' => [
                'user_id' => 123,
                'action' => 'save',
            ],
            'trace' => ['file1.php', 'file2.php'],
        ];

        $notification = LoggingMessageNotification::create(
            LoggingLevel::Error,
            $complexData
        );

        $this->assertEquals($complexData, $notification->getData());
    }

    public function testGetLevel(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 'error',
            'data' => 'Error message',
        ]);

        $this->assertEquals(LoggingLevel::Error, $notification->getLevel());
    }

    public function testGetLevelWithInvalidLevel(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 'invalid_level',
            'data' => 'test',
        ]);

        $this->assertNull($notification->getLevel());
    }

    public function testGetLevelWithNonStringLevel(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 123,
            'data' => 'test',
        ]);

        $this->assertNull($notification->getLevel());
    }

    public function testGetLevelWithNoParams(): void
    {
        $notification = new LoggingMessageNotification([]);

        $this->assertNull($notification->getLevel());
    }

    public function testGetLogger(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 'info',
            'data' => 'test',
            'logger' => 'MyLogger',
        ]);

        $this->assertEquals('MyLogger', $notification->getLogger());
    }

    public function testGetLoggerWithNoLogger(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 'info',
            'data' => 'test',
        ]);

        $this->assertNull($notification->getLogger());
    }

    public function testGetLoggerWithNonStringLogger(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 'info',
            'data' => 'test',
            'logger' => 123,
        ]);

        $this->assertNull($notification->getLogger());
    }

    public function testGetData(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 'info',
            'data' => 'Test data',
        ]);

        $this->assertEquals('Test data', $notification->getData());
    }

    public function testGetDataWithNoData(): void
    {
        $notification = new LoggingMessageNotification([
            'level' => 'info',
        ]);

        $this->assertNull($notification->getData());
    }

    public function testGetDataWithDifferentTypes(): void
    {
        // String data
        $notification1 = new LoggingMessageNotification([
            'level' => 'info',
            'data' => 'string data',
        ]);
        $this->assertEquals('string data', $notification1->getData());

        // Array data
        $notification2 = new LoggingMessageNotification([
            'level' => 'info',
            'data' => ['key' => 'value'],
        ]);
        $this->assertEquals(['key' => 'value'], $notification2->getData());

        // Integer data
        $notification3 = new LoggingMessageNotification([
            'level' => 'info',
            'data' => 42,
        ]);
        $this->assertEquals(42, $notification3->getData());

        // Null data
        $notification4 = new LoggingMessageNotification([
            'level' => 'info',
            'data' => null,
        ]);
        $this->assertNull($notification4->getData());
    }

    public function testIsValidWithValidNotification(): void
    {
        $valid = [
            'method' => 'notifications/message',
            'params' => [
                'level' => 'info',
                'data' => 'test message',
            ],
        ];

        $this->assertTrue(LoggingMessageNotification::isValid($valid));
    }

    public function testIsValidWithLogger(): void
    {
        $valid = [
            'method' => 'notifications/message',
            'params' => [
                'level' => 'warning',
                'data' => 'test',
                'logger' => 'MyApp',
            ],
        ];

        $this->assertTrue(LoggingMessageNotification::isValid($valid));
    }

    public function testIsValidWithWrongMethod(): void
    {
        $invalid = [
            'method' => 'wrong/method',
            'params' => [
                'level' => 'info',
                'data' => 'test',
            ],
        ];

        $this->assertFalse(LoggingMessageNotification::isValid($invalid));
    }

    public function testIsValidWithMissingParams(): void
    {
        $invalid = [
            'method' => 'notifications/message',
        ];

        $this->assertFalse(LoggingMessageNotification::isValid($invalid));
    }

    public function testIsValidWithNullParams(): void
    {
        $invalid = [
            'method' => 'notifications/message',
            'params' => null,
        ];

        $this->assertFalse(LoggingMessageNotification::isValid($invalid));
    }

    public function testIsValidWithMissingLevel(): void
    {
        $invalid = [
            'method' => 'notifications/message',
            'params' => [
                'data' => 'test',
            ],
        ];

        $this->assertFalse(LoggingMessageNotification::isValid($invalid));
    }

    public function testIsValidWithMissingData(): void
    {
        $invalid = [
            'method' => 'notifications/message',
            'params' => [
                'level' => 'info',
            ],
        ];

        $this->assertFalse(LoggingMessageNotification::isValid($invalid));
    }

    public function testIsValidWithNonArrayValue(): void
    {
        $this->assertFalse(LoggingMessageNotification::isValid('not an array'));
        $this->assertFalse(LoggingMessageNotification::isValid(123));
        $this->assertFalse(LoggingMessageNotification::isValid(null));
    }

    public function testJsonSerialize(): void
    {
        $notification = LoggingMessageNotification::create(
            LoggingLevel::Info,
            'Test message',
            'TestLogger'
        );
        $json = $notification->jsonSerialize();

        $this->assertEquals('notifications/message', $json['method']);
        $this->assertArrayHasKey('params', $json);
        $this->assertEquals('info', $json['params']['level']);
        $this->assertEquals('Test message', $json['params']['data']);
        $this->assertEquals('TestLogger', $json['params']['logger']);
    }
}

