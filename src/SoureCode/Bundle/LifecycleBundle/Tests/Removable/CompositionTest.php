<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Removable;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\LifecycleBundle\LifecycleBundle;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures\User;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Support\FixedAuthorProvider;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\LifecycleBundle\Tests\Removable\Fixtures\FullyDecoratedArticle;
use SoureCode\Bundle\VersionableBundle\VersionableBundle;
use SoureCode\Component\Lifecycle\RemoverInterface;
use SoureCode\Component\Versionable\VersionerInterface;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Exercises Timestampable + Authorable + Removable + Versionable on one entity
 * to catch listener-ordering and changeset-recomputation regressions that
 * single-bundle tests cannot see.
 */
final class CompositionTest extends AbstractBundleTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var TestKernel $kernel */
        $kernel = parent::createKernel($options);
        $kernel->addTestBundle(DoctrineBundle::class);
        $kernel->addTestBundle(SecurityBundle::class);
        $kernel->addTestBundle(DoctrineExtensionsBundle::class);
        $kernel->addTestBundle(LifecycleBundle::class);
        $kernel->addTestBundle(VersionableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/composition.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testAllBehaviorsFireInOneTransaction(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(FullyDecoratedArticle::class),
        ]);

        $author = new User('alice');
        $entityManager->persist($author);
        $entityManager->flush();

        $provider = $container->get('app.author_provider');
        self::assertInstanceOf(FixedAuthorProvider::class, $provider);
        $provider->setAuthor($author);

        $article = new FullyDecoratedArticle('first');
        $entityManager->persist($article);
        $entityManager->flush();

        self::assertNotNull($article->getCreatedAt(), 'Timestampable: CreatedAt stamped on persist');
        self::assertSame($author, $article->getCreatedBy(), 'Authorable: CreatedBy stamped on persist');
        self::assertNull($article->getDeletedAt());

        $article->title = 'second';
        $entityManager->flush();

        self::assertNotNull($article->getUpdatedAt(), 'Timestampable: UpdatedAt stamped on update');
        self::assertSame($author, $article->getUpdatedBy(), 'Authorable: UpdatedBy stamped on update');

        self::assertSame(2, $article->version, 'Versionable: live in-memory version is bumped past the recompute that Timestampable triggers');
        $liveVersion = (int) $entityManager->getConnection()->fetchOne(
            'SELECT version FROM composition_article WHERE id = ?',
            [$article->id],
        );
        self::assertSame(2, $liveVersion, 'Versionable: live DB version column is bumped (extra-update fires alongside the main UPDATE)');

        $versioner = $container->get(VersionerInterface::class);
        $history = $versioner->findHistory(FullyDecoratedArticle::class, $article->id);
        self::assertCount(2, $history, 'Versionable: insert (v=1) + update (v=2) snapshots');
        self::assertSame('first', $history[0]->title, 'v=1 captures the insert state');
        self::assertSame('second', $history[1]->title, 'v=2 captures the post-update state');

        $container->get(RemoverInterface::class)->remove($article);
        self::assertNotNull($article->getDeletedAt(), 'Removable: DeletedAt stamped on soft delete');
    }

    public function testSecondUpdateAfterEmClearAndReloadBumpsLiveVersionAndAppendsSnapshot(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(FullyDecoratedArticle::class),
        ]);

        $author = new User('alice');
        $entityManager->persist($author);
        $entityManager->flush();

        $provider = $container->get('app.author_provider');
        self::assertInstanceOf(FixedAuthorProvider::class, $provider);
        $provider->setAuthor($author);

        $article = new FullyDecoratedArticle('first');
        $entityManager->persist($article);
        $entityManager->flush();
        $articleId = $article->id;

        // Reload — exact pattern of "load same task, mutate, flush" reported in the wild.
        $authorId = $author->id;
        $entityManager->clear();

        $refreshedAuthor = $entityManager->find(User::class, $authorId);
        self::assertNotNull($refreshedAuthor);
        $provider->setAuthor($refreshedAuthor);

        $reloaded = $entityManager->find(FullyDecoratedArticle::class, $articleId);
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->version, 'reloaded entity carries the v=1 it was persisted with');

        $reloaded->title = 'second';
        $entityManager->flush();

        self::assertSame(2, $reloaded->version, 'live in-memory version is bumped after the reloaded-mutate-flush cycle');
        $liveVersion = (int) $entityManager->getConnection()->fetchOne(
            'SELECT version FROM composition_article WHERE id = ?',
            [$articleId],
        );
        self::assertSame(2, $liveVersion, 'live DB version column is bumped (extra-update fires alongside the main UPDATE)');

        $snapshotVersions = $entityManager->getConnection()->fetchFirstColumn(
            'SELECT version FROM composition_article_version WHERE entity_id = ? ORDER BY version',
            [$articleId],
        );
        self::assertSame([1, 2], array_map('intval', $snapshotVersions), 'snapshot table grows from v=1 to v=2 — no unique-index collision');
    }
}
