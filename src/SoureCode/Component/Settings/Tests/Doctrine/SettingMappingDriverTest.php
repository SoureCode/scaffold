<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
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
     * Reserved-word safety: the generated DDL for the `key` column must
     * use the active platform's quote character, never raw backticks
     * leaking from the mapping into the SQL stream. Doctrine's QuoteStrategy
     * is responsible for translating the `quoted` flag; this test fails
     * loudly if a refactor accidentally drops the flag and emits bare
     * `key` (which PostgreSQL would reject as a reserved word).
     */
    public function testGeneratedDdlEmitsPlatformQuotedKeyColumn(): void
    {
        $config = ORMSetup::createConfiguration(isDevMode: true);
        $config->setMetadataDriverImpl(new SettingMappingDriver());
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );
        $entityManager = new EntityManager($connection, $config);

        $sql = (new SchemaTool($entityManager))->getCreateSchemaSql([
            $entityManager->getClassMetadata(Setting::class),
        ]);

        $createTable = $sql[0] ?? '';

        $quoteChar = $connection->getDatabasePlatform()->quoteSingleIdentifier('x')[0];
        $expectedQuoted = $quoteChar . 'key' . $quoteChar;

        self::assertStringContainsString($expectedQuoted, $createTable, 'key column must appear with the platform-specific quote char');
        self::assertStringNotContainsString('`key`', $createTable, 'raw backticks must not leak into the generated SQL');
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
