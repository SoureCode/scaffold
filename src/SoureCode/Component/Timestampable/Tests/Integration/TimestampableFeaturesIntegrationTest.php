<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Timestampable\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\Timestampable\EventListener\TimestampableListener;
use SoureCode\Component\Timestampable\EventListener\TimestampableMappingListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Timestampable\Tests\Fixtures\Address;
use SoureCode\Component\Timestampable\Tests\Fixtures\AutoMappedEntry;
use SoureCode\Component\Timestampable\Tests\Fixtures\Comment;
use SoureCode\Component\Timestampable\Tests\Fixtures\MutableArticle;
use SoureCode\Component\Timestampable\Tests\Fixtures\Place;
use SoureCode\Component\Timestampable\Tests\Fixtures\Topic;
use Symfony\Component\Clock\MockClock;

final class TimestampableFeaturesIntegrationTest extends TestCase
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

        $metadataFactory = new TimestampableMetadataFactory();
        $timestampFactory = new TimestampFactory($this->clock);

        $listener = new TimestampableListener(
            $this->clock,
            $metadataFactory,
            $timestampFactory,
            new ChangeSetMatcher(),
        );
        $mappingListener = new TimestampableMappingListener($metadataFactory);

        $eventManager = $this->entityManager->getEventManager();
        $eventManager->addEventListener([Events::prePersist, Events::onFlush], $listener);
        $eventManager->addEventListener([Events::loadClassMetadata], $mappingListener);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(AutoMappedEntry::class),
            $this->entityManager->getClassMetadata(MutableArticle::class),
            $this->entityManager->getClassMetadata(Place::class),
            $this->entityManager->getClassMetadata(Topic::class),
            $this->entityManager->getClassMetadata(Comment::class),
        ]);
    }

    public function testAutoMappingRegistersColumnsForAttributedPropertiesWithoutOrmColumn(): void
    {
        $classMetadata = $this->entityManager->getClassMetadata(AutoMappedEntry::class);

        self::assertTrue($classMetadata->hasField('createdAt'));
        self::assertTrue($classMetadata->hasField('updatedAt'));

        $createdAt = $classMetadata->getFieldMapping('createdAt');
        self::assertSame('datetimetz_immutable', $createdAt['type']);
        self::assertFalse($createdAt['nullable']);

        $updatedAt = $classMetadata->getFieldMapping('updatedAt');
        self::assertSame('datetimetz_immutable', $updatedAt['type']);
        self::assertTrue($updatedAt['nullable']);
    }

    public function testNullableUpdatedAtStaysNullUntilFirstUpdate(): void
    {
        $entry = new AutoMappedEntry('hello');
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        self::assertNotNull($entry->getCreatedAt());
        self::assertNull($entry->getUpdatedAt());

        $this->clock->modify('+1 hour');
        $entry->setTitle('changed');
        $this->entityManager->flush();

        self::assertNotNull($entry->getUpdatedAt());
    }

    public function testMutableTypePropertyReceivesDateTimeInstance(): void
    {
        $article = new MutableArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        self::assertInstanceOf(\DateTime::class, $article->getCreatedAt());
        self::assertNull($article->getUpdatedAt());

        $this->clock->modify('+1 hour');
        $article->setTitle('changed');
        $this->entityManager->flush();

        self::assertInstanceOf(\DateTime::class, $article->getUpdatedAt());
    }

    public function testEmbeddableDottedPathTriggersChangedAt(): void
    {
        $place = new Place('shop', new Address('berlin'));
        $this->entityManager->persist($place);
        $this->entityManager->flush();
        self::assertNull($place->getRelocatedAt());

        $this->clock->modify('+1 hour');
        $place->getAddress()->setCity('munich');
        $this->entityManager->flush();

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $place->getRelocatedAt(),
        );
    }

    public function testRelationDottedPathTriggersChangedAtViaUnitOfWork(): void
    {
        $topic = new Topic('original');
        $comment = new Comment('hi', $topic);
        $this->entityManager->persist($topic);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();
        self::assertNull($comment->getTopicRetitledAt());

        $this->clock->modify('+1 hour');
        $topic->setTitle('renamed');
        $this->entityManager->flush();

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $comment->getTopicRetitledAt(),
        );
    }
}
