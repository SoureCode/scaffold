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
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\Channel;
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\Hub;
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\Node;
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\UnixEntry;
use Symfony\Component\Clock\MockClock;

final class TimestampableExtendedIntegrationTest extends TestCase
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
            $this->entityManager->getClassMetadata(Hub::class),
            $this->entityManager->getClassMetadata(Channel::class),
            $this->entityManager->getClassMetadata(Node::class),
            $this->entityManager->getClassMetadata(UnixEntry::class),
        ]);
    }

    public function testInverseSideCollectionTraversalFires(): void
    {
        $hub = new Hub('central');
        $channel = new Channel('news', $hub);

        $this->entityManager->persist($hub);
        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        self::assertNull($hub->getLastChannelTitleChangedAt());

        $this->clock->modify('+1 hour');
        $channel->setTitle('updates');
        $this->entityManager->flush();

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $hub->getLastChannelTitleChangedAt(),
        );
    }

    public function testCycleProtectionPreventsInfiniteLoop(): void
    {
        $a = new Node('a');
        $b = new Node('b');
        $a->setParent($b);
        $b->setParent($a);

        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $this->clock->modify('+1 hour');
        $b->setLabel('renamed');
        $this->entityManager->flush();

        self::assertTrue(true);
    }

    public function testIntegerColumnTypeWritesUnixTimestamp(): void
    {
        $entry = new UnixEntry('hello');
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $expectedUnix = (int) $this->clock->now()->format('U');
        self::assertSame($expectedUnix, $entry->getCreatedAt());
        self::assertNull($entry->getUpdatedAt());

        $this->clock->modify('+1 hour');
        $entry->setTitle('changed');
        $this->entityManager->flush();

        self::assertSame((int) $this->clock->now()->format('U'), $entry->getUpdatedAt());
    }
}
