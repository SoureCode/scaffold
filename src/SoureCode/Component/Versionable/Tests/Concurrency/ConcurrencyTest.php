<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Concurrency;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
use SoureCode\Component\Versionable\Tests\Concurrency\Fixtures\Plain;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use Symfony\Component\Clock\MockClock;

/**
 * #37 — a versioned entity with no `#[ORM\Version]` lock. Without a lock the
 * `(entity_id, version)` unique index is the only concurrency backstop.
 */
final class ConcurrencyTest extends TestCase
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
            $this->entityManager->getClassMetadata(Plain::class),
        ]);
    }

    public function testSequentialWritersAppendDistinctVersions(): void
    {
        $entity = new Plain('v0');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->setTitle('v1');
        $this->entityManager->flush();

        $entity->setTitle('v2');
        $this->entityManager->flush();

        self::assertSame(3, $entity->getVersion());

        $versions = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT version FROM concurrency_probe_version WHERE entity_id = ? ORDER BY version',
            [$entity->getId()],
        );

        self::assertSame([1, 2, 3], $versions, 'insert + two edits append three distinct versions');
    }

    public function testCollidingWriteHitsTheUniqueIndex(): void
    {
        $entity = new Plain('v0');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // A concurrent writer already committed version 2 for this entity
        // (we hold the insert v=1 snapshot already).
        $this->entityManager->getConnection()->insert('concurrency_probe_version', [
            'entity_id' => $entity->getId(),
            'version' => 2,
            'created_at' => '2026-05-26 10:00:00',
            'title' => 'from-another-writer',
        ]);

        // This writer is stale (still at v=1) and computes the same next
        // version — with no lock, the unique index rejects it.
        $this->expectException(UniqueConstraintViolationException::class);

        $entity->setTitle('v1');
        $this->entityManager->flush();
    }
}
