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

    public function testOneToOneRetargetBumpsOldAndNewBadge(): void // #8
    {
        $subject = $this->persistedSubject();
        $first = new Badge('A1');
        $second = new Badge('B2');
        $this->entityManager->persist($first);
        $this->entityManager->persist($second);
        $this->entityManager->flush();

        $subject->setBadge($first);
        $this->entityManager->flush();
        self::assertSame(1, $first->getVersion());

        $subject->setBadge($second);
        $this->entityManager->flush();

        self::assertSame(2, $subject->getVersion());
        self::assertSame(2, $first->getVersion(), 'old badge bumps — it lost the subject');
        self::assertSame(1, $second->getVersion(), 'new badge bumps — it gained the subject');
    }

    public function testOneToOneSetNullBumpsOldBadge(): void // #9
    {
        $subject = $this->persistedSubject();
        $badge = new Badge('A1');
        $this->entityManager->persist($badge);
        $this->entityManager->flush();

        $subject->setBadge($badge);
        $this->entityManager->flush();
        self::assertSame(1, $badge->getVersion());

        $subject->setBadge(null);
        $this->entityManager->flush();

        self::assertSame(2, $subject->getVersion());
        self::assertSame(2, $badge->getVersion(), 'detached badge bumps');
    }

    public function testManyToManyClearBumpsEachRemovedElement(): void // #12
    {
        $subject = $this->persistedSubject();
        $tagOne = new Tag('news');
        $tagTwo = new Tag('sports');
        $this->entityManager->persist($tagOne);
        $this->entityManager->persist($tagTwo);
        $this->entityManager->flush();

        $subject->addTag($tagOne);
        $subject->addTag($tagTwo);
        $this->entityManager->flush();
        self::assertSame(1, $subject->getVersion());
        self::assertSame(1, $tagOne->getVersion());
        self::assertSame(1, $tagTwo->getVersion());

        $subject->removeTag($tagOne);
        $subject->removeTag($tagTwo);
        $this->entityManager->flush();

        self::assertSame(2, $subject->getVersion());
        self::assertSame(2, $tagOne->getVersion(), 'each cleared element bumps');
        self::assertSame(2, $tagTwo->getVersion());
    }

    public function testScalarAndEnumBumpSubjectOnce(): void // #13 a+b
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject): void {
                $subject->setTitle('renamed');
                $subject->setStatus(ProbeStatus::Published);
            },
            ['Subject' => $subject],
            ['Owner' => $owner, 'Badge' => $badge, 'Tag' => $tag],
        );
    }

    public function testScalarAndEmbeddedBumpSubjectOnce(): void // #14 a+c
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject): void {
                $subject->setTitle('renamed');
                $subject->setGeo(new Geo(9.0, 9.0));
            },
            ['Subject' => $subject],
            ['Owner' => $owner, 'Badge' => $badge, 'Tag' => $tag],
        );
    }

    public function testScalarAndOwnerBumpSubjectAndOwner(): void // #15 a+d
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $owner): void {
                $subject->setTitle('renamed');
                $subject->setOwner($owner);
            },
            ['Subject' => $subject, 'Owner' => $owner],
            ['Badge' => $badge, 'Tag' => $tag],
        );
    }

    public function testScalarAndBadgeBumpSubjectAndBadge(): void // #16 a+e
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $badge): void {
                $subject->setTitle('renamed');
                $subject->setBadge($badge);
            },
            ['Subject' => $subject, 'Badge' => $badge],
            ['Owner' => $owner, 'Tag' => $tag],
        );
    }

    public function testEnumAndEmbeddedBumpSubjectOnce(): void // #18 b+c
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject): void {
                $subject->setStatus(ProbeStatus::Published);
                $subject->setGeo(new Geo(9.0, 9.0));
            },
            ['Subject' => $subject],
            ['Owner' => $owner, 'Badge' => $badge, 'Tag' => $tag],
        );
    }

    public function testEnumAndOwnerBumpSubjectAndOwner(): void // #19 b+d
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $owner): void {
                $subject->setStatus(ProbeStatus::Published);
                $subject->setOwner($owner);
            },
            ['Subject' => $subject, 'Owner' => $owner],
            ['Badge' => $badge, 'Tag' => $tag],
        );
    }

    public function testEnumAndBadgeBumpSubjectAndBadge(): void // #20 b+e
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $badge): void {
                $subject->setStatus(ProbeStatus::Published);
                $subject->setBadge($badge);
            },
            ['Subject' => $subject, 'Badge' => $badge],
            ['Owner' => $owner, 'Tag' => $tag],
        );
    }

    public function testEnumAndTagBumpSubjectAndTag(): void // #21 b+f
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $tag): void {
                $subject->setStatus(ProbeStatus::Published);
                $subject->addTag($tag);
            },
            ['Subject' => $subject, 'Tag' => $tag],
            ['Owner' => $owner, 'Badge' => $badge],
        );
    }

    public function testEmbeddedAndOwnerBumpSubjectAndOwner(): void // #22 c+d
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $owner): void {
                $subject->setGeo(new Geo(9.0, 9.0));
                $subject->setOwner($owner);
            },
            ['Subject' => $subject, 'Owner' => $owner],
            ['Badge' => $badge, 'Tag' => $tag],
        );
    }

    public function testEmbeddedAndBadgeBumpSubjectAndBadge(): void // #23 c+e
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $badge): void {
                $subject->setGeo(new Geo(9.0, 9.0));
                $subject->setBadge($badge);
            },
            ['Subject' => $subject, 'Badge' => $badge],
            ['Owner' => $owner, 'Tag' => $tag],
        );
    }

    public function testEmbeddedAndTagBumpSubjectAndTag(): void // #24 c+f
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $tag): void {
                $subject->setGeo(new Geo(9.0, 9.0));
                $subject->addTag($tag);
            },
            ['Subject' => $subject, 'Tag' => $tag],
            ['Owner' => $owner, 'Badge' => $badge],
        );
    }

    public function testOwnerAndBadgeBumpSubjectOwnerBadge(): void // #25 d+e
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $owner, $badge): void {
                $subject->setOwner($owner);
                $subject->setBadge($badge);
            },
            ['Subject' => $subject, 'Owner' => $owner, 'Badge' => $badge],
            ['Tag' => $tag],
        );
    }

    public function testOwnerAndTagBumpSubjectOwnerTag(): void // #26 d+f
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $owner, $tag): void {
                $subject->setOwner($owner);
                $subject->addTag($tag);
            },
            ['Subject' => $subject, 'Owner' => $owner, 'Tag' => $tag],
            ['Badge' => $badge],
        );
    }

    public function testBadgeAndTagBumpSubjectBadgeTag(): void // #27 e+f
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject, $badge, $tag): void {
                $subject->setBadge($badge);
                $subject->addTag($tag);
            },
            ['Subject' => $subject, 'Badge' => $badge, 'Tag' => $tag],
            ['Owner' => $owner],
        );
    }

    public function testAllOwnRowFieldsBumpSubjectOnce(): void // #28 a+b+c
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();

        $this->assertFlushBumps(
            function () use ($subject): void {
                $subject->setTitle('renamed');
                $subject->setStatus(ProbeStatus::Published);
                $subject->setGeo(new Geo(9.0, 9.0));
            },
            ['Subject' => $subject],
            ['Owner' => $owner, 'Badge' => $badge, 'Tag' => $tag],
        );
    }

    public function testTwoTagsAddedBumpEachExactlyOnce(): void // #31
    {
        [$subject, , , $tagOne] = $this->persistedHub();
        $tagTwo = new Tag('sports');
        $this->entityManager->persist($tagTwo);
        $this->entityManager->flush();

        $this->assertFlushBumps(
            function () use ($subject, $tagOne, $tagTwo): void {
                $subject->addTag($tagOne);
                $subject->addTag($tagTwo);
            },
            ['Subject' => $subject, 'TagOne' => $tagOne, 'TagTwo' => $tagTwo],
        );
    }

    public function testTagAddedAndRemovedInOneFlushBumpsBothElements(): void // #32
    {
        [$subject, , , $tagOne] = $this->persistedHub();
        $tagTwo = new Tag('sports');
        $this->entityManager->persist($tagTwo);
        $this->entityManager->flush();

        $subject->addTag($tagOne);
        $this->entityManager->flush();
        self::assertSame(1, $subject->getVersion());
        self::assertSame(1, $tagOne->getVersion());

        $this->assertFlushBumps(
            function () use ($subject, $tagOne, $tagTwo): void {
                $subject->removeTag($tagOne);
                $subject->addTag($tagTwo);
            },
            ['Subject' => $subject, 'TagOne' => $tagOne, 'TagTwo' => $tagTwo],
        );
    }

    public function testEnumIsStoredAsBackingValueAndReadsBack(): void // #41
    {
        $subject = $this->persistedSubject();

        $subject->setStatus(ProbeStatus::Published);
        $this->entityManager->flush();

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT status FROM version_probe_subject_version WHERE entity_id = ?',
            [$subject->getId()],
        );

        self::assertIsArray($row);
        self::assertSame('published', $row['status'], 'enum stored as its backing value');
        self::assertSame(ProbeStatus::Published, ProbeStatus::from($row['status']), 'reads back to the enum case');
    }

    public function testInsertWithExistingElementSparesOwnerButBumpsElement(): void // #33 — aggregate insert
    {
        $tag = new Tag('news');
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        self::assertSame(0, $tag->getVersion());

        $subject = new Subject('hello', new Geo(1.0, 2.0));
        $subject->addTag($tag);
        $this->entityManager->persist($subject);
        $this->entityManager->flush();

        self::assertSame(0, $subject->getVersion(), 'a newly inserted owner is not bumped by its initial collection');
        self::assertSame(1, $tag->getVersion(), 'an existing element still bumps when a new owner references it');
    }

    public function testDeletingEntityWritesNoTombstoneAndBumpsAllSurvivors(): void // #42
    {
        [$subject, $owner, $badge, $tag] = $this->persistedHub();
        $subject->setOwner($owner);
        $subject->setBadge($badge);
        $subject->addTag($tag);
        $this->entityManager->flush();
        self::assertSame(1, $owner->getVersion());
        self::assertSame(1, $badge->getVersion());
        self::assertSame(1, $tag->getVersion());

        $subjectId = $subject->getId();
        $connection = $this->entityManager->getConnection();
        $snapshotsBefore = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM version_probe_subject_version WHERE entity_id = ?',
            [$subjectId],
        );

        $this->entityManager->remove($subject);
        $this->entityManager->flush();

        $snapshotsAfter = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM version_probe_subject_version WHERE entity_id = ?',
            [$subjectId],
        );

        self::assertSame($snapshotsBefore, $snapshotsAfter, 'a delete is not a snapshot — no tombstone row for the removed entity');
        self::assertSame(2, $owner->getVersion(), 'owner bumps — it lost the subject (n:1 survivor)');
        self::assertSame(2, $badge->getVersion(), 'badge bumps — it lost the subject (1:1 survivor)');
        self::assertSame(2, $tag->getVersion(), 'tag bumps — it lost the subject (n:m survivor)');
    }

    public function testDeletingInverseSideOfManyToManyBumpsOwningSurvivors(): void // #42b
    {
        $subject = $this->persistedSubject();
        $tag = new Tag('news');
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $subject->addTag($tag);
        $this->entityManager->flush();
        self::assertSame(1, $subject->getVersion());
        self::assertSame(1, $tag->getVersion());

        $this->entityManager->remove($tag);
        $this->entityManager->flush();

        self::assertSame(2, $subject->getVersion(), 'deleting the inverse-side element bumps the owning-side survivor');
    }

    public function testDeletingBothEndsOfManyToManyDoesNotBumpEither(): void // #42c
    {
        $subject = $this->persistedSubject();
        $tag = new Tag('news');
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $subject->addTag($tag);
        $this->entityManager->flush();

        $subjectId = $subject->getId();
        $tagId = $tag->getId();
        $connection = $this->entityManager->getConnection();
        $subjectSnapsBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM version_probe_subject_version WHERE entity_id = ?', [$subjectId]);
        $tagSnapsBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM version_probe_tag_version WHERE entity_id = ?', [$tagId]);

        $this->entityManager->remove($subject);
        $this->entityManager->remove($tag);
        $this->entityManager->flush();

        $subjectSnapsAfter = (int) $connection->fetchOne('SELECT COUNT(*) FROM version_probe_subject_version WHERE entity_id = ?', [$subjectId]);
        $tagSnapsAfter = (int) $connection->fetchOne('SELECT COUNT(*) FROM version_probe_tag_version WHERE entity_id = ?', [$tagId]);

        self::assertSame($subjectSnapsBefore, $subjectSnapsAfter, 'both ends deleted — no tombstone for subject');
        self::assertSame($tagSnapsBefore, $tagSnapsAfter, 'both ends deleted — no tombstone for tag');
    }

    /**
     * @return array{0: Subject, 1: Owner, 2: Badge, 3: Tag}
     */
    private function persistedHub(): array
    {
        $subject = new Subject('hello', new Geo(1.0, 2.0));
        $owner = new Owner('acme');
        $badge = new Badge('A1');
        $tag = new Tag('news');
        $this->entityManager->persist($subject);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($badge);
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return [$subject, $owner, $badge, $tag];
    }

    /**
     * Make a change, flush, and assert the full per-case tail: every entity in
     * $bumped went +1, every entity in $isolated stayed put, and a second
     * no-op flush leaves all of them stable.
     *
     * @param array<string, object> $bumped
     * @param array<string, object> $isolated
     */
    private function assertFlushBumps(callable $mutate, array $bumped, array $isolated = []): void
    {
        $expected = [];

        foreach ($bumped as $label => $entity) {
            $expected[$label] = $entity->getVersion() + 1;
        }

        foreach ($isolated as $label => $entity) {
            $expected[$label] = $entity->getVersion();
        }

        $all = $bumped + $isolated;

        $mutate();
        $this->entityManager->flush();

        foreach ($all as $label => $entity) {
            self::assertSame($expected[$label], $entity->getVersion(), $label . ' (bump / isolation)');
        }

        $this->entityManager->flush();

        foreach ($all as $label => $entity) {
            self::assertSame($expected[$label], $entity->getVersion(), $label . ' (stability after no-op flush)');
        }
    }
}
