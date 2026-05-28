<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Lock;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Lock\Fixtures\Lockable;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use Symfony\Component\Clock\MockClock;

final class LockTest extends TestCase
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
            $this->entityManager->getClassMetadata(Lockable::class),
        ]);
    }

    public function testLockFieldIsNotSnapshotContent(): void
    {
        $entity = new Lockable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->setTitle('renamed');
        $this->entityManager->flush();

        self::assertSame(2, $entity->getVersion(), 'our counter bumps (v=1 at insert, v=2 after change)');

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM lock_probe_entity_version WHERE entity_id = ? ORDER BY version DESC LIMIT 1',
            [$entity->getId()],
        );

        self::assertIsArray($row);
        self::assertArrayHasKey('title', $row);
        self::assertArrayNotHasKey('lockVersion', $row, 'the optimistic-lock column is not snapshot content');
    }

    public function testConcurrentModificationThrowsOptimisticLockException(): void
    {
        $entity = new Lockable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE lock_probe_entity SET lockVersion = lockVersion + 1 WHERE id = ?',
            [$entity->getId()],
        );

        $this->expectException(OptimisticLockException::class);

        $entity->setTitle('renamed');
        $this->entityManager->flush();
    }
}
