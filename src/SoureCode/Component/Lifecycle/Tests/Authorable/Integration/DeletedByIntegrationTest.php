<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Authorable\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Lifecycle\EventListener\AuthorableListener;
use SoureCode\Component\Lifecycle\EventListener\AuthorableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Lifecycle\Tests\Authorable\Fixtures\SoftDeletable;
use SoureCode\Component\Lifecycle\Tests\Authorable\Fixtures\User;
use SoureCode\Component\Lifecycle\Tests\Authorable\Support\FixedAuthorProvider;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;

final class DeletedByIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private FixedAuthorProvider $authorProvider;

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
        $this->authorProvider = new FixedAuthorProvider();

        $metadataFactory = new AuthorableMetadataFactory();

        $listener = new AuthorableListener(
            $this->authorProvider,
            $metadataFactory,
            new ChangeSetMatcher(),
        );
        $mappingListener = new AuthorableMappingListener($metadataFactory);

        $eventManager = $this->entityManager->getEventManager();
        $eventManager->addEventListener([Events::prePersist, Events::onFlush], $listener);
        $eventManager->addEventListener([Events::loadClassMetadata], $mappingListener);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(User::class),
            $this->entityManager->getClassMetadata(SoftDeletable::class),
        ]);
    }

    public function testAssociationIsAutoMappedAsNullable(): void
    {
        $classMetadata = $this->entityManager->getClassMetadata(SoftDeletable::class);

        self::assertTrue($classMetadata->hasAssociation('deletedBy'));
        self::assertSame(User::class, $classMetadata->getAssociationMapping('deletedBy')->targetEntity);

        $joinColumn = $classMetadata->getAssociationMapping('deletedBy')->joinColumns[0];
        self::assertTrue($joinColumn->nullable);
    }

    public function testPersistDoesNotAutoFillDeletedBy(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();
        $this->authorProvider->setAuthor($alice);

        $entity = new SoftDeletable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertNull($entity->getDeletedBy());
    }

    public function testUpdateDoesNotAutoFillDeletedBy(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();
        $this->authorProvider->setAuthor($alice);

        $entity = new SoftDeletable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->setTitle('changed');
        $this->entityManager->flush();

        self::assertNull($entity->getDeletedBy());
    }

    public function testCallerSetsDeletedByManually(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();
        $this->authorProvider->setAuthor($alice);

        $entity = new SoftDeletable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->setDeletedBy($alice);
        $this->entityManager->flush();

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(SoftDeletable::class, $entity->getId());

        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getDeletedBy());
        self::assertSame($alice->getId(), $reloaded->getDeletedBy()->getId());
    }
}
