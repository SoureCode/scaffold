<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Doctrine\SettingMappingDriver;
use SoureCode\Component\Settings\Model\Setting;
use SoureCode\Component\Settings\Tests\Fixtures\CustomSetting;

final class SettingMappingDriverTest extends TestCase
{
    public function testDefaultEntityClassAndTableAreUsed(): void
    {
        $driver = new SettingMappingDriver();
        $metadata = $this->makeMetadata(Setting::class);

        $driver->loadMetadataForClass(Setting::class, $metadata);

        self::assertSame('settings', $metadata->getTableName());
    }

    public function testCustomEntityClassAndTableNameArePropagated(): void
    {
        $driver = new SettingMappingDriver(CustomSetting::class, 'custom_settings');
        $metadata = $this->makeMetadata(CustomSetting::class);

        $driver->loadMetadataForClass(CustomSetting::class, $metadata);

        self::assertSame('custom_settings', $metadata->getTableName());
    }

    public function testKeyFieldIsMappedAsIdentifier(): void
    {
        $driver = new SettingMappingDriver();
        $metadata = $this->makeMetadata(Setting::class);

        $driver->loadMetadataForClass(Setting::class, $metadata);

        self::assertTrue($metadata->hasField('key'));
        $mapping = $metadata->getFieldMapping('key');
        self::assertSame('string', $mapping->type);
        self::assertSame(['key'], $metadata->identifier);
        self::assertTrue((bool) $mapping->quoted, 'key column must be force-quoted to dodge SQL reserved word');
    }

    public function testValueFieldIsMappedAsJsonNullable(): void
    {
        $driver = new SettingMappingDriver();
        $metadata = $this->makeMetadata(Setting::class);

        $driver->loadMetadataForClass(Setting::class, $metadata);

        self::assertTrue($metadata->hasField('value'));
        $mapping = $metadata->getFieldMapping('value');
        self::assertSame('json', $mapping->type);
        self::assertTrue($mapping->nullable ?? false);
    }

    public function testLoadMetadataIsNoopForUnknownClass(): void
    {
        $driver = new SettingMappingDriver();
        $metadata = $this->makeMetadata(CustomSetting::class);

        $driver->loadMetadataForClass(CustomSetting::class, $metadata);

        self::assertFalse($metadata->hasField('key'));
        self::assertFalse($metadata->hasField('value'));
    }

    public function testGetAllClassNamesReturnsConfiguredClass(): void
    {
        $driver = new SettingMappingDriver();
        self::assertSame([Setting::class], $driver->getAllClassNames());

        $custom = new SettingMappingDriver(CustomSetting::class);
        self::assertSame([CustomSetting::class], $custom->getAllClassNames());
    }

    public function testIsTransientIsTrueForOtherClassesAndFalseForConfigured(): void
    {
        $driver = new SettingMappingDriver();

        self::assertFalse($driver->isTransient(Setting::class));
        self::assertTrue($driver->isTransient(CustomSetting::class));
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
