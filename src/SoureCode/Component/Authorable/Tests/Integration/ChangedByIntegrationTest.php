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
use SoureCode\Component\Authorable\EventListener\AuthorableMappingListener;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Authorable\Tests\Fixtures\ArticleWithChangedBy;
use SoureCode\Component\Authorable\Tests\Fixtures\User;
use SoureCode\Component\Authorable\Tests\Support\FixedAuthorProvider;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;

final class ChangedByIntegrationTest extends TestCase
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

        $metadataFactory = new AuthorableMetadataFactory();
        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            new AuthorableListener($this->authorProvider, $metadataFactory, new ChangeSetMatcher()),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [Events::loadClassMetadata],
            new AuthorableMappingListener($metadataFactory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(User::class),
            $this->entityManager->getClassMetadata(ArticleWithChangedBy::class),
        ]);
    }

    public function testChangedByStampsWhenWatchedFieldChanges(): void
    {
        $alice = new User('alice');
        $bob = new User('bob');
        $this->entityManager->persist($alice);
        $this->entityManager->persist($bob);
        $this->entityManager->flush();

        $this->authorProvider->setAuthor($alice);

        $article = new ArticleWithChangedBy('hello', 'body-1');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        self::assertNull($article->getChangedBy(), 'Persist alone does not fill ChangedBy (no change yet)');

        $this->authorProvider->setAuthor($bob);
        $article->setTitle('hello v2');
        $this->entityManager->flush();

        self::assertSame($bob, $article->getChangedBy(), 'Title change must fire ChangedBy with the active author');
    }

    public function testChangedByStampsAlsoForSecondaryWatchedField(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();

        $this->authorProvider->setAuthor($alice);

        $article = new ArticleWithChangedBy('hello', 'body-1');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setBody('body-2');
        $this->entityManager->flush();

        self::assertSame($alice, $article->getChangedBy(), 'Body change must also fire ChangedBy');
    }

    public function testChangedByDoesNotFireWhenNoWatchedFieldChanges(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();

        $this->authorProvider->setAuthor($alice);

        $article = new ArticleWithChangedBy('hello', 'body-1');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->entityManager->flush();

        self::assertNull($article->getChangedBy(), 'No change means no stamp');
    }
}
