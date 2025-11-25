<?php

declare(strict_types=1);

namespace Tests\Types\Notifications;

use MCP\Types\Notifications\ResourceUpdatedNotification;
use PHPUnit\Framework\TestCase;

class ResourceUpdatedNotificationTest extends TestCase
{
    public function testMethodConstant(): void
    {
        $this->assertEquals('notifications/resources/updated', ResourceUpdatedNotification::METHOD);
    }

    public function testConstructor(): void
    {
        $notification = new ResourceUpdatedNotification([
            'uri' => 'file:///path/to/resource',
        ]);

        $this->assertEquals(ResourceUpdatedNotification::METHOD, $notification->getMethod());
    }

    public function testConstructorWithNullParams(): void
    {
        $notification = new ResourceUpdatedNotification(null);

        $this->assertNull($notification->getUri());
    }

    public function testCreate(): void
    {
        $notification = ResourceUpdatedNotification::create('file:///test.txt');

        $this->assertEquals('file:///test.txt', $notification->getUri());
        $this->assertEquals(ResourceUpdatedNotification::METHOD, $notification->getMethod());
    }

    public function testCreateWithDifferentUris(): void
    {
        $uris = [
            'file:///path/to/file.txt',
            'https://example.com/resource',
            'custom://my-resource',
            'data:text/plain,Hello',
        ];

        foreach ($uris as $uri) {
            $notification = ResourceUpdatedNotification::create($uri);
            $this->assertEquals($uri, $notification->getUri());
        }
    }

    public function testGetUri(): void
    {
        $notification = new ResourceUpdatedNotification([
            'uri' => 'file:///specific/resource',
        ]);

        $this->assertEquals('file:///specific/resource', $notification->getUri());
    }

    public function testGetUriWithNoParams(): void
    {
        $notification = new ResourceUpdatedNotification([]);

        $this->assertNull($notification->getUri());
    }

    public function testGetUriWithNullParams(): void
    {
        $notification = new ResourceUpdatedNotification(null);

        $this->assertNull($notification->getUri());
    }

    public function testGetUriWithNonStringUri(): void
    {
        $notification = new ResourceUpdatedNotification([
            'uri' => 123,
        ]);

        $this->assertNull($notification->getUri());
    }

    public function testGetUriWithArrayUri(): void
    {
        $notification = new ResourceUpdatedNotification([
            'uri' => ['not', 'a', 'string'],
        ]);

        $this->assertNull($notification->getUri());
    }

    public function testIsValidWithValidNotification(): void
    {
        $valid = [
            'method' => 'notifications/resources/updated',
            'params' => [
                'uri' => 'file:///path/to/resource',
            ],
        ];

        $this->assertTrue(ResourceUpdatedNotification::isValid($valid));
    }

    public function testIsValidWithWrongMethod(): void
    {
        $invalid = [
            'method' => 'wrong/method',
            'params' => [
                'uri' => 'file:///test',
            ],
        ];

        $this->assertFalse(ResourceUpdatedNotification::isValid($invalid));
    }

    public function testIsValidWithMissingParams(): void
    {
        $invalid = [
            'method' => 'notifications/resources/updated',
        ];

        $this->assertFalse(ResourceUpdatedNotification::isValid($invalid));
    }

    public function testIsValidWithNullParams(): void
    {
        $invalid = [
            'method' => 'notifications/resources/updated',
            'params' => null,
        ];

        $this->assertFalse(ResourceUpdatedNotification::isValid($invalid));
    }

    public function testIsValidWithMissingUri(): void
    {
        $invalid = [
            'method' => 'notifications/resources/updated',
            'params' => [],
        ];

        $this->assertFalse(ResourceUpdatedNotification::isValid($invalid));
    }

    public function testIsValidWithNonArrayValue(): void
    {
        $this->assertFalse(ResourceUpdatedNotification::isValid('not an array'));
        $this->assertFalse(ResourceUpdatedNotification::isValid(123));
        $this->assertFalse(ResourceUpdatedNotification::isValid(null));
    }

    public function testJsonSerialize(): void
    {
        $notification = ResourceUpdatedNotification::create('file:///updated/resource');
        $json = $notification->jsonSerialize();

        $this->assertEquals('notifications/resources/updated', $json['method']);
        $this->assertArrayHasKey('params', $json);
        $this->assertEquals('file:///updated/resource', $json['params']['uri']);
    }

    public function testUriWithSpecialCharacters(): void
    {
        $uri = 'file:///path/to/file%20with%20spaces.txt';
        $notification = ResourceUpdatedNotification::create($uri);

        $this->assertEquals($uri, $notification->getUri());
    }

    public function testUriWithQueryString(): void
    {
        $uri = 'https://api.example.com/resource?version=2&format=json';
        $notification = ResourceUpdatedNotification::create($uri);

        $this->assertEquals($uri, $notification->getUri());
    }

    public function testUriWithFragment(): void
    {
        $uri = 'https://docs.example.com/page#section';
        $notification = ResourceUpdatedNotification::create($uri);

        $this->assertEquals($uri, $notification->getUri());
    }
}

