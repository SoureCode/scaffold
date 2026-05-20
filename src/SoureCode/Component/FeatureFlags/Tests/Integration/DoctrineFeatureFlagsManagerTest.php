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
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;

final class DoctrineFeatureFlagsManagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineFeatureFlagsManager $manager;

    protected function setUp(): void
    {
        $config = ORMSetup::createConfiguration(isDevMode: true);
        $config->setMetadataDriverImpl(new FeatureFlagMappingDriver());
        $config->enableNativeLazyObjects(true);

        $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
        $connection = DriverManager::getConnection(
            $dsnParser->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(FeatureFlag::class),
        ]);

        $this->manager = new DoctrineFeatureFlagsManager(
            entityManager: $this->entityManager,
            featureFlagEntityClassName: FeatureFlag::class,
            featureFlagFactory: new FeatureFlagFactory(),
        );
    }

    public function testMissingFlagDefaultsToDisabled(): void
    {
        self::assertFalse($this->manager->isEnabled('beta'));
        self::assertFalse($this->manager->has('beta'));
    }

    public function testEnablePersistsAndTurnsFlagOn(): void
    {
        $this->manager->enable('beta');

        self::assertTrue($this->manager->isEnabled('beta'));
        self::assertTrue($this->manager->has('beta'));
    }

    public function testDisablePersistsAndTurnsFlagOff(): void
    {
        $this->manager->enable('beta');
        $this->manager->disable('beta');

        self::assertFalse($this->manager->isEnabled('beta'));
        self::assertTrue($this->manager->has('beta'));
    }

    public function testRemoveDeletesFlag(): void
    {
        $this->manager->enable('beta');
        $this->manager->remove('beta');

        self::assertFalse($this->manager->has('beta'));
        self::assertFalse($this->manager->isEnabled('beta'));
    }

    public function testAllReturnsCollectionOfFlags(): void
    {
        $this->manager->enable('a');
        $this->manager->disable('b');

        $collection = $this->manager->all();

        self::assertCount(2, $collection);
        self::assertTrue($collection->get('a')->isEnabled());
        self::assertFalse($collection->get('b')->isEnabled());
    }

    public function testInvalidNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->isEnabled('Invalid Name');
    }
}
