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
use SoureCode\Component\Versionable\Tests\Fixtures\Category;
use SoureCode\Component\Versionable\Tests\Fixtures\Comment;
use SoureCode\Component\Versionable\Tests\Fixtures\Profile;
use SoureCode\Component\Versionable\Tests\Fixtures\RichArticle;
use SoureCode\Component\Versionable\Tests\Fixtures\Tag;
use Symfony\Component\Clock\MockClock;

final class VersionableRelationsIntegrationTest extends TestCase
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

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
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

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Category::class),
            $this->entityManager->getClassMetadata(Profile::class),
            $this->entityManager->getClassMetadata(Tag::class),
            $this->entityManager->getClassMetadata(RichArticle::class),
            $this->entityManager->getClassMetadata(Comment::class),
        ]);
    }

    public function testManyToOneAndOneToOneCapturedAsFkColumns(): void
    {
        $category = new Category('news');
        $profile = new Profile('about');
        $article = new RichArticle('hello');

        $this->entityManager->persist($category);
        $this->entityManager->persist($profile);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setCategory($category);
        $article->setProfile($profile);
        $this->entityManager->flush();

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_rich_article_version WHERE entity_id = ? ORDER BY version DESC LIMIT 1',
            [$article->getId()],
        );

        self::assertCount(1, $rows);
        self::assertSame($category->getId(), (int) $rows[0]['category_id']);
        self::assertSame($profile->getId(), (int) $rows[0]['profile_id']);
    }

    public function testManyToOneTargetVersionIsTrackedWhenTargetIsVersionable(): void
    {
        $category = new Category('news');
        $article = new RichArticle('hello');
        $article->setCategory($category);

        $this->entityManager->persist($category);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $category->setName('updates');
        $this->entityManager->flush();

        $article->setTitle('renamed');
        $this->entityManager->flush();

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT category_version FROM versionable_rich_article_version WHERE entity_id = ? ORDER BY version DESC LIMIT 1',
            [$article->getId()],
        );

        self::assertCount(1, $rows);
        self::assertSame(2, (int) $rows[0]['category_version'], 'latest article snapshot pins category at its current (post-update) version');
    }

    public function testOneToManyCollectionSnapshotIntoJoinTable(): void
    {
        $article = new RichArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        new Comment('first', $article);
        new Comment('second', $article);
        $this->entityManager->flush();

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_rich_article_version_comments ORDER BY target_id ASC',
        );

        self::assertCount(2, $rows);
    }

    public function testManyToManyCollectionSnapshotIntoJoinTable(): void
    {
        $tagA = new Tag('a');
        $tagB = new Tag('b');
        $article = new RichArticle('hello');

        $this->entityManager->persist($tagA);
        $this->entityManager->persist($tagB);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->addTag($tagA);
        $article->addTag($tagB);
        $this->entityManager->flush();

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_rich_article_version_tags ORDER BY target_id ASC',
        );

        self::assertCount(2, $rows);
        self::assertSame($tagA->getId(), (int) $rows[0]['target_id']);
        self::assertSame($tagB->getId(), (int) $rows[1]['target_id']);
    }

    public function testCollectionElementRemovalProducesShrunkSnapshot(): void
    {
        $tagA = new Tag('a');
        $tagB = new Tag('b');
        $article = new RichArticle('hello');

        $this->entityManager->persist($tagA);
        $this->entityManager->persist($tagB);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->addTag($tagA);
        $article->addTag($tagB);
        $this->entityManager->flush();

        $article->removeTag($tagA);
        $this->entityManager->flush();

        $versions = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, version FROM versionable_rich_article_version WHERE entity_id = ? ORDER BY version ASC',
            [$article->getId()],
        );
        self::assertCount(3, $versions, 'Three snapshots: insert (v1), add (v2), remove (v3)');

        $v2Rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT target_id FROM versionable_rich_article_version_tags WHERE version_id = ? ORDER BY target_id ASC',
            [$versions[1]['id']],
        );
        self::assertSame(
            [$tagA->getId(), $tagB->getId()],
            array_map(static fn (array $row): int => (int) $row['target_id'], $v2Rows),
            'v2 captures both tags',
        );

        $v3Rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT target_id FROM versionable_rich_article_version_tags WHERE version_id = ?',
            [$versions[2]['id']],
        );
        self::assertSame(
            [$tagB->getId()],
            array_map(static fn (array $row): int => (int) $row['target_id'], $v3Rows),
            'v3 captures only the remaining tag after removeTag(tagA)',
        );
    }
}
