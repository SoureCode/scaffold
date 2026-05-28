<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RuntimeMapping;

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
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures\PlainUser;
use SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures\WorkItem;
use SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures\WorkItemRuntimeMappingListener;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

/**
 * Regression: a versioned entity with a single-valued association to a
 * NON-versioned target (the Authorable `#[CreatedBy] User` case). The
 * generated `*History` DTO must expose `getCreatedBy()` returning the live
 * target entity loaded via the EM at hydration time — previously the
 * generator skipped any association whose target wasn't versioned, and
 * the History class shipped no accessor at all.
 */
final class NonVersionedTargetTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Versioner $versioner;

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

        $this->entityManager->getEventManager()->addEventListener(
            [Events::loadClassMetadata],
            new WorkItemRuntimeMappingListener(),
        );

        $factory = new VersionableMetadataFactory($this->entityManager);

        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, new MockClock('2026-05-28T10:00:00+00:00')),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(PlainUser::class),
            $this->entityManager->getClassMetadata(WorkItem::class),
        ]);

        $this->versioner = new Versioner($this->entityManager, $factory);
    }

    public function testHistoryDtoExposesAccessorForNonVersionedTarget(): void
    {
        $alice = new PlainUser('alice');
        $work = new WorkItem('plan');
        $work->setCreatedBy($alice);

        $this->entityManager->persist($alice);
        $this->entityManager->persist($work);
        $this->entityManager->flush();

        $historyClass = Versioner::historyClassFor(WorkItem::class);
        $history = $this->versioner->findByVersion(WorkItem::class, $work->getId(), 1);

        self::assertNotNull($history);
        self::assertInstanceOf($historyClass, $history);
        self::assertTrue(method_exists($history, 'getCreatedBy'), 'association to non-versioned target gains a getter on the *History DTO');

        $hydrated = $history->getCreatedBy();
        self::assertInstanceOf(PlainUser::class, $hydrated, 'returns the live target entity loaded via the EM');
        self::assertSame($alice->getId(), $hydrated->getId());
        self::assertSame('alice', $hydrated->getName());
    }

    public function testHistoryGetterReturnsNullWhenSnapshotHasNoTargetId(): void
    {
        $work = new WorkItem('untouched');
        $this->entityManager->persist($work);
        $this->entityManager->flush();

        $history = $this->versioner->findByVersion(WorkItem::class, $work->getId(), 1);

        self::assertNotNull($history);
        self::assertNull($history->getCreatedBy());
    }
}
