<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\History;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\EntityProxy\Fixtures\Author;
use SoureCode\Component\Versionable\Tests\EntityProxy\Fixtures\Post;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

/**
 * Phase 5 — transitive relation getters on *History. Verifies that the
 * generated *History DTO exposes single-valued and collection-valued
 * versioned associations, each resolved to the partner *History at the
 * recorded `<field>_version`/`target_version`.
 */
final class TransitiveHistoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Versioner $versioner;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../EntityProxy/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        $factory = new VersionableMetadataFactory($this->entityManager);
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, new MockClock('2026-05-28T10:00:00+00:00')),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Author::class),
            $this->entityManager->getClassMetadata(Post::class),
        ]);

        $this->versioner = new Versioner($this->entityManager, $factory);
    }

    public function testPostHistoryHasAuthorGetterReturningAuthorHistory(): void
    {
        $author = new Author('alice');
        $post = new Post('hello');
        $post->setAuthor($author);
        $this->entityManager->persist($author);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $authorVersionAtPostInsert = $author->getVersion();
        $postId = $post->getId();

        $postHistory = $this->versioner->findByVersion(Post::class, $postId, 1);

        self::assertNotNull($postHistory);
        self::assertSame('hello', $postHistory->getTitle());

        self::assertTrue(method_exists($postHistory, 'getAuthor'));

        $authorHistory = $postHistory->getAuthor();

        self::assertNotNull($authorHistory);
        self::assertInstanceOf(Versioner::historyClassFor(Author::class), $authorHistory);
        self::assertSame($authorVersionAtPostInsert, $authorHistory->getVersion(), 'partner *History pinned at the recorded version');
        self::assertSame('alice', $authorHistory->getName());
    }

    public function testTransitiveWalkIsFrozenEvenWhenLiveAuthorBumps(): void
    {
        $author = new Author('alice');
        $post = new Post('hello');
        $post->setAuthor($author);
        $this->entityManager->persist($author);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $authorVersionAtPostInsert = $author->getVersion();
        $postId = $post->getId();

        $author->setName('alice-renamed');
        $this->entityManager->flush();
        self::assertGreaterThan($authorVersionAtPostInsert, $author->getVersion());

        $postHistory = $this->versioner->findByVersion(Post::class, $postId, 1);
        $authorHistory = $postHistory->getAuthor();

        self::assertNotNull($authorHistory);
        self::assertSame($authorVersionAtPostInsert, $authorHistory->getVersion());
        self::assertSame('alice', $authorHistory->getName(), 'history walk reflects the moment of post v=1, not the later renamed live author');
    }

    public function testRelationGetterReturnsNullWhenSourceFieldIsNull(): void
    {
        $post = new Post('hello');
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $postHistory = $this->versioner->findByVersion(Post::class, $post->getId(), 1);

        self::assertNotNull($postHistory);
        self::assertNull($postHistory->getAuthor(), 'relation null at snapshot time → history getter returns null');
    }

    public function testGeneratedHistoryClassHasACollectionGetter(): void
    {
        $author = new Author('alice');
        $this->entityManager->persist($author);
        $this->entityManager->flush();

        $authorHistory = $this->versioner->findByVersion(Author::class, $author->getId(), 1);

        self::assertNotNull($authorHistory);
        self::assertTrue(method_exists($authorHistory, 'getPosts'), 'inverse 1:n collection becomes a getter on *History');

        $posts = $authorHistory->getPosts();
        self::assertIsArray($posts, 'collection getter returns a list of partner *History');
    }
}
