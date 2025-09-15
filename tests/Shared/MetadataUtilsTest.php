<?php

declare(strict_types=1);

namespace MCP\Tests\Shared;

use MCP\Shared\MetadataUtils;
use MCP\Types\BaseMetadata;
use PHPUnit\Framework\TestCase;

class MetadataUtilsTest extends TestCase
{
    private function createMockMetadata(
        string $name,
        ?string $title = null,
        array $additionalProperties = []
    ): BaseMetadata {
        return new class ($name, $title, null, $additionalProperties) extends BaseMetadata {
            public function __construct(
                string $name,
                ?string $title = null,
                ?array $_meta = null,
                array $additionalProperties = []
            ) {
                parent::__construct($name, $title, $_meta, $additionalProperties);
            }
        };
    }

    public function testGetDisplayNameFromArray(): void
    {
        // Test with title
        $metadata = ['name' => 'test-name', 'title' => 'Test Title'];
        $this->assertEquals('Test Title', MetadataUtils::getDisplayName($metadata));

        // Test without title
        $metadata = ['name' => 'test-name'];
        $this->assertEquals('test-name', MetadataUtils::getDisplayName($metadata));

        // Test with empty title
        $metadata = ['name' => 'test-name', 'title' => ''];
        $this->assertEquals('test-name', MetadataUtils::getDisplayName($metadata));

        // Test with annotations.title (tool-specific)
        $metadata = [
            'name' => 'test-tool',
            'annotations' => ['title' => 'Annotated Title'],
        ];
        $this->assertEquals('Annotated Title', MetadataUtils::getDisplayName($metadata));

        // Test precedence: title > annotations.title > name
        $metadata = [
            'name' => 'test-tool',
            'title' => 'Main Title',
            'annotations' => ['title' => 'Annotated Title'],
        ];
        $this->assertEquals('Main Title', MetadataUtils::getDisplayName($metadata));
    }

    public function testGetDisplayNameFromObject(): void
    {
        // Test with title
        $metadata = $this->createMockMetadata('test-name', 'Test Title');
        $this->assertEquals('Test Title', MetadataUtils::getDisplayName($metadata));

        // Test without title
        $metadata = $this->createMockMetadata('test-name', null);
        $this->assertEquals('test-name', MetadataUtils::getDisplayName($metadata));

        // Test with empty title
        $metadata = $this->createMockMetadata('test-name', '');
        $this->assertEquals('test-name', MetadataUtils::getDisplayName($metadata));

        // Test with annotations.title in additional properties
        $metadata = $this->createMockMetadata(
            'test-tool',
            null,
            ['annotations' => ['title' => 'Annotated Title']]
        );
        $this->assertEquals('Annotated Title', MetadataUtils::getDisplayName($metadata));

        // Test precedence
        $metadata = $this->createMockMetadata(
            'test-tool',
            'Main Title',
            ['annotations' => ['title' => 'Annotated Title']]
        );
        $this->assertEquals('Main Title', MetadataUtils::getDisplayName($metadata));
    }

    public function testHasTitle(): void
    {
        // Array tests
        $this->assertTrue(MetadataUtils::hasTitle(['name' => 'test', 'title' => 'Title']));
        $this->assertFalse(MetadataUtils::hasTitle(['name' => 'test']));
        $this->assertFalse(MetadataUtils::hasTitle(['name' => 'test', 'title' => '']));

        // Object tests
        $metadata = $this->createMockMetadata('test', 'Title');
        $this->assertTrue(MetadataUtils::hasTitle($metadata));

        $metadata = $this->createMockMetadata('test', null);
        $this->assertFalse(MetadataUtils::hasTitle($metadata));

        $metadata = $this->createMockMetadata('test', '');
        $this->assertFalse(MetadataUtils::hasTitle($metadata));
    }

    public function testGetDescription(): void
    {
        // Array tests
        $metadata = ['name' => 'test', 'description' => 'Test description'];
        $this->assertEquals('Test description', MetadataUtils::getDescription($metadata));

        $metadata = ['name' => 'test'];
        $this->assertNull(MetadataUtils::getDescription($metadata));

        // Object tests
        $metadata = $this->createMockMetadata(
            'test',
            null,
            ['description' => 'Test description']
        );
        $this->assertEquals('Test description', MetadataUtils::getDescription($metadata));

        $metadata = $this->createMockMetadata('test');
        $this->assertNull(MetadataUtils::getDescription($metadata));
    }

    public function testHasDescription(): void
    {
        // Array tests
        $this->assertTrue(MetadataUtils::hasDescription([
            'name' => 'test',
            'description' => 'Description',
        ]));
        $this->assertFalse(MetadataUtils::hasDescription(['name' => 'test']));
        $this->assertFalse(MetadataUtils::hasDescription([
            'name' => 'test',
            'description' => '',
        ]));

        // Object tests
        $metadata = $this->createMockMetadata(
            'test',
            null,
            ['description' => 'Description']
        );
        $this->assertTrue(MetadataUtils::hasDescription($metadata));

        $metadata = $this->createMockMetadata('test');
        $this->assertFalse(MetadataUtils::hasDescription($metadata));

        $metadata = $this->createMockMetadata(
            'test',
            null,
            ['description' => '']
        );
        $this->assertFalse(MetadataUtils::hasDescription($metadata));
    }

    public function testGetDisplayString(): void
    {
        // Test when title equals name
        $metadata = ['name' => 'test-name', 'title' => 'test-name'];
        $this->assertEquals('test-name', MetadataUtils::getDisplayString($metadata));

        // Test when title differs from name
        $metadata = ['name' => 'test-name', 'title' => 'Test Title'];
        $this->assertEquals('test-name (Test Title)', MetadataUtils::getDisplayString($metadata));

        // Test without title
        $metadata = ['name' => 'test-name'];
        $this->assertEquals('test-name', MetadataUtils::getDisplayString($metadata));

        // Test with object
        $metadata = $this->createMockMetadata('test-name', 'Test Title');
        $this->assertEquals('test-name (Test Title)', MetadataUtils::getDisplayString($metadata));

        // Test with annotations.title
        $metadata = [
            'name' => 'test-tool',
            'annotations' => ['title' => 'Tool Title'],
        ];
        $this->assertEquals('test-tool (Tool Title)', MetadataUtils::getDisplayString($metadata));
    }

    public function testEmptyArrayHandling(): void
    {
        $metadata = [];
        $this->assertEquals('', MetadataUtils::getDisplayName($metadata));
        $this->assertFalse(MetadataUtils::hasTitle($metadata));
        $this->assertNull(MetadataUtils::getDescription($metadata));
        $this->assertFalse(MetadataUtils::hasDescription($metadata));
        $this->assertEquals('', MetadataUtils::getDisplayString($metadata));
    }

    public function testComplexAnnotationsStructure(): void
    {
        // Test nested annotations with other properties
        $metadata = [
            'name' => 'complex-tool',
            'title' => '',  // Empty title should be ignored
            'annotations' => [
                'title' => 'Annotation Title',
                'other' => 'ignored',
            ],
        ];
        $this->assertEquals('Annotation Title', MetadataUtils::getDisplayName($metadata));

        // Test with invalid annotations structure
        $metadata = [
            'name' => 'test',
            'annotations' => 'not-an-array',
        ];
        $this->assertEquals('test', MetadataUtils::getDisplayName($metadata));

        // Test with missing annotations.title
        $metadata = [
            'name' => 'test',
            'annotations' => ['other' => 'value'],
        ];
        $this->assertEquals('test', MetadataUtils::getDisplayName($metadata));
    }
}
