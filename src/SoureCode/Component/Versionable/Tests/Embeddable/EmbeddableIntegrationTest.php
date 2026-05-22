<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Embeddable;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Embeddable\Fixtures\CustomerLocation;
use SoureCode\Component\Versionable\Tests\Embeddable\Fixtures\PostalAddress;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

final class EmbeddableIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Versioner $versioner;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
        $connection = DriverManager::getConnection(
            $dsnParser->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);
        $clock = new MockClock('2026-05-21T12:00:00+00:00');

        $factory = new VersionableMetadataFactory();
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            new VersionableListener($factory, $clock),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(CustomerLocation::class),
        ]);

        $this->versioner = new Versioner($this->entityManager, $factory);
    }

    public function testVersionTableFlattensEmbeddableColumns(): void
    {
        $schema = $this->entityManager->getConnection()->createSchemaManager()->introspectSchema();

        $table = $schema->getTable('versionable_customer_location_version');
        $names = array_map(static fn ($column) => $column->getName(), $table->getColumns());

        self::assertContains('address_street', $names, 'embeddable sub-fields are flattened');
        self::assertContains('address_city', $names);
    }

    public function testSnapshotAndRestoreOfEmbeddedValueObject(): void
    {
        $location = new CustomerLocation('HQ', new PostalAddress('Main 1', 'Berlin'));
        $this->entityManager->persist($location);
        $this->entityManager->flush();

        $location->setAddress(new PostalAddress('Side 2', 'Munich'));
        $this->entityManager->flush();

        $location->setAddress(new PostalAddress('Other 3', 'Paris'));
        $this->entityManager->flush();

        $applied = $this->versioner->applyVersion($location, 1);

        self::assertContains('address', $applied->changedFields, 'embeddable property reported as changed');
        self::assertSame('Side 2', $location->getAddress()->getStreet());
        self::assertSame('Munich', $location->getAddress()->getCity());
    }
}
