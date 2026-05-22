<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Inheritance;

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
use SoureCode\Component\Versionable\Tests\Inheritance\Fixtures\InheritedAnnouncement;
use SoureCode\Component\Versionable\Tests\Inheritance\Fixtures\InheritedDocument;
use SoureCode\Component\Versionable\Tests\Inheritance\Fixtures\InheritedMemo;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

final class InheritanceIntegrationTest extends TestCase
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
            $this->entityManager->getClassMetadata(InheritedDocument::class),
            $this->entityManager->getClassMetadata(InheritedMemo::class),
            $this->entityManager->getClassMetadata(InheritedAnnouncement::class),
        ]);

        $this->versioner = new Versioner($this->entityManager, $factory);
    }

    public function testVersionTableIsSharedAcrossSubclassesAndCarriesDiscriminator(): void
    {
        $schema = $this->entityManager->getConnection()->createSchemaManager()->introspectSchema();

        self::assertTrue($schema->hasTable('versionable_inherited_document_version'));

        $columns = $schema->getTable('versionable_inherited_document_version')->getColumns();
        $columnNames = array_map(static fn ($column) => $column->getName(), $columns);

        self::assertContains('kind', $columnNames, 'discriminator column flows into version table');
        self::assertContains('title', $columnNames, 'root-class versioned field is present');
        self::assertContains('author_note', $columnNames, 'memo-only field is folded into the shared table');
        self::assertContains('audience', $columnNames, 'announcement-only field is folded into the shared table');
    }

    public function testSnapshotRoundTripPreservesSubclassValuesAndDiscriminator(): void
    {
        $memo = new InheritedMemo('memo-v1');
        $memo->setAuthorNote('first');
        $this->entityManager->persist($memo);
        $this->entityManager->flush();

        $memo->setTitle('memo-v2');
        $memo->setAuthorNote('second');
        $this->entityManager->flush();

        $row = $this->entityManager->getConnection()
            ->createQueryBuilder()
            ->select('*')
            ->from('versionable_inherited_document_version')
            ->where('entity_id = :id')
            ->setParameter('id', $memo->getId())
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame('memo', $row['kind']);
        self::assertSame('memo-v2', $row['title']);
        self::assertSame('second', $row['author_note']);
    }

    public function testAnnouncementSubclassWritesItsOwnDiscriminatorValue(): void
    {
        $announcement = new InheritedAnnouncement('a-v1');
        $announcement->setAudience('staff');
        $this->entityManager->persist($announcement);
        $this->entityManager->flush();

        $announcement->setTitle('a-v2');
        $this->entityManager->flush();

        $row = $this->entityManager->getConnection()
            ->createQueryBuilder()
            ->select('*')
            ->from('versionable_inherited_document_version')
            ->where('entity_id = :id')
            ->setParameter('id', $announcement->getId())
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame('announcement', $row['kind']);
        self::assertSame('a-v2', $row['title']);
        self::assertSame('staff', $row['audience']);
    }
}
