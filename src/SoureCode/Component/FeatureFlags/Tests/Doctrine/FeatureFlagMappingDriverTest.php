<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Doctrine\FeatureFlagMappingDriver;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
use SoureCode\Component\FeatureFlags\Tests\Fixtures\CustomFeatureFlag;

final class FeatureFlagMappingDriverTest extends TestCase
{
    public function testDefaultEntityClassAndTableAreUsed(): void
    {
        $driver = new FeatureFlagMappingDriver();
        $metadata = $this->makeMetadata(FeatureFlag::class);

        $driver->loadMetadataForClass(FeatureFlag::class, $metadata);

        self::assertSame('feature_flags', $metadata->getTableName());
    }

    public function testCustomEntityClassAndTableNameArePropagated(): void
    {
        $driver = new FeatureFlagMappingDriver(CustomFeatureFlag::class, 'custom_flags');
        $metadata = $this->makeMetadata(CustomFeatureFlag::class);

        $driver->loadMetadataForClass(CustomFeatureFlag::class, $metadata);

        self::assertSame('custom_flags', $metadata->getTableName());
    }

    public function testNameFieldIsMappedAsIdentifier(): void
    {
        $driver = new FeatureFlagMappingDriver();
        $metadata = $this->makeMetadata(FeatureFlag::class);

        $driver->loadMetadataForClass(FeatureFlag::class, $metadata);

        self::assertTrue($metadata->hasField('name'));
        $mapping = $metadata->getFieldMapping('name');
        self::assertSame('string', $mapping->type);
        self::assertSame(['name'], $metadata->identifier);
    }

    public function testEnabledFieldIsMappedAsBoolean(): void
    {
        $driver = new FeatureFlagMappingDriver();
        $metadata = $this->makeMetadata(FeatureFlag::class);

        $driver->loadMetadataForClass(FeatureFlag::class, $metadata);

        self::assertTrue($metadata->hasField('enabled'));
        self::assertSame('boolean', $metadata->getFieldMapping('enabled')->type);
    }

    public function testLoadMetadataIsNoopForUnknownClass(): void
    {
        $driver = new FeatureFlagMappingDriver();
        $metadata = $this->makeMetadata(CustomFeatureFlag::class);

        $driver->loadMetadataForClass(CustomFeatureFlag::class, $metadata);

        self::assertFalse($metadata->hasField('name'));
        self::assertFalse($metadata->hasField('enabled'));
    }

    public function testGetAllClassNamesReturnsConfiguredClass(): void
    {
        $driver = new FeatureFlagMappingDriver();
        self::assertSame([FeatureFlag::class], $driver->getAllClassNames());

        $custom = new FeatureFlagMappingDriver(CustomFeatureFlag::class);
        self::assertSame([CustomFeatureFlag::class], $custom->getAllClassNames());
    }

    public function testIsTransientIsTrueForOtherClassesAndFalseForConfigured(): void
    {
        $driver = new FeatureFlagMappingDriver();

        self::assertFalse($driver->isTransient(FeatureFlag::class));
        self::assertTrue($driver->isTransient(CustomFeatureFlag::class));
        self::assertTrue($driver->isTransient(\stdClass::class));
    }

    /**
     * @param class-string $className
     */
    private function makeMetadata(string $className): ClassMetadata
    {
        $metadata = new ClassMetadata($className, new DefaultNamingStrategy(), new DefaultTypedFieldMapper());
        $metadata->initializeReflection(new \Doctrine\Persistence\Mapping\RuntimeReflectionService());

        return $metadata;
    }
}
