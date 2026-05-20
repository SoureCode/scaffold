<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Doctrine\FeatureFlagMappingDriver;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactory;
use SoureCode\Component\FeatureFlags\Manager\DoctrineFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Tests\Fixtures\CustomFeatureFlag;

final class CustomFeatureFlagIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineFeatureFlagsManager $manager;

    protected function setUp(): void
    {
        $config = ORMSetup::createConfiguration(isDevMode: true);
        $config->setMetadataDriverImpl(new FeatureFlagMappingDriver(CustomFeatureFlag::class, 'custom_flags'));
        $config->enableNativeLazyObjects(true);

        $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
        $connection = DriverManager::getConnection(
            $dsnParser->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(CustomFeatureFlag::class),
        ]);

        $this->manager = new DoctrineFeatureFlagsManager(
            entityManager: $this->entityManager,
            featureFlagEntityClassName: CustomFeatureFlag::class,
            featureFlagFactory: new FeatureFlagFactory(CustomFeatureFlag::class),
        );
    }

    public function testFactoryInstantiatesCustomClassOnEnable(): void
    {
        $this->manager->enable('alpha');

        $stored = $this->entityManager->getRepository(CustomFeatureFlag::class)->find('alpha');

        self::assertInstanceOf(CustomFeatureFlag::class, $stored);
        self::assertTrue($stored->isEnabled());
    }

    public function testTableNameIsTheConfiguredOne(): void
    {
        $schema = $this->entityManager->getConnection()
            ->createSchemaManager()
            ->introspectSchema();

        self::assertTrue($schema->hasTable('custom_flags'));
    }

    public function testCustomClassRoundTripsThroughManagerApi(): void
    {
        $this->manager->enable('alpha');
        $this->manager->disable('beta');

        self::assertTrue($this->manager->isEnabled('alpha'));
        self::assertFalse($this->manager->isEnabled('beta'));
        self::assertTrue($this->manager->has('beta'));

        $collection = $this->manager->all();
        self::assertCount(2, $collection);
        self::assertInstanceOf(CustomFeatureFlag::class, $collection->get('alpha'));
    }
}
