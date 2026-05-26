<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Integration;

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
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Fixtures\Category;
use SoureCode\Component\Versionable\Tests\Fixtures\Comment;
use SoureCode\Component\Versionable\Tests\Fixtures\Profile;
use SoureCode\Component\Versionable\Tests\Fixtures\RichArticle;
use SoureCode\Component\Versionable\Tests\Fixtures\Tag;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

final class EdgeCaseIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private VersionableMetadataFactory $metadataFactory;

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
        $clock = new MockClock('2026-05-17T10:00:00+00:00');

        $this->metadataFactory = new VersionableMetadataFactory();
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($this->metadataFactory, $clock),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($this->metadataFactory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Category::class),
            $this->entityManager->getClassMetadata(Profile::class),
            $this->entityManager->getClassMetadata(Tag::class),
            $this->entityManager->getClassMetadata(RichArticle::class),
            $this->entityManager->getClassMetadata(Comment::class),
        ]);
    }

    public function testOwnerScheduledForDeletionIsExcludedFromSnapshotTargets(): void
    {
        $article = new RichArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $comment = new Comment('first', $article);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $rowsBefore = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_rich_article_version WHERE entity_id = ?',
            [$article->getId()],
        );

        // Delete both the child (Comment) and the owner (RichArticle) in one flush.
        // Without the owner-scheduled-for-deletion guard, the listener would try to
        // snapshot the article in postFlush after its row is already gone, triggering
        // a foreign-key violation on the version table's entity_id.
        $this->entityManager->remove($comment);
        $this->entityManager->remove($article);
        $this->entityManager->flush();

        $rowsAfter = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_rich_article_version',
        );

        self::assertCount(\count($rowsBefore), $rowsAfter, 'No additional snapshot must be produced for a parent that is itself being deleted');
    }

    public function testVersionerLogsWarningWhenHistoricalForeignKeyNoLongerResolves(): void
    {
        $category = new Category('news');
        $article = new RichArticle('hello');
        $this->entityManager->persist($category);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setCategory($category);
        $article->setTitle('v2');
        $this->entityManager->flush();

        $deletedCategoryId = $category->getId();

        // Drop the category outside Doctrine so the FK becomes dangling for the version row.
        $this->entityManager->clear();
        $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM versionable_category WHERE id = ?',
            [$deletedCategoryId],
        );

        $logger = $this->captureLogger();
        $versioner = new Versioner($this->entityManager, $this->metadataFactory, $logger);

        $reloaded = $this->entityManager->find(RichArticle::class, $article->getId());
        self::assertNotNull($reloaded);

        $versioner->applyVersion($reloaded, 1);

        $warnings = array_filter(
            $logger->records,
            static fn(array $record): bool => $record['level'] === 'warning',
        );
        self::assertNotEmpty($warnings, 'Versioner must log a warning when a historical FK target no longer resolves');
    }

    public function testConcurrentDuplicateVersionInsertViolatesUniqueIndex(): void
    {
        $article = new RichArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('v2');
        $this->entityManager->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        // Force a duplicate (entity_id, version) row to confirm the protective unique
        // index actually rejects the conflicting concurrent snapshot path.
        $this->entityManager->getConnection()->insert('versionable_rich_article_version', [
            'entity_id' => $article->getId(),
            'version' => 1,
            'created_at' => '2026-05-17 10:00:00',
            'title' => 'duplicate',
        ]);
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
