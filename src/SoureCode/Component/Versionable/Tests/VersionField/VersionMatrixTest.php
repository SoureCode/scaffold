<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\VersionField;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Badge;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Geo;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Owner;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\ProbeStatus;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Subject;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Tag;
use Symfony\Component\Clock\MockClock;

final class VersionMatrixTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        $factory = new VersionableMetadataFactory();
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, new MockClock('2026-05-26T10:00:00+00:00')),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Subject::class),
            $this->entityManager->getClassMetadata(Owner::class),
            $this->entityManager->getClassMetadata(Badge::class),
            $this->entityManager->getClassMetadata(Tag::class),
        ]);
    }

    private function persistedSubject(): Subject
    {
        $subject = new Subject('hello', new Geo(1.0, 2.0));
        $this->entityManager->persist($subject);
        $this->entityManager->flush();

        return $subject;
    }

    public function testScalarChangeBumpsVersion(): void
    {
        $subject = $this->persistedSubject();

        $subject->setTitle('renamed');
        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion());
    }

    public function testEnumChangeBumpsVersion(): void
    {
        $subject = $this->persistedSubject();

        $subject->setStatus(ProbeStatus::Published);
        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion());
    }

    public function testEmbeddedChangeBumpsVersion(): void
    {
        $subject = $this->persistedSubject();

        $subject->setGeo(new Geo(9.0, 9.0));
        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion());
    }

    public function testManyToOneChangeBumpsBothSides(): void
    {
        $subject = $this->persistedSubject();

        $owner = new Owner('acme');
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        $subject->setOwner($owner);
        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion(), 'owning n:1 side');
        self::assertSame(1, $owner->getVersion(), 'inverse 1:n side');
    }

    public function testOneToOneChangeBumpsBothSides(): void
    {
        $subject = $this->persistedSubject();

        $badge = new Badge('A1');
        $this->entityManager->persist($badge);
        $this->entityManager->flush();

        $subject->setBadge($badge);
        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion(), 'owning 1:1 side');
        self::assertSame(1, $badge->getVersion(), 'inverse 1:1 side');
    }

    public function testManyToManyChangeBumpsBothSides(): void
    {
        $subject = $this->persistedSubject();

        $tag = new Tag('news');
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        $subject->addTag($tag);
        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion(), 'owning n:m endpoint');
        self::assertSame(1, $tag->getVersion(), 'inverse n:m endpoint (element)');
    }

    public function testMixedChangeBumpsExactlyOnce(): void
    {
        $subject = $this->persistedSubject();

        $tag = new Tag('news');
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        $subject->setTitle('renamed');
        $subject->addTag($tag);
        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion(), 'scalar + collection in one flush is one bump');
    }

    public function testInsertDoesNotBump(): void
    {
        $subject = $this->persistedSubject();

        self::assertSame(0, $subject->getVersion());
    }

    public function testEmptyReflushDoesNotBump(): void
    {
        $subject = $this->persistedSubject();

        $subject->setTitle('renamed');
        $this->entityManager->flush();
        self::assertSame(1, $subject->getVersion());

        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion(), 'a flush with no change must not bump (stability)');
    }

    public function testInverseBumpIsStableAndIsolated(): void
    {
        $subject = $this->persistedSubject();
        $owner = new Owner('acme');
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        $subject->setOwner($owner);
        $this->entityManager->flush();
        self::assertSame(1, $subject->getVersion());
        self::assertSame(1, $owner->getVersion());

        $this->entityManager->flush();
        self::assertSame(1, $owner->getVersion(), 'owner stable on a no-op flush');

        $subject->setTitle('renamed');
        $this->entityManager->flush();

        self::assertSame(2, $subject->getVersion());
        self::assertSame(1, $owner->getVersion(), 'owner is not bumped by an unrelated change on subject (isolation)');
    }

    public function testAllRelationsInOneFlushBumpEachExactlyOnce(): void
    {
        $subject = $this->persistedSubject();
        $owner = new Owner('acme');
        $badge = new Badge('A1');
        $tag = new Tag('news');
        $this->entityManager->persist($owner);
        $this->entityManager->persist($badge);
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        $subject->setTitle('renamed');
        $subject->setOwner($owner);
        $subject->setBadge($badge);
        $subject->addTag($tag);
        $this->entityManager->flush();

        self::assertSame(1, $subject->getVersion(), 'subject bumps once for all of its changes');
        self::assertSame(1, $owner->getVersion());
        self::assertSame(1, $badge->getVersion());
        self::assertSame(1, $tag->getVersion());
    }

    public function testManyToOneRetargetBumpsOldAndNewOwner(): void
    {
        $subject = $this->persistedSubject();
        $first = new Owner('first');
        $second = new Owner('second');
        $this->entityManager->persist($first);
        $this->entityManager->persist($second);
        $this->entityManager->flush();

        $subject->setOwner($first);
        $this->entityManager->flush();
        self::assertSame(1, $first->getVersion());

        $subject->setOwner($second);
        $this->entityManager->flush();

        self::assertSame(2, $subject->getVersion());
        self::assertSame(2, $first->getVersion(), 'old owner bumps — it lost the subject');
        self::assertSame(1, $second->getVersion(), 'new owner bumps — it gained the subject');
    }

    public function testManyToOneSetNullBumpsOldOwner(): void
    {
        $subject = $this->persistedSubject();
        $owner = new Owner('acme');
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        $subject->setOwner($owner);
        $this->entityManager->flush();
        self::assertSame(1, $owner->getVersion());

        $subject->setOwner(null);
        $this->entityManager->flush();

        self::assertSame(2, $subject->getVersion());
        self::assertSame(2, $owner->getVersion(), 'detached owner bumps');
    }

    public function testManyToManyRemoveBumpsElement(): void
    {
        $subject = $this->persistedSubject();
        $tag = new Tag('news');
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        $subject->addTag($tag);
        $this->entityManager->flush();
        self::assertSame(1, $tag->getVersion());

        $subject->removeTag($tag);
        $this->entityManager->flush();

        self::assertSame(2, $subject->getVersion());
        self::assertSame(2, $tag->getVersion(), 'removed element bumps');
    }
}
