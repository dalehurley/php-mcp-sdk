<?php

declare(strict_types=1);

namespace MCP\Tests\Types\Notifications;

use MCP\Types\Notifications\ProgressNotification;
use MCP\Types\Progress;
use MCP\Types\ProgressToken;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ProgressNotification.
 */
class ProgressNotificationTest extends TestCase
{
    /**
     * Test create method.
     */
    public function testCreate(): void
    {
        $token = ProgressToken::fromString('task-123');
        $progress = new Progress(50.0, 100.0, 'Processing...');
        
        $notification = ProgressNotification::create($token, $progress);
        
        $this->assertEquals('notifications/progress', $notification->getMethod());
        $this->assertNotNull($notification->getProgressToken());
        $this->assertEquals('task-123', $notification->getProgressToken()->getValue());
        
        $progressData = $notification->getProgress();
        $this->assertNotNull($progressData);
        $this->assertEquals(50.0, $progressData->getProgress());
        $this->assertEquals(100.0, $progressData->getTotal());
        $this->assertEquals('Processing...', $progressData->getMessage());
    }

    /**
     * Test getProgressToken method.
     */
    public function testGetProgressToken(): void
    {
        // String token
        $notification = new ProgressNotification([
            'progressToken' => 'string-token',
            'progress' => 10.0
        ]);
        
        $token = $notification->getProgressToken();
        $this->assertNotNull($token);
        $this->assertTrue($token->isString());
        $this->assertEquals('string-token', $token->getValue());
        
        // Integer token
        $notification = new ProgressNotification([
            'progressToken' => 456,
            'progress' => 20.0
        ]);
        
        $token = $notification->getProgressToken();
        $this->assertNotNull($token);
        $this->assertTrue($token->isInt());
        $this->assertEquals(456, $token->getValue());
        
        // No token
        $notification = new ProgressNotification();
        $this->assertNull($notification->getProgressToken());
    }

    /**
     * Test getProgress method.
     */
    public function testGetProgress(): void
    {
        $notification = new ProgressNotification([
            'progressToken' => 'token',
            'progress' => 75.0,
            'total' => 150.0,
            'message' => 'Almost done',
            'stage' => 'finalization'
        ]);
        
        $progress = $notification->getProgress();
        $this->assertNotNull($progress);
        $this->assertEquals(75.0, $progress->getProgress());
        $this->assertEquals(150.0, $progress->getTotal());
        $this->assertEquals('Almost done', $progress->getMessage());
        
        // Additional fields should be preserved
        $json = $progress->jsonSerialize();
        $this->assertEquals('finalization', $json['stage']);
    }

    /**
     * Test isValid method.
     */
    public function testIsValid(): void
    {
        // Valid notification
        $this->assertTrue(ProgressNotification::isValid([
            'method' => 'notifications/progress',
            'params' => [
                'progressToken' => 'token',
                'progress' => 50.0
            ]
        ]));
        
        // Wrong method
        $this->assertFalse(ProgressNotification::isValid([
            'method' => 'other/method',
            'params' => [
                'progressToken' => 'token',
                'progress' => 50.0
            ]
        ]));
        
        // Missing progressToken
        $this->assertFalse(ProgressNotification::isValid([
            'method' => 'notifications/progress',
            'params' => [
                'progress' => 50.0
            ]
        ]));
        
        // Missing progress
        $this->assertFalse(ProgressNotification::isValid([
            'method' => 'notifications/progress',
            'params' => [
                'progressToken' => 'token'
            ]
        ]));
        
        // No params
        $this->assertFalse(ProgressNotification::isValid([
            'method' => 'notifications/progress'
        ]));
    }

    /**
     * Test JSON serialization.
     */
    public function testJsonSerialization(): void
    {
        $token = ProgressToken::fromInt(789);
        $progress = new Progress(25.0, 50.0);
        
        $notification = ProgressNotification::create($token, $progress);
        $json = $notification->jsonSerialize();
        
        $this->assertEquals('notifications/progress', $json['method']);
        $this->assertArrayHasKey('params', $json);
        $this->assertEquals(789, $json['params']['progressToken']);
        $this->assertEquals(25.0, $json['params']['progress']);
        $this->assertEquals(50.0, $json['params']['total']);
    }
}
