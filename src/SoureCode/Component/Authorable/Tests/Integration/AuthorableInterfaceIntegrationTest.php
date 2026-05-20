<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Authorable\EventListener\AuthorableListener;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Authorable\Tests\Fixtures\InterfaceArticle;
use SoureCode\Component\Authorable\Tests\Fixtures\User;
use SoureCode\Component\Authorable\Tests\Support\FixedAuthorProvider;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;

final class AuthorableInterfaceIntegrationTest extends TestCase
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

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);
        $this->authorProvider = new FixedAuthorProvider();

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            new AuthorableListener($this->authorProvider, new AuthorableMetadataFactory(), new ChangeSetMatcher()),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(User::class),
            $this->entityManager->getClassMetadata(InterfaceArticle::class),
        ]);
    }

    public function testInterfaceFallbackStampsCreatedAndUpdatedOnPersist(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();

        $this->authorProvider->setAuthor($alice);

        $article = new InterfaceArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy(), 'Interface fallback fills createdBy on persist');
        self::assertSame($alice, $article->getUpdatedBy(), 'Interface fallback fills updatedBy on persist');
    }

    public function testInterfaceFallbackRefreshesUpdatedByOnUpdate(): void
    {
        $alice = new User('alice');
        $bob = new User('bob');
        $this->entityManager->persist($alice);
        $this->entityManager->persist($bob);
        $this->entityManager->flush();

        $this->authorProvider->setAuthor($alice);

        $article = new InterfaceArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->authorProvider->setAuthor($bob);

        $article->setTitle('hello v2');
        $this->entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy(), 'createdBy stays the original');
        self::assertSame($bob, $article->getUpdatedBy(), 'updatedBy moves to the new active author');
    }
}
