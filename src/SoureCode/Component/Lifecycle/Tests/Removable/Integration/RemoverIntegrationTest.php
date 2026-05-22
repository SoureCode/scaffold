<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Removable\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use SoureCode\Component\Lifecycle\EventListener\AuthorableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Lifecycle\AuthorableDeletionMarkerProvider;
use SoureCode\Component\Lifecycle\Remover;
use SoureCode\Component\Lifecycle\Tests\Removable\Fixtures\Article;
use SoureCode\Component\Lifecycle\Tests\Removable\Fixtures\ArticleWithoutMarker;
use SoureCode\Component\Lifecycle\Tests\Removable\Fixtures\User;
use SoureCode\Component\Lifecycle\Tests\Removable\Support\FixedAuthorProvider;
use SoureCode\Component\Lifecycle\EventListener\TimestampableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\TimestampableMetadataFactory;
use Symfony\Component\Clock\MockClock;

final class RemoverIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MockClock $clock;
    private FixedAuthorProvider $authorProvider;
    private Remover $remover;

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

        $timestampableMetadata = new TimestampableMetadataFactory();
        $authorableMetadata = new AuthorableMetadataFactory();

        $eventManager = $this->entityManager->getEventManager();
        $eventManager->addEventListener(
            [Events::loadClassMetadata],
            new TimestampableMappingListener($timestampableMetadata),
        );
        $eventManager->addEventListener(
            [Events::loadClassMetadata],
            new AuthorableMappingListener($authorableMetadata),
        );

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(User::class),
            $this->entityManager->getClassMetadata(Article::class),
            $this->entityManager->getClassMetadata(ArticleWithoutMarker::class),
        ]);

        $this->remover = new Remover(
            $this->entityManager,
            $this->clock,
            $timestampableMetadata,
            [new AuthorableDeletionMarkerProvider($authorableMetadata, $this->authorProvider)],
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
        $this->remover->remove($article);

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

        $this->remover->remove($article);

        self::assertNotNull($article->getDeletedAt());
        self::assertNull($article->getDeletedBy());
    }

    public function testHardRemoveDelegatesToEntityManager(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        $id = $article->getId();

        $this->remover->remove($article, soft: false);

        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Article::class, $id));
    }

    public function testRemoveWithFlushFalseDoesNotPersistUntilFlush(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->remover->remove($article, flush: false);
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

        $this->remover->remove($article);
        self::assertNotNull($article->getDeletedAt());
        self::assertNotNull($article->getDeletedBy());

        $this->remover->restore($article);

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
        $entity = new ArticleWithoutMarker('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('#[DeletedAt]');

        $this->remover->remove($entity);
    }

    public function testRemoverConstructedWithoutAuthorProviderStillSoftDeletes(): void
    {
        $remover = new Remover(
            $this->entityManager,
            $this->clock,
            new TimestampableMetadataFactory(),
            [new AuthorableDeletionMarkerProvider(new AuthorableMetadataFactory(), authorProvider: null)],
        );

        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $remover->remove($article);

        self::assertNotNull($article->getDeletedAt());
        self::assertNull($article->getDeletedBy(), 'Without an author provider, the deletedBy column stays null');
    }

    public function testRestoreThrowsOnEntityWithoutDeletedAtMarker(): void
    {
        $entity = new ArticleWithoutMarker('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot restore');

        $this->remover->restore($entity);
    }

    public function testRestoreWithFlushFalseDoesNotPersistUntilFlush(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->remover->remove($article);
        $id = $article->getId();

        $this->remover->restore($article, flush: false);
        self::assertNull($article->getDeletedAt());

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Article::class, $id);
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getDeletedAt(), 'Without flush the row in DB is still soft-deleted');
    }

    public function testHardRemoveWithFlushFalseLeavesRowUntilFlush(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        $id = $article->getId();

        $this->remover->remove($article, soft: false, flush: false);

        $this->entityManager->clear();
        self::assertNotNull(
            $this->entityManager->find(Article::class, $id),
            'EntityManager::remove() only schedules; without flush the row is still in the database.',
        );
    }

    public function testRestoreOnLiveEntityLogsWarning(): void
    {
        $logger = $this->captureLogger();

        $remover = new Remover(
            $this->entityManager,
            $this->clock,
            new TimestampableMetadataFactory(),
            [new AuthorableDeletionMarkerProvider(new AuthorableMetadataFactory(), $this->authorProvider)],
            $logger,
        );

        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $remover->restore($article);

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertStringContainsString('deletedAt was already null', $logger->records[0]['message']);
    }

    /**
     * @return LoggerInterface&object{records: list<array{level: string, message: string, context: array<string, mixed>}>}
     */
    private function captureLogger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
