<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Lifecycle\Clock\TimestampFactory;
use SoureCode\Component\Lifecycle\EventListener\TimestampableListener;
use SoureCode\Component\Lifecycle\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\InterfaceArticle;
use Symfony\Component\Clock\MockClock;

final class InterfaceFallbackIntegrationTest extends TestCase
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
        $this->clock = new MockClock('2026-05-19T12:00:00+00:00');

        $listener = new TimestampableListener(
            new TimestampableMetadataFactory(),
            new TimestampFactory($this->clock),
            new ChangeSetMatcher(),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(InterfaceArticle::class),
        ]);
    }

    public function testInterfaceFallbackFillsCreatedAndUpdatedOnPersist(): void
    {
        $article = new InterfaceArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        self::assertEquals($now, $article->getCreatedAt());
        self::assertEquals($now, $article->getUpdatedAt());
    }

    public function testInterfaceFallbackRefreshesUpdatedAtOnUpdate(): void
    {
        $article = new InterfaceArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $createdAt = $article->getCreatedAt();
        $this->clock->modify('+1 hour');

        $article->setTitle('changed');
        $this->entityManager->flush();

        self::assertEquals($createdAt, $article->getCreatedAt(), 'createdAt is never overwritten');
        $expectedUpdated = \DateTimeImmutable::createFromInterface($this->clock->now());
        self::assertEquals($expectedUpdated, $article->getUpdatedAt(), 'updatedAt advances to the new clock reading');
    }
}
