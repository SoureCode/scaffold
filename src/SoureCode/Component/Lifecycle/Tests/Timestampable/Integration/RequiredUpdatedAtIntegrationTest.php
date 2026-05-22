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
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\ArticleWithRequiredUpdatedAt;
use Symfony\Component\Clock\MockClock;

final class RequiredUpdatedAtIntegrationTest extends TestCase
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
            $this->entityManager->getClassMetadata(ArticleWithRequiredUpdatedAt::class),
        ]);
    }

    public function testNonNullableUpdatedAtIsFilledOnPersist(): void
    {
        $article = new ArticleWithRequiredUpdatedAt('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        self::assertEquals($now, $article->getCreatedAt());
        self::assertEquals($now, $article->getUpdatedAt(), '#[UpdatedAt(nullable: false)] must be filled on first persist');
    }
}
