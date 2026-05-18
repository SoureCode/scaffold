<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\Timestampable\EventListener\TimestampableListener;
use SoureCode\Component\Timestampable\EventListener\TimestampableMappingListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Timestampable\Tests\Fixtures\SoftDeletable;
use Symfony\Component\Clock\MockClock;

final class DeletedAtIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MockClock $clock;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
        $connection = DriverManager::getConnection(
            $dsnParser->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);
        $this->clock = new MockClock('2026-05-17T10:00:00+00:00');

        $metadataFactory = new TimestampableMetadataFactory();
        $timestampFactory = new TimestampFactory($this->clock);

        $listener = new TimestampableListener(
            $this->clock,
            $metadataFactory,
            $timestampFactory,
            new ChangeSetMatcher(),
        );
        $mappingListener = new TimestampableMappingListener($metadataFactory);

        $eventManager = $this->entityManager->getEventManager();
        $eventManager->addEventListener([Events::prePersist, Events::onFlush], $listener);
        $eventManager->addEventListener([Events::loadClassMetadata], $mappingListener);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(SoftDeletable::class),
        ]);
    }

    public function testColumnIsAutoMappedAsNullable(): void
    {
        $classMetadata = $this->entityManager->getClassMetadata(SoftDeletable::class);

        self::assertTrue($classMetadata->hasField('deletedAt'));
        self::assertTrue($classMetadata->isNullable('deletedAt'));
    }

    public function testPersistDoesNotAutoFillDeletedAt(): void
    {
        $entity = new SoftDeletable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertNull($entity->getDeletedAt());
    }

    public function testUpdateDoesNotAutoFillDeletedAt(): void
    {
        $entity = new SoftDeletable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->clock->modify('+1 hour');
        $this->entityManager->flush();

        self::assertNull($entity->getDeletedAt());
    }

    public function testCallerSetsDeletedAtManually(): void
    {
        $entity = new SoftDeletable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->clock->modify('+1 hour');
        $marker = \DateTimeImmutable::createFromInterface($this->clock->now());
        $entity->setDeletedAt($marker);
        $this->entityManager->flush();

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(SoftDeletable::class, $entity->getId());

        self::assertNotNull($reloaded);
        self::assertEquals($marker, $reloaded->getDeletedAt());
    }
}
