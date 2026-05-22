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
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Lifecycle\EventListener\AuthorableListener;
use SoureCode\Component\Lifecycle\EventListener\AuthorableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Lifecycle\Tests\Authorable\Fixtures\Article;
use SoureCode\Component\Lifecycle\Tests\Authorable\Fixtures\User;
use SoureCode\Component\Lifecycle\Tests\Authorable\Support\FixedAuthorProvider;

final class AuthorableIntegrationTest extends TestCase
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
            $this->entityManager->getClassMetadata(Article::class),
        ]);
    }

    public function testAutoMappingRegistersManyToOneForAuthorProperties(): void
    {
        $classMetadata = $this->entityManager->getClassMetadata(Article::class);

        self::assertTrue($classMetadata->hasAssociation('createdBy'));
        self::assertTrue($classMetadata->hasAssociation('updatedBy'));
        self::assertSame(User::class, $classMetadata->getAssociationMapping('createdBy')->targetEntity);
    }

    public function testPersistSetsCreatedByLeavesUpdatedByNull(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();
        $this->authorProvider->setAuthor($alice);

        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy());
        self::assertNull($article->getUpdatedBy());
    }

    public function testUpdateSetsUpdatedByFromCurrentAuthor(): void
    {
        $alice = new User('alice');
        $bob = new User('bob');
        $this->entityManager->persist($alice);
        $this->entityManager->persist($bob);
        $this->entityManager->flush();
        $this->authorProvider->setAuthor($alice);

        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->authorProvider->setAuthor($bob);
        $article->setTitle('changed');
        $this->entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy());
        self::assertSame($bob, $article->getUpdatedBy());
    }

    public function testUpdateWithNoAuthorDoesNotTouchUpdatedBy(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();
        $this->authorProvider->setAuthor($alice);

        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->authorProvider->setAuthor(null);
        $article->setTitle('changed');
        $this->entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy());
        self::assertNull($article->getUpdatedBy());
    }
}
