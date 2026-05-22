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
use SoureCode\Component\Lifecycle\EventListener\TimestampableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\ArticleWithPredeclaredColumn;
use Symfony\Component\Clock\MockClock;

final class PredeclaredColumnIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;

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
        $clock = new MockClock('2026-05-19T12:00:00+00:00');

        $metadataFactory = new TimestampableMetadataFactory();
        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            new TimestampableListener($metadataFactory, new TimestampFactory($clock), new ChangeSetMatcher()),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [Events::loadClassMetadata],
            new TimestampableMappingListener($metadataFactory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(ArticleWithPredeclaredColumn::class),
        ]);
    }

    public function testAutoMappingSkipsPredeclaredOrmColumn(): void
    {
        $classMetadata = $this->entityManager->getClassMetadata(ArticleWithPredeclaredColumn::class);
        $mapping = $classMetadata->getFieldMapping('createdAt');

        self::assertSame('created_at_custom', $mapping->columnName, 'Pre-declared column name must survive the mapping listener');
        self::assertTrue($mapping->nullable ?? false, 'Pre-declared nullable=true must survive the mapping listener default of nullable=false for #[CreatedAt]');
    }
}
