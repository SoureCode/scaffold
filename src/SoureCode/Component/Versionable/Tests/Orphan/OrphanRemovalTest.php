<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Orphan;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Orphan\Fixtures\Catalog;
use SoureCode\Component\Versionable\Tests\Orphan\Fixtures\Item;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use Symfony\Component\Clock\MockClock;

/**
 * #40 — a `OneToMany` with `orphanRemoval`. Removing a child from the owner's
 * collection deletes the child; the owner bumps for the collection change and
 * the deleted child gets no snapshot.
 */
final class OrphanRemovalTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        $factory = new VersionableMetadataFactory($this->entityManager);
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, new MockClock('2026-05-26T10:00:00+00:00')),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Catalog::class),
            $this->entityManager->getClassMetadata(Item::class),
        ]);
    }

    public function testOrphanRemovalBumpsOwnerAndDeletesChild(): void
    {
        $catalog = new Catalog('books');
        $item = new Item('a');
        $catalog->addItem($item);
        $this->entityManager->persist($catalog);
        $this->entityManager->flush();

        self::assertSame(1, $catalog->getVersion(), 'insert seeds version 1 with a snapshot');
        $itemId = $item->getId();
        $itemSnapshotsBeforeRemove = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM orphan_item_version WHERE entity_id = ?',
            [$itemId],
        );
        self::assertSame(1, $itemSnapshotsBeforeRemove, 'item also has its insert snapshot');

        $catalog->removeItem($item);
        $this->entityManager->flush();

        self::assertSame(2, $catalog->getVersion(), 'owner bumps when a child is orphan-removed');

        $surviving = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM orphan_item WHERE id = ?',
            [$itemId],
        );
        self::assertSame(0, (int) $surviving, 'orphan child is deleted');

        $snapshotsAfter = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM orphan_item_version WHERE entity_id = ?',
            [$itemId],
        );
        self::assertSame($itemSnapshotsBeforeRemove, $snapshotsAfter, 'delete adds no tombstone — pre-existing snapshots remain');
    }
}
