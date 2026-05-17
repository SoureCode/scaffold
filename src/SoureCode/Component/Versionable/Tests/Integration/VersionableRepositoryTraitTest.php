<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Repository\VersionableRepositoryTrait;
use SoureCode\Component\Versionable\Tests\Fixtures\Article;
use Symfony\Component\Clock\MockClock;

final class VersionableRepositoryTraitTest extends TestCase
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

        $metadataFactory = new VersionableMetadataFactory();
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            new VersionableListener($metadataFactory, $this->clock),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($metadataFactory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Article::class),
        ]);
    }

    public function testFindHistoryReturnsRowsInChronologicalOrder(): void
    {
        $article = new Article('one');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('two');
        $this->entityManager->flush();
        $article->setTitle('three');
        $this->entityManager->flush();

        $repository = $this->buildRepository();
        $history = $repository->findHistory($article->getId());

        self::assertCount(2, $history);
        self::assertSame('two', $history[0]['title']);
        self::assertSame('three', $history[1]['title']);
    }

    public function testFindByVersionReturnsSingleRow(): void
    {
        $article = new Article('alpha');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('beta');
        $this->entityManager->flush();
        $article->setTitle('gamma');
        $this->entityManager->flush();

        $repository = $this->buildRepository();

        self::assertSame('beta', $repository->findByVersion($article->getId(), 1)['title'] ?? null);
        self::assertSame('gamma', $repository->findByVersion($article->getId(), 2)['title'] ?? null);
        self::assertNull($repository->findByVersion($article->getId(), 99));
    }

    public function testFindLatestVersionReturnsHighestVersionRow(): void
    {
        $article = new Article('alpha');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('beta');
        $this->entityManager->flush();
        $article->setTitle('gamma');
        $this->entityManager->flush();

        $latest = $this->buildRepository()->findLatestVersion($article->getId());
        self::assertSame('gamma', $latest['title'] ?? null);
        self::assertSame(2, (int) ($latest['version'] ?? 0));
    }

    public function testApplyVersionMutatesEntityInPlace(): void
    {
        $article = new Article('alpha');
        $article->setBody('body-alpha');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('beta');
        $article->setBody('body-beta');
        $this->entityManager->flush();
        $article->setTitle('gamma');
        $article->setBody('body-gamma');
        $this->entityManager->flush();

        $reflection = new \ReflectionClass($article);

        $this->buildRepository()->applyVersion($article, 1);

        self::assertSame('beta', $reflection->getProperty('title')->getValue($article));
        self::assertSame('body-beta', $reflection->getProperty('body')->getValue($article));
    }

    public function testApplyVersionThrowsForUnknownVersion(): void
    {
        $article = new Article('alpha');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('beta');
        $this->entityManager->flush();

        $this->expectException(\RuntimeException::class);
        $this->buildRepository()->applyVersion($article, 99);
    }

    private function buildRepository(): object
    {
        return new class($this->entityManager, $this->entityManager->getClassMetadata(Article::class)) extends EntityRepository {
            use VersionableRepositoryTrait;
        };
    }
}
