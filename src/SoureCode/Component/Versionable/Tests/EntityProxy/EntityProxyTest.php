<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\EntityProxy;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableClassMetadataListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Internal\History\EntityProxyFactory;
use SoureCode\Component\Versionable\Internal\History\EntityProxyGenerator;
use SoureCode\Component\Versionable\Internal\History\EntityProxyNamer;
use SoureCode\Component\Versionable\Internal\History\HistoryClassFactory;
use SoureCode\Component\Versionable\Internal\History\HistoryClassGenerator;
use SoureCode\Component\Versionable\Internal\History\HistoryHydrator;
use SoureCode\Component\Versionable\Internal\History\HistoryRegistry;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\EntityProxy\Fixtures\Author;
use SoureCode\Component\Versionable\Tests\EntityProxy\Fixtures\Post;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

/**
 * Phase 4 — the entity proxy. Verifies that `$em->find(Post::class, $id)`
 * returns an instance of the runtime-generated proxy subclass and that
 * `$post->getAuthorHistory()` resolves the pinned `*History` instance.
 */
final class EntityProxyTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'sourecode-versionable-proxy-' . uniqid('', true);

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        $factory = new VersionableMetadataFactory();
        $clock = new MockClock('2026-05-28T10:00:00+00:00');

        $historyClassFactory = new HistoryClassFactory(
            new HistoryClassGenerator($factory, $this->entityManager),
            $this->cacheDir,
        );
        $hydrator = new HistoryHydrator($this->entityManager, $factory, $historyClassFactory);

        $entityProxyFactory = new EntityProxyFactory(
            new EntityProxyGenerator($factory),
            $this->cacheDir,
        );

        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, $clock),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [Events::loadClassMetadata],
            new VersionableClassMetadataListener($entityProxyFactory, $factory, $hydrator),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Author::class),
            $this->entityManager->getClassMetadata(Post::class),
        ]);
    }

    protected function tearDown(): void
    {
        HistoryRegistry::unbind();

        if (is_dir($this->cacheDir)) {
            foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->cacheDir);
        }
    }

    public function testLoadedEntityIsAnInstanceOfTheGeneratedProxy(): void
    {
        $author = new Author('alice');
        $post = new Post('hello');
        $post->setAuthor($author);
        $this->entityManager->persist($author);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $postId = $post->getId();
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(Post::class, $postId);

        self::assertNotNull($loaded);
        self::assertInstanceOf(Post::class, $loaded, 'proxy is still IS-A Post');
        self::assertInstanceOf(EntityProxyNamer::proxyClassFor(Post::class), $loaded, 'concrete class is the generated proxy subclass');
        self::assertTrue(method_exists($loaded, 'getAuthorHistory'), 'proxy carries get<Field>History() per versioned relation');
    }

    public function testGetAuthorHistoryReturnsTheHistoryAtThePinnedVersion(): void
    {
        $author = new Author('alice');
        $post = new Post('hello');
        $post->setAuthor($author);
        $this->entityManager->persist($author);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $postId = $post->getId();
        $pinnedAuthorVersion = $author->getVersion();

        $this->entityManager->clear();

        $loaded = $this->entityManager->find(Post::class, $postId);
        self::assertNotNull($loaded);

        $history = $loaded->getAuthorHistory();

        self::assertNotNull($history);
        self::assertInstanceOf(Versioner::historyClassFor(Author::class), $history);
        self::assertSame($pinnedAuthorVersion, $history->getVersion());
        self::assertSame('alice', $history->getName());
    }

    public function testPinIsFrozen_authorBumpsIndependentlyAfterPostFlush(): void
    {
        $author = new Author('alice');
        $post = new Post('hello');
        $post->setAuthor($author);
        $this->entityManager->persist($author);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $pinnedAuthorVersion = $author->getVersion();

        // Author bumps independently (post is not re-flushed).
        $author->setName('alice-renamed');
        $this->entityManager->flush();
        self::assertGreaterThan($pinnedAuthorVersion, $author->getVersion(), 'author advanced past the pinned version');

        $postId = $post->getId();
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(Post::class, $postId);
        self::assertNotNull($loaded);

        $history = $loaded->getAuthorHistory();

        self::assertNotNull($history);
        self::assertSame($pinnedAuthorVersion, $history->getVersion(), 'pin is frozen at the version the post last saw');
        self::assertSame('alice', $history->getName(), 'history reflects the pinned moment, not the later renamed live author');
    }

    public function testGetAuthorHistoryReturnsNullWhenRelationIsNull(): void
    {
        $post = new Post('hello');
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $postId = $post->getId();
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(Post::class, $postId);
        self::assertNotNull($loaded);

        self::assertNull($loaded->getAuthorHistory());
    }

    public function testProxyClassIsOnlyGeneratedForEntitiesWithVersionedSingleValuedRelations(): void
    {
        $authorProxy = EntityProxyNamer::proxyClassFor(Author::class);

        self::assertFalse(class_exists($authorProxy, false), 'Author has no versioned single-valued relations — no proxy needed');

        // Trigger metadata load for Author explicitly to confirm.
        $loaded = $this->entityManager->getClassMetadata(Author::class);
        self::assertSame(Author::class, $loaded->reflClass->getName(), 'Author reflClass is unchanged');
    }
}
