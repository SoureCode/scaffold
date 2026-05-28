<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Fixtures\Article;
use Symfony\Component\Clock\MockClock;

final class VersionableIntegrationTest extends TestCase
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

        $metadataFactory = new VersionableMetadataFactory($this->entityManager);

        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($metadataFactory, $this->clock),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($metadataFactory),
        );

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([$this->entityManager->getClassMetadata(Article::class)]);
    }

    public function testInsertCreatesV1Snapshot(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $rows = $this->fetchVersionRows($article->getId());
        self::assertCount(1, $rows);
        self::assertSame(1, (int) $rows[0]['version']);
        self::assertSame('hello', $rows[0]['title']);
    }

    public function testUpdateOnVersionedFieldWritesSnapshot(): void
    {
        $article = new Article('hello');
        $article->setBody('first body');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->clock->modify('+1 hour');
        $article->setTitle('renamed');
        $this->entityManager->flush();

        $rows = $this->fetchVersionRows($article->getId());
        self::assertCount(2, $rows);
        self::assertSame(1, (int) $rows[0]['version']);
        self::assertSame('hello', $rows[0]['title']);
        self::assertSame(2, (int) $rows[1]['version']);
        self::assertSame('renamed', $rows[1]['title']);
        self::assertSame('first body', $rows[1]['body']);
    }

    public function testUpdateOnAnyMappedFieldWritesSnapshot(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setInternalNote('audit only');
        $this->entityManager->flush();

        self::assertSame(2, $this->countVersionRows($article->getId()));
    }

    public function testVersionCounterIncrementsPerEntity(): void
    {
        $article = new Article('one');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('two');
        $this->entityManager->flush();

        $article->setTitle('three');
        $this->entityManager->flush();

        $article->setTitle('four');
        $this->entityManager->flush();

        $rows = $this->fetchVersionRows($article->getId());
        self::assertSame([1, 2, 3, 4], array_map(static fn (array $row): int => (int) $row['version'], $rows));
        self::assertSame(['one', 'two', 'three', 'four'], array_map(static fn (array $row): string => $row['title'], $rows));
    }

    private function countVersionRows(int $entityId): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM versionable_article_version WHERE entity_id = ?',
            [$entityId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchVersionRows(int $entityId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_article_version WHERE entity_id = ? ORDER BY version ASC',
            [$entityId],
        );

        return $rows;
    }
}
