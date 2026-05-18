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
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Fixtures\Article;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

final class VersionerIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MockClock $clock;
    private Versioner $versioner;

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

        $this->versioner = new Versioner($this->entityManager, $metadataFactory);
    }

    public function testFindHistoryReturnsHydratedEntitiesInChronologicalOrder(): void
    {
        $article = new Article('one');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('two');
        $this->entityManager->flush();
        $article->setTitle('three');
        $this->entityManager->flush();

        $history = $this->versioner->findHistory(Article::class, $article->getId());

        self::assertCount(2, $history);
        self::assertInstanceOf(Article::class, $history[0]);
        self::assertSame('two', $history[0]->getTitle());
        self::assertSame('three', $history[1]->getTitle());
    }

    public function testFindByVersionReturnsHydratedEntity(): void
    {
        $article = new Article('alpha');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('beta');
        $this->entityManager->flush();
        $article->setTitle('gamma');
        $this->entityManager->flush();

        $first = $this->versioner->findByVersion(Article::class, $article->getId(), 1);
        self::assertInstanceOf(Article::class, $first);
        self::assertSame('beta', $first->getTitle());

        $second = $this->versioner->findByVersion(Article::class, $article->getId(), 2);
        self::assertInstanceOf(Article::class, $second);
        self::assertSame('gamma', $second->getTitle());

        self::assertNull($this->versioner->findByVersion(Article::class, $article->getId(), 99));
    }

    public function testFindLatestVersionReturnsHighestVersionEntity(): void
    {
        $article = new Article('alpha');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('beta');
        $this->entityManager->flush();
        $article->setTitle('gamma');
        $this->entityManager->flush();

        $latest = $this->versioner->findLatestVersion(Article::class, $article->getId());
        self::assertInstanceOf(Article::class, $latest);
        self::assertSame('gamma', $latest->getTitle());
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

        $this->versioner->applyVersion($article, 1);

        self::assertSame('beta', $article->getTitle());
        self::assertSame('body-beta', $article->getBody());
    }

    public function testApplyVersionThrowsForUnknownVersion(): void
    {
        $article = new Article('alpha');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('beta');
        $this->entityManager->flush();

        $this->expectException(\RuntimeException::class);
        $this->versioner->applyVersion($article, 99);
    }
}
