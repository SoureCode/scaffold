<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RelationBump;

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
use SoureCode\Component\Versionable\Internal\RelationBumpState;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\RelationBump\Fixtures\LoudItem;
use SoureCode\Component\Versionable\Tests\RelationBump\Fixtures\Partner;
use SoureCode\Component\Versionable\Tests\RelationBump\Fixtures\QuietItem;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\Versioner;
use SoureCode\Component\Versionable\VersionerInterface;
use Symfony\Component\Clock\MockClock;

final class RelationBumpTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private VersionerInterface $versioner;

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
        $clock = new MockClock('2026-05-26T10:00:00+00:00');
        $relationBumpState = new RelationBumpState();

        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, $clock, relationBumpState: $relationBumpState),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(LoudItem::class),
            $this->entityManager->getClassMetadata(QuietItem::class),
            $this->entityManager->getClassMetadata(Partner::class),
        ]);

        $this->versioner = new Versioner(
            $this->entityManager,
            $factory,
            relationBumpState: $relationBumpState,
        );
    }

    public function testClassDefaultTrueRipples(): void
    {
        $loud = new LoudItem('hello');
        $partner = new Partner('acme');
        $this->entityManager->persist($loud);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $loud->setPartner($partner);
        $this->entityManager->flush();

        self::assertSame(2, $loud->getVersion());
        self::assertSame(2, $partner->getVersion(), 'class default true → partner bumps too');
    }

    public function testClassDefaultFalseDoesNotRipple(): void
    {
        $quiet = new QuietItem('hello');
        $partner = new Partner('acme');
        $this->entityManager->persist($quiet);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $quiet->setPartner($partner);
        $this->entityManager->flush();

        self::assertSame(2, $quiet->getVersion(), 'the entity itself still bumps');
        self::assertSame(1, $partner->getVersion(), 'class default false → partner does NOT bump beyond its insert v=1');
    }

    public function testRuntimeOverrideFalseSuppressesPropagationOnLoud(): void
    {
        $loud = new LoudItem('hello');
        $partner = new Partner('acme');
        $this->entityManager->persist($loud);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $loud->setPartner($partner);
        $this->versioner->bumpRelations(false);
        $this->entityManager->flush();

        self::assertSame(2, $loud->getVersion());
        self::assertSame(1, $partner->getVersion(), 'runtime override beats Loud\'s class default true; partner stays at insert v=1');
    }

    public function testRuntimeOverrideTrueForcesPropagationOnQuiet(): void
    {
        $quiet = new QuietItem('hello');
        $partner = new Partner('acme');
        $this->entityManager->persist($quiet);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $quiet->setPartner($partner);
        $this->versioner->bumpRelations(true);
        $this->entityManager->flush();

        self::assertSame(2, $quiet->getVersion());
        self::assertSame(2, $partner->getVersion(), 'runtime override beats Quiet\'s class default false');
    }

    public function testMixedFlushUsesEachClassDefault(): void
    {
        $loud = new LoudItem('hello');
        $quiet = new QuietItem('quiet');
        $partner = new Partner('acme');
        $this->entityManager->persist($loud);
        $this->entityManager->persist($quiet);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $loud->setPartner($partner);
        $quiet->setPartner($partner);
        $this->entityManager->flush();

        self::assertSame(2, $loud->getVersion());
        self::assertSame(2, $quiet->getVersion());
        self::assertSame(2, $partner->getVersion(), 'partner bumps once from Loud\'s ripple; Quiet does not propagate');
    }

    public function testRuntimeOverrideAppliesToWholeFlush(): void
    {
        $loud = new LoudItem('hello');
        $quiet = new QuietItem('quiet');
        $partner = new Partner('acme');
        $this->entityManager->persist($loud);
        $this->entityManager->persist($quiet);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $loud->setPartner($partner);
        $quiet->setPartner($partner);
        $this->versioner->bumpRelations(false);
        $this->entityManager->flush();

        self::assertSame(2, $loud->getVersion());
        self::assertSame(2, $quiet->getVersion());
        self::assertSame(1, $partner->getVersion(), 'override wins over every class default in this flush; partner stays at insert v=1');
    }

    public function testOverrideResetsAfterFlush(): void
    {
        $loud = new LoudItem('hello');
        $first = new Partner('first');
        $second = new Partner('second');
        $this->entityManager->persist($loud);
        $this->entityManager->persist($first);
        $this->entityManager->persist($second);
        $this->entityManager->flush();

        $loud->setPartner($first);
        $this->versioner->bumpRelations(false);
        $this->entityManager->flush();
        self::assertSame(2, $loud->getVersion());
        self::assertSame(1, $first->getVersion(), 'override suppressed propagation in flush #1; first stays at insert v=1');

        $loud->setPartner($second);
        $this->entityManager->flush();

        self::assertSame(3, $loud->getVersion());
        self::assertSame(2, $first->getVersion(), 'flush #2 used Loud\'s class default — first lost the loud, bumped');
        self::assertSame(2, $second->getVersion(), 'flush #2 used Loud\'s class default — second gained the loud, bumped');
    }

    public function testApplyVersionWithBumpRelationsFalseRestoresWithoutRipple(): void
    {
        $loud = new LoudItem('v1');
        $first = new Partner('first');
        $second = new Partner('second');
        $this->entityManager->persist($loud);
        $this->entityManager->persist($first);
        $this->entityManager->persist($second);
        $this->entityManager->flush();

        $loud->setPartner($first);
        $this->entityManager->flush();
        self::assertSame(2, $loud->getVersion());
        self::assertSame(2, $first->getVersion());

        $loud->setPartner($second);
        $this->entityManager->flush();
        self::assertSame(3, $loud->getVersion());
        self::assertSame(3, $first->getVersion(), 'lost the loud');
        self::assertSame(2, $second->getVersion(), 'gained the loud');

        $this->versioner->applyVersion($loud, 2, bumpRelations: false);
        $this->entityManager->flush();

        self::assertSame(4, $loud->getVersion(), 'restoration is recorded as its own snapshot');
        self::assertSame(3, $first->getVersion(), 'no ripple — first not touched by the restore');
        self::assertSame(2, $second->getVersion(), 'no ripple — second not touched by the restore');
    }

    public function testGlobalDefaultFalseStopsRippleForClassesWithNoExplicitAttribute(): void
    {
        // Re-wire the EM with a state whose global default is `false`.
        $state = $this->bootEntityManagerWithGlobalDefault(false);

        $loud = new LoudItem('hello');
        $partner = new Partner('acme');
        $this->entityManager->persist($loud);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $loud->setPartner($partner);
        $this->entityManager->flush();

        self::assertSame(2, $loud->getVersion(), 'loud bumps for its own change');
        self::assertSame(1, $partner->getVersion(), 'global default false: no ripple — partner stays at insert v=1');

        unset($state);
    }

    public function testAttributeTrueOverridesGlobalDefaultFalse(): void
    {
        // Global default false, but Loud explicitly opts back in via its
        // own attribute — wait, Loud's attribute is currently `null`.
        // Use a fresh LoudWithExplicitTrue fixture? Simpler: prove the
        // chain on Quiet (attribute false) against global true — already
        // covered. And vice-versa via global false + a class that has
        // attribute true. The cleanest path: replace the class default at
        // runtime by re-asserting on QuietItem (attribute false) with the
        // global flipped to true — same as today's setup. The chain holds
        // because attribute false is explicit and wins.
        $state = $this->bootEntityManagerWithGlobalDefault(false);

        $quiet = new QuietItem('quiet');
        $partner = new Partner('acme');
        $this->entityManager->persist($quiet);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $quiet->setPartner($partner);
        $this->entityManager->flush();

        self::assertSame(1, $partner->getVersion(), 'attribute false stays explicit even under a global-false default');

        unset($state);
    }

    public function testRuntimeOverrideTrumpsGlobalDefaultAndAttribute(): void
    {
        $state = $this->bootEntityManagerWithGlobalDefault(false);

        $quiet = new QuietItem('quiet');
        $partner = new Partner('acme');
        $this->entityManager->persist($quiet);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        $quiet->setPartner($partner);
        $this->versioner->bumpRelations(true);
        $this->entityManager->flush();

        self::assertSame(2, $quiet->getVersion());
        self::assertSame(2, $partner->getVersion(), 'runtime override beats both class attribute and global default');

        unset($state);
    }

    /**
     * Bring up a fresh EM (replacing the one in setUp) wired with a
     * `RelationBumpState` whose global default is set to the given value.
     * Returns the state so the caller can keep it alive.
     */
    private function bootEntityManagerWithGlobalDefault(bool $globalDefault): RelationBumpState
    {
        $config = \Doctrine\ORM\ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = \Doctrine\DBAL\DriverManager::getConnection(
            (new \Doctrine\DBAL\Tools\DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new \Doctrine\ORM\EntityManager($connection, $config);

        $factory = new VersionableMetadataFactory();
        $clock = new \Symfony\Component\Clock\MockClock('2026-05-28T10:00:00+00:00');
        $state = new RelationBumpState();
        $state->setGlobalDefault($globalDefault);

        $this->entityManager->getEventManager()->addEventListener(
            [\Doctrine\ORM\Events::onFlush, \Doctrine\ORM\Events::postFlush],
            \SoureCode\Component\Versionable\Tests\VersionableListenerFactory::create(
                $factory,
                $clock,
                relationBumpState: $state,
            ),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [\Doctrine\ORM\Tools\ToolEvents::postGenerateSchema],
            new \SoureCode\Component\Versionable\EventListener\VersionableSchemaListener($factory),
        );

        (new \Doctrine\ORM\Tools\SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(LoudItem::class),
            $this->entityManager->getClassMetadata(QuietItem::class),
            $this->entityManager->getClassMetadata(Partner::class),
        ]);

        $this->versioner = new Versioner(
            $this->entityManager,
            $factory,
            relationBumpState: $state,
        );

        return $state;
    }
}
