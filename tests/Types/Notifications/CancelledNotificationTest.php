<?php

declare(strict_types=1);

namespace MCP\Tests\Types\Notifications;

use MCP\Types\Notifications\CancelledNotification;
use MCP\Types\RequestId;
use PHPUnit\Framework\TestCase;

/**
 * Test class for CancelledNotification.
 */
class CancelledNotificationTest extends TestCase
{
    /**
     * Test create method with reason.
     */
    public function testCreateWithReason(): void
    {
        $requestId = RequestId::fromString('req-123');
        $notification = CancelledNotification::create($requestId, 'User cancelled');

        $this->assertEquals('notifications/cancelled', $notification->getMethod());

        $id = $notification->getRequestId();
        $this->assertNotNull($id);
        $this->assertEquals('req-123', $id->getValue());

        $this->assertEquals('User cancelled', $notification->getReason());
    }

    /**
     * Test create method without reason.
     */
    public function testCreateWithoutReason(): void
    {
        $requestId = RequestId::fromInt(456);
        $notification = CancelledNotification::create($requestId);

        $id = $notification->getRequestId();
        $this->assertNotNull($id);
        $this->assertEquals(456, $id->getValue());

        $this->assertNull($notification->getReason());
    }

    /**
     * Test getRequestId method.
     */
    public function testGetRequestId(): void
    {
        // String ID
        $notification = new CancelledNotification([
            'requestId' => 'string-id',
        ]);

        $id = $notification->getRequestId();
        $this->assertNotNull($id);
        $this->assertTrue($id->isString());
        $this->assertEquals('string-id', $id->getValue());

        // Integer ID
        $notification = new CancelledNotification([
            'requestId' => 789,
        ]);

        $id = $notification->getRequestId();
        $this->assertNotNull($id);
        $this->assertTrue($id->isInt());
        $this->assertEquals(789, $id->getValue());

        // No params
        $notification = new CancelledNotification();
        $this->assertNull($notification->getRequestId());
    }

    /**
     * Test getReason method.
     */
    public function testGetReason(): void
    {
        $notification = new CancelledNotification([
            'requestId' => 'id',
            'reason' => 'Timeout exceeded',
        ]);

        $this->assertEquals('Timeout exceeded', $notification->getReason());

        // No reason
        $notification = new CancelledNotification([
            'requestId' => 'id',
        ]);

        $this->assertNull($notification->getReason());
    }

    /**
     * Test isValid method.
     */
    public function testIsValid(): void
    {
        // Valid notification
        $this->assertTrue(CancelledNotification::isValid([
            'method' => 'notifications/cancelled',
            'params' => ['requestId' => 'req-123'],
        ]));

        // Valid with reason
        $this->assertTrue(CancelledNotification::isValid([
            'method' => 'notifications/cancelled',
            'params' => [
                'requestId' => 456,
                'reason' => 'Cancelled by user',
            ],
        ]));

        // Wrong method
        $this->assertFalse(CancelledNotification::isValid([
            'method' => 'notifications/other',
            'params' => ['requestId' => 'req-123'],
        ]));

        // Missing requestId
        $this->assertFalse(CancelledNotification::isValid([
            'method' => 'notifications/cancelled',
            'params' => ['reason' => 'No ID'],
        ]));

        // No params
        $this->assertFalse(CancelledNotification::isValid([
            'method' => 'notifications/cancelled',
        ]));
    }

    /**
     * Test JSON serialization.
     */
    public function testJsonSerialization(): void
    {
        $requestId = RequestId::fromString('json-req');
        $notification = CancelledNotification::create($requestId, 'Test reason');

        $json = $notification->jsonSerialize();

        $this->assertEquals('notifications/cancelled', $json['method']);
        $this->assertArrayHasKey('params', $json);
        $this->assertEquals('json-req', $json['params']['requestId']);
        $this->assertEquals('Test reason', $json['params']['reason']);
    }

    /**
     * Test JSON serialization without reason.
     */
    public function testJsonSerializationWithoutReason(): void
    {
        $requestId = RequestId::fromInt(999);
        $notification = CancelledNotification::create($requestId);

        $json = $notification->jsonSerialize();

        $this->assertEquals(999, $json['params']['requestId']);
        $this->assertArrayNotHasKey('reason', $json['params']);
    }
}
