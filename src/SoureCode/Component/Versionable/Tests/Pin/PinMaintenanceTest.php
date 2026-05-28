<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Pin;

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
use SoureCode\Component\Versionable\Tests\Pin\Fixtures\Pinned;
use SoureCode\Component\Versionable\Tests\Pin\Fixtures\Target;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use Symfony\Component\Clock\MockClock;

final class PinMaintenanceTest extends TestCase
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
            $this->entityManager->getClassMetadata(Pinned::class),
            $this->entityManager->getClassMetadata(Target::class),
        ]);
    }

    public function testPinColumnIsAddedToLiveTable(): void
    {
        $columns = $this->entityManager->getConnection()->createSchemaManager()
            ->introspectSchema()
            ->getTable('pin_pinned')
            ->getColumns();

        $names = array_map(static fn ($column) => $column->getName(), $columns);

        self::assertContains('target_id', $names, 'FK column is present');
        self::assertContains('target_version', $names, 'pin column is present next to the FK');
    }

    public function testPinIsWrittenOnInsert(): void
    {
        $target = new Target('t');
        $this->entityManager->persist($target);
        $this->entityManager->flush();
        self::assertSame(1, $target->getVersion());

        $pinned = new Pinned('p');
        $pinned->setTarget($target);
        $this->entityManager->persist($pinned);
        $this->entityManager->flush();

        // target is the inverse owner of the new relation — it bumps when
        // Pinned is inserted, so it lands at v=2 by the time the pin is
        // written.
        self::assertSame(2, $target->getVersion());

        $row = $this->fetchLiveRow($pinned->getId());

        self::assertSame($target->getId(), (int) $row['target_id']);
        self::assertSame($target->getVersion(), (int) $row['target_version'], 'pin captures the target at its bumped version');
    }

    public function testPinIsUpdatedWhenRelationIsReassigned(): void
    {
        $first = new Target('first');
        $second = new Target('second');
        $this->entityManager->persist($first);
        $this->entityManager->persist($second);
        $this->entityManager->flush();

        $pinned = new Pinned('p');
        $pinned->setTarget($first);
        $this->entityManager->persist($pinned);
        $this->entityManager->flush();

        $row = $this->fetchLiveRow($pinned->getId());
        self::assertSame($first->getId(), (int) $row['target_id']);
        self::assertSame($first->getVersion(), (int) $row['target_version'], 'pin captures first at its bumped version');

        $pinned->setTarget($second);
        $this->entityManager->flush();

        $row = $this->fetchLiveRow($pinned->getId());
        self::assertSame($second->getId(), (int) $row['target_id']);
        self::assertSame($second->getVersion(), (int) $row['target_version'], 'pin captures second at its bumped version');
    }

    public function testPinIsNullWhenRelationIsNull(): void
    {
        $pinned = new Pinned('p');
        $this->entityManager->persist($pinned);
        $this->entityManager->flush();

        $row = $this->fetchLiveRow($pinned->getId());
        self::assertNull($row['target_id']);
        self::assertNull($row['target_version']);
    }

    public function testPinStaysFrozenWhenTargetBumpsButOwnerIsNotFlushed(): void
    {
        $target = new Target('t');
        $this->entityManager->persist($target);
        $this->entityManager->flush();

        $pinned = new Pinned('p');
        $pinned->setTarget($target);
        $this->entityManager->persist($pinned);
        $this->entityManager->flush();

        $row = $this->fetchLiveRow($pinned->getId());
        self::assertSame(2, (int) $row['target_version'], 'target bumped to v=2 because Pinned referenced it; pin recorded that');

        // Target bumps independently — but only the target is flushed, not Pinned.
        $target->setName('t-2');
        $this->entityManager->flush();
        self::assertSame(3, $target->getVersion());

        $row = $this->fetchLiveRow($pinned->getId());
        self::assertSame(2, (int) $row['target_version'], 'pin frozen — Pinned was not flushed');
    }

    public function testPinIsRefreshedWhenOwnerFlushesEvenWithoutRelationChange(): void
    {
        $target = new Target('t');
        $this->entityManager->persist($target);
        $this->entityManager->flush();

        $pinned = new Pinned('p');
        $pinned->setTarget($target);
        $this->entityManager->persist($pinned);
        $this->entityManager->flush();

        $target->setName('t-2');
        $this->entityManager->flush();
        self::assertSame(3, $target->getVersion());

        // Pinned is flushed without touching its relation — the pin refreshes
        // to the target's current version because the owner's snapshot at
        // this flush captures the relation as-of-now.
        $pinned->setTitle('p-2');
        $this->entityManager->flush();

        $row = $this->fetchLiveRow($pinned->getId());
        self::assertSame(3, (int) $row['target_version']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchLiveRow(?int $id): array
    {
        self::assertNotNull($id);

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM pin_pinned WHERE id = ?',
            [$id],
        );

        self::assertIsArray($row);

        return $row;
    }
}
