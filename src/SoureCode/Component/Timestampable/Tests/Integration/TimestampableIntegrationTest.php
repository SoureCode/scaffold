<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Timestampable\EventListener\TimestampableListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Tests\Fixtures\Article;
use Symfony\Component\Clock\MockClock;

final class TimestampableIntegrationTest extends TestCase
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

        $listener = new TimestampableListener($this->clock, new TimestampableMetadataFactory(), new TimestampFactory($this->clock), new ChangeSetMatcher());
        $this->entityManager->getEventManager()->addEventListener([Events::prePersist, Events::onFlush], $listener);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([$this->entityManager->getClassMetadata(Article::class)]);
    }

    public function testPersistSetsCreatedAtAndLeavesUpdatedAtNull(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $expected = \DateTimeImmutable::createFromInterface($this->clock->now());
        self::assertEquals($expected, $article->getCreatedAt());
        self::assertNull($article->getUpdatedAt());
    }

    public function testUpdateRefreshesUpdatedAtOnly(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $createdAt = $article->getCreatedAt();
        $initialUpdatedAt = $article->getUpdatedAt();

        $this->clock->modify('+1 hour');
        $article->setTitle('changed');
        $this->entityManager->flush();

        self::assertEquals($createdAt, $article->getCreatedAt());
        self::assertNotEquals($initialUpdatedAt, $article->getUpdatedAt());
        self::assertEquals(
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $article->getUpdatedAt(),
        );
    }
}
