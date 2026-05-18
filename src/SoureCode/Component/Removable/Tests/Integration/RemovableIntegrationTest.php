<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Authorable\EventListener\AuthorableMappingListener;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Removable\Tests\Fixtures\Article;
use SoureCode\Component\Removable\Tests\Fixtures\ArticleRepository;
use SoureCode\Component\Removable\Tests\Fixtures\ArticleWithoutMarker;
use SoureCode\Component\Removable\Tests\Fixtures\ArticleWithoutMarkerRepository;
use SoureCode\Component\Removable\Tests\Fixtures\User;
use SoureCode\Component\Removable\Tests\Support\FixedAuthorProvider;
use SoureCode\Component\Timestampable\EventListener\TimestampableMappingListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use Symfony\Component\Clock\MockClock;

final class RemovableIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MockClock $clock;
    private FixedAuthorProvider $authorProvider;
    private ArticleRepository $repository;
    private TimestampableMetadataFactory $timestampableMetadata;
    private AuthorableMetadataFactory $authorableMetadata;

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
        $this->authorProvider = new FixedAuthorProvider();
        $this->timestampableMetadata = new TimestampableMetadataFactory();
        $this->authorableMetadata = new AuthorableMetadataFactory();

        $eventManager = $this->entityManager->getEventManager();
        $eventManager->addEventListener(
            [Events::loadClassMetadata],
            new TimestampableMappingListener($this->timestampableMetadata),
        );
        $eventManager->addEventListener(
            [Events::loadClassMetadata],
            new AuthorableMappingListener($this->authorableMetadata),
        );

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(User::class),
            $this->entityManager->getClassMetadata(Article::class),
            $this->entityManager->getClassMetadata(ArticleWithoutMarker::class),
        ]);

        $this->repository = new ArticleRepository(
            $this->entityManager,
            $this->entityManager->getClassMetadata(Article::class),
            $this->clock,
            $this->timestampableMetadata,
            $this->authorableMetadata,
            $this->authorProvider,
        );
    }

    public function testSoftRemoveFillsDeletedAtAndDeletedBy(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();
        $this->authorProvider->setAuthor($alice);

        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->clock->modify('+1 hour');
        $this->repository->remove($article);

        $expectedDeletedAt = \DateTimeImmutable::createFromInterface($this->clock->now());
        self::assertEquals($expectedDeletedAt, $article->getDeletedAt());
        self::assertSame($alice, $article->getDeletedBy());

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Article::class, $article->getId());
        self::assertNotNull($reloaded);
        self::assertEquals($expectedDeletedAt, $reloaded->getDeletedAt());
        self::assertNotNull($reloaded->getDeletedBy());
        self::assertSame($alice->getId(), $reloaded->getDeletedBy()->getId());
    }

    public function testSoftRemoveWithoutAuthorLeavesDeletedByNull(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->repository->remove($article);

        self::assertNotNull($article->getDeletedAt());
        self::assertNull($article->getDeletedBy());
    }

    public function testHardRemoveDelegatesToEntityManager(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        $id = $article->getId();

        $this->repository->remove($article, soft: false);

        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Article::class, $id));
    }

    public function testRemoveWithFlushFalseDoesNotPersistUntilFlush(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->repository->remove($article, flush: false);
        self::assertNotNull($article->getDeletedAt());

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Article::class, $article->getId());
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getDeletedAt());
    }

    public function testRestoreClearsBothMarkers(): void
    {
        $alice = new User('alice');
        $this->entityManager->persist($alice);
        $this->entityManager->flush();
        $this->authorProvider->setAuthor($alice);

        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->repository->remove($article);
        self::assertNotNull($article->getDeletedAt());
        self::assertNotNull($article->getDeletedBy());

        $this->repository->restore($article);

        self::assertNull($article->getDeletedAt());
        self::assertNull($article->getDeletedBy());

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Article::class, $article->getId());
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getDeletedAt());
        self::assertNull($reloaded->getDeletedBy());
    }

    public function testRemoveThrowsWhenEntityHasNoDeletedAtMarker(): void
    {
        $repository = new ArticleWithoutMarkerRepository(
            $this->entityManager,
            $this->entityManager->getClassMetadata(ArticleWithoutMarker::class),
            $this->clock,
            $this->timestampableMetadata,
            $this->authorableMetadata,
        );

        $entity = new ArticleWithoutMarker('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('#[DeletedAt]');

        $repository->remove($entity);
    }
}
