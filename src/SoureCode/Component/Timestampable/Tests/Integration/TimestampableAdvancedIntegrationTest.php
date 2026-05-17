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
use SoureCode\Component\Timestampable\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\Timestampable\EventListener\TimestampableListener;
use SoureCode\Component\Timestampable\EventListener\TimestampableMappingListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Timestampable\Tests\Fixtures\Comment;
use SoureCode\Component\Timestampable\Tests\Fixtures\Department;
use SoureCode\Component\Timestampable\Tests\Fixtures\Document;
use SoureCode\Component\Timestampable\Tests\Fixtures\Person;
use SoureCode\Component\Timestampable\Tests\Fixtures\Tag;
use SoureCode\Component\Timestampable\Tests\Fixtures\TaggedItem;
use SoureCode\Component\Timestampable\Tests\Fixtures\Topic;
use Symfony\Component\Clock\MockClock;

final class TimestampableAdvancedIntegrationTest extends TestCase
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
            $this->entityManager->getClassMetadata(Department::class),
            $this->entityManager->getClassMetadata(Person::class),
            $this->entityManager->getClassMetadata(Document::class),
            $this->entityManager->getClassMetadata(Tag::class),
            $this->entityManager->getClassMetadata(TaggedItem::class),
            $this->entityManager->getClassMetadata(Topic::class),
            $this->entityManager->getClassMetadata(Comment::class),
        ]);
    }

    public function testMultiLevelDottedPathFires(): void
    {
        $department = new Department('R&D');
        $person = new Person('alice', $department);
        $document = new Document('spec', $person);

        $this->entityManager->persist($department);
        $this->entityManager->persist($person);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        self::assertNull($document->getDeptCodeChangedAt());

        $this->clock->modify('+1 hour');
        $department->setCode('PROD');
        $this->entityManager->flush();

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $document->getDeptCodeChangedAt(),
        );
    }

    public function testRelatedDeletionFiresWatcher(): void
    {
        $topic = new Topic('original');
        $comment = new Comment('hi', $topic);

        $this->entityManager->persist($topic);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        self::assertNull($comment->getTopicRetitledAt());

        $this->clock->modify('+1 hour');
        $this->entityManager->remove($topic);
        $this->entityManager->flush();

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $comment->getTopicRetitledAt(),
        );
    }

    public function testRelatedInsertionFiresWatcherWhenRelationPointerChanges(): void
    {
        $originalTopic = new Topic('original');
        $comment = new Comment('hi', $originalTopic);

        $this->entityManager->persist($originalTopic);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->clock->modify('+1 hour');
        $newTopic = new Topic('replacement');
        $comment->setTopic($newTopic);
        $this->entityManager->persist($newTopic);
        $this->entityManager->flush();

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $comment->getTopicRetitledAt(),
        );
    }

    public function testCollectionAddFiresWatcher(): void
    {
        $item = new TaggedItem('article');
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        self::assertNull($item->getTagsChangedAt());

        $this->clock->modify('+1 hour');
        $tag = new Tag('news');
        $this->entityManager->persist($tag);
        $item->addTag($tag);
        $this->entityManager->flush();

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $item->getTagsChangedAt(),
        );
    }
}
