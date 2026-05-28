<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RuntimeMapping;

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
use SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures\Partner;
use SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures\Task;
use SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures\TaskRuntimeMappingListener;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use Symfony\Component\Clock\MockClock;

/**
 * Regression: an entity with a relation registered through a Doctrine
 * `loadClassMetadata` listener (rather than `#[ORM\ManyToOne]` on the
 * property) — the situation produced by `AuthorableMappingListener` for
 * `#[CreatedBy] ?UserInterface $createdBy`. Before driving bindings from
 * `ClassMetadata`, Versionable would miss the association entirely
 * because its reflection scan looked only for ORM attributes.
 */
final class RuntimeMappingTest extends TestCase
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

        // The mapping listener runs BEFORE Versionable's listeners so the
        // association is in ClassMetadata by the time we read bindings.
        $this->entityManager->getEventManager()->addEventListener(
            [Events::loadClassMetadata],
            new TaskRuntimeMappingListener(),
        );

        $factory = new VersionableMetadataFactory($this->entityManager);

        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, new MockClock('2026-05-28T10:00:00+00:00')),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Partner::class),
            $this->entityManager->getClassMetadata(Task::class),
        ]);
    }

    public function testSchemaIncludesListenerAddedAssociationOnVersionTable(): void
    {
        $columns = $this->entityManager->getConnection()->createSchemaManager()
            ->introspectSchema()
            ->getTable('rtmap_task_version')
            ->getColumns();

        $names = array_map(static fn ($column) => $column->getName(), $columns);

        self::assertContains('createdBy_id', $names, 'listener-added association lands on the version table');
        self::assertContains('createdBy_version', $names, 'and the pin column too, since the target is versioned');
    }

    public function testSnapshotRowCapturesListenerAddedAssociation(): void
    {
        $partner = new Partner('alice');
        $task = new Task('hello');
        $this->entityManager->persist($partner);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $task->setCreatedBy($partner);
        $this->entityManager->flush();

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT createdBy_id, createdBy_version FROM rtmap_task_version WHERE entity_id = ? ORDER BY version DESC LIMIT 1',
            [$task->getId()],
        );

        self::assertIsArray($row);
        self::assertSame($partner->getId(), (int) $row['createdBy_id'], 'snapshot row captures the FK of the listener-added association');
        self::assertSame($partner->getVersion(), (int) $row['createdBy_version'], 'and pins it at the target\'s current version');
    }

    public function testLivePinColumnIsWrittenForListenerAddedAssociation(): void
    {
        $partner = new Partner('bob');
        $task = new Task('hi');
        $this->entityManager->persist($partner);
        $this->entityManager->persist($task);
        $task->setCreatedBy($partner);
        $this->entityManager->flush();

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT created_by_id, createdBy_version FROM rtmap_task WHERE id = ?',
            [$task->getId()],
        );

        self::assertIsArray($row);
        self::assertSame($partner->getId(), (int) $row['created_by_id'], 'Doctrine-named FK column');
        self::assertSame($partner->getVersion(), (int) $row['createdBy_version'], 'live-side pin is populated even for listener-mapped relations');
    }
}
