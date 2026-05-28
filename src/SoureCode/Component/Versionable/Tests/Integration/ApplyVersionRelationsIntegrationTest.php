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
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

final class ApplyVersionRelationsIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Versioner $versioner;

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

        $metadataFactory = new VersionableMetadataFactory($this->entityManager);
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($metadataFactory, $clock),
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

        $this->versioner = new Versioner($this->entityManager, $metadataFactory);
    }

    public function testApplyVersionRestoresSingleCardAssociation(): void
    {
        $news = new Category('news');
        $updates = new Category('updates');
        $article = new RichArticle('hello');

        $this->entityManager->persist($news);
        $this->entityManager->persist($updates);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setCategory($news);
        $article->setTitle('v2');
        $this->entityManager->flush();

        $article->setCategory($updates);
        $article->setTitle('v3');
        $this->entityManager->flush();

        $this->versioner->applyVersion($article, 2);

        $reflection = new \ReflectionProperty(RichArticle::class, 'category');
        $restored = $reflection->getValue($article);

        self::assertInstanceOf(Category::class, $restored);
        self::assertSame($news->getId(), $restored->getId(), 'Version 2 captured category=news; applyVersion reattaches via EntityManager::find');
    }

    public function testApplyVersionRestoresManyToManyCollection(): void
    {
        $tagA = new Tag('a');
        $tagB = new Tag('b');
        $tagC = new Tag('c');
        $article = new RichArticle('hello');

        $this->entityManager->persist($tagA);
        $this->entityManager->persist($tagB);
        $this->entityManager->persist($tagC);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->addTag($tagA);
        $article->addTag($tagB);
        $article->setTitle('v2');
        $this->entityManager->flush();

        $article->removeTag($tagA);
        $article->addTag($tagC);
        $article->setTitle('v3');
        $this->entityManager->flush();

        $this->versioner->applyVersion($article, 2);

        $reflection = new \ReflectionProperty(RichArticle::class, 'tags');
        /** @var \Doctrine\Common\Collections\Collection<int, Tag> $tags */
        $tags = $reflection->getValue($article);

        self::assertCount(2, $tags);
        $ids = array_map(static fn (Tag $tag): int => $tag->getId(), $tags->toArray());
        sort($ids);
        $expected = [$tagA->getId(), $tagB->getId()];
        sort($expected);
        self::assertSame($expected, $ids, 'Version 2 captured the {A, B} tag set');
    }
}
