<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Doctrine\SettingMappingDriver;
use SoureCode\Component\Settings\Manager\DoctrineSettingsManager;
use SoureCode\Component\Settings\Tests\Fixtures\CustomSetting;

final class CustomSettingIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineSettingsManager $manager;

    protected function setUp(): void
    {
        $config = ORMSetup::createConfiguration(isDevMode: true);
        $config->setMetadataDriverImpl(new SettingMappingDriver(CustomSetting::class, 'custom_settings'));
        $config->enableNativeLazyObjects(true);

        $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
        $connection = DriverManager::getConnection(
            $dsnParser->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(CustomSetting::class),
        ]);

        $this->manager = new DoctrineSettingsManager(
            entityManager: $this->entityManager,
            settingEntityClassName: CustomSetting::class,
        );
    }

    public function testFirstWriteHydratesAsConfiguredCustomClassOnRead(): void
    {
        $this->manager->set('color.primary', '#0066ff');

        $stored = $this->entityManager->getRepository(CustomSetting::class)->find('color.primary');

        self::assertInstanceOf(CustomSetting::class, $stored);
        self::assertSame('#0066ff', $stored->getValue());
    }

    public function testTableNameIsTheConfiguredOne(): void
    {
        $schema = $this->entityManager->getConnection()
            ->createSchemaManager()
            ->introspectSchema();

        self::assertTrue($schema->hasTable('custom_settings'));
    }

    public function testCustomClassRoundTripsThroughManagerApi(): void
    {
        $this->manager->set('palette', ['primary' => '#000', 'secondary' => '#fff']);
        $this->manager->set('rollout.enabled', true);

        self::assertSame(['primary' => '#000', 'secondary' => '#fff'], $this->manager->get('palette'));
        self::assertTrue($this->manager->get('rollout.enabled'));

        $collection = $this->manager->all();
        self::assertCount(2, $collection);
        self::assertInstanceOf(CustomSetting::class, $collection->get('palette'));
    }
}
