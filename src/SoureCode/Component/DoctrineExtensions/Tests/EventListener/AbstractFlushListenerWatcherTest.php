<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\EventListener;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeBehaviorMetadata;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeBehaviorMetadataFactory;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeChangeBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeFlushListener;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeUpdateBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\InterfaceStampable;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\RelatedStampable;

final class AbstractFlushListenerWatcherTest extends TestCase
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

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(RelatedStampable::class),
            $this->entityManager->getClassMetadata(InterfaceStampable::class),
        ]);
    }

    public function testRelatedWatcherFiresWhenWatchedPathTargetIsUpdated(): void
    {
        $related = new RelatedStampable('original');
        $watcher = new RelatedStampable('watcher');
        $watcher->related = $related;

        $this->entityManager->persist($related);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        $listener = $this->attachListener(['related.label']);

        $related->label = 'updated';
        $this->entityManager->flush();

        self::assertSame('watcher-fired', $watcher->watcherStamp, 'updating related.label must stamp the watcher entity');
        self::assertSame(1, $listener->resolveValueCalls);
    }

    public function testRelatedWatcherIgnoresOtherFieldsOnTheRelated(): void
    {
        $related = new RelatedStampable('original');
        $watcher = new RelatedStampable('watcher');
        $watcher->related = $related;

        $this->entityManager->persist($related);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        $listener = $this->attachListener(['related.label']);

        // watcherStamp on $related is in the change set, but the watcher
        // binding only fires on related.label — so this flush must be a no-op.
        $related->watcherStamp = 'unrelated-write';
        $this->entityManager->flush();

        self::assertNull($watcher->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testNonDottedBindingOnlyFiresThroughTouchScheduled(): void
    {
        $related = new RelatedStampable('original');
        $watcher = new RelatedStampable('watcher');
        $watcher->related = $related;

        $this->entityManager->persist($related);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        // A binding without a dotted path only matches the entity's own
        // change set — touchRelatedWatchers must skip it because that
        // walker is specifically about "other" entities reaching the
        // changed one. Updating $related's label fires the binding ONLY on
        // $related itself, never on the watcher.
        $listener = $this->attachListener(['label']);

        $related->label = 'updated';
        $this->entityManager->flush();

        self::assertSame('watcher-fired', $related->watcherStamp);
        self::assertNull($watcher->watcherStamp);
        self::assertSame(1, $listener->resolveValueCalls);
    }

    public function testCollectionWatcherFiresOnElementAddition(): void
    {
        $parent = new RelatedStampable('parent');
        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        $listener = $this->attachListener(['children']);

        $child = new RelatedStampable('child');
        $child->related = $parent;
        $parent->children->add($child);
        $this->entityManager->persist($child);

        $this->entityManager->flush();

        self::assertSame('watcher-fired', $parent->watcherStamp);
        self::assertSame(1, $listener->resolveValueCalls);
    }

    public function testCollectionWatcherStaysSilentWhenOwnerHasNoMatchingField(): void
    {
        $parent = new RelatedStampable('parent');
        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        // Bind a different field name so touchCollectionWatchers has bindings
        // but none of them match the changed collection.
        $listener = $this->attachListener(['unrelated_field']);

        $child = new RelatedStampable('child');
        $child->related = $parent;
        $parent->children->add($child);
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        self::assertNull($parent->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testDeletedRelatedTriggersWatcherIgnoringValueMatcher(): void
    {
        $related = new RelatedStampable('original');
        $watcher = new RelatedStampable('watcher');
        $watcher->related = $related;

        $this->entityManager->persist($related);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        // Break the FK so the row can be deleted without cascading the
        // watcher; the JoinColumn uses SET NULL onDelete.
        $watcher->related = null;
        $this->entityManager->flush();
        // Re-attach so the watcher still has a reference at delete time.
        $watcher->related = $related;
        $this->entityManager->flush();

        $listener = $this->attachListener(['related.label'], stamp: 'deleted-fired');

        $this->entityManager->remove($related);
        $this->entityManager->flush();

        self::assertSame('deleted-fired', $watcher->watcherStamp);
        self::assertGreaterThanOrEqual(1, $listener->resolveValueCalls);
    }

    public function testWalkPathCycleDoesNotInfiniteLoop(): void
    {
        $a = new RelatedStampable('a');
        $b = new RelatedStampable('b');
        $a->related = $b;

        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        // Close the cycle: b -> a, a -> b.
        $b->related = $a;
        $this->entityManager->flush();

        // A binding with a longer dotted path forces the walker to recurse
        // through the cycle; the visited guard must short-circuit before
        // we blow the stack.
        $listener = $this->attachListener(['related.related.related.label']);

        $a->label = 'updated';
        $this->entityManager->flush();

        // The cycle prevents the binding from ever reaching a leaf, so
        // resolveValue must never fire — but the listener must also not
        // crash.
        self::assertNull($b->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testWalkPathReturnsFalseWhenIntermediateNodeIsNull(): void
    {
        $related = new RelatedStampable('original');
        $watcher = new RelatedStampable('watcher');
        // Intentionally null — the walker must hit the "$related is not a
        // collection and not an object" branch and return false instead
        // of NPE'ing.
        $watcher->related = null;

        $this->entityManager->persist($related);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        $listener = $this->attachListener(['related.label']);

        $related->label = 'updated';
        $this->entityManager->flush();

        self::assertNull($watcher->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testWalkPathReturnsFalseWhenHeadPropertyDoesNotExist(): void
    {
        $related = new RelatedStampable('original');
        $watcher = new RelatedStampable('watcher');
        $watcher->related = $related;

        $this->entityManager->persist($related);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        // findProperty returns null for unknown segments — walker must
        // bail out instead of throwing.
        $listener = $this->attachListener(['nonexistent.label']);

        $related->label = 'updated';
        $this->entityManager->flush();

        self::assertNull($watcher->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testWalkPathDeepCycleGuardReturnsFalse(): void
    {
        $self = new RelatedStampable('self-loop');
        $other = new RelatedStampable('other');

        $this->entityManager->persist($self);
        $this->entityManager->persist($other);
        $this->entityManager->flush();

        // Close a cycle that the walker MUST stop walking through after
        // re-encountering $self. Without the visited guard the recursion
        // would never terminate.
        $self->related = $self;
        $this->entityManager->flush();

        // Path is deeper than the cycle so the walker has to visit $self
        // twice — the second visit triggers the cycle guard at the top
        // of walkPath.
        $listener = $this->attachListener(['related.related.label']);

        $other->label = 'updated';
        $this->entityManager->flush();

        self::assertNull($self->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testWalkPathRecursesThroughCollectionElementsAndFindsMatch(): void
    {
        $parent = new RelatedStampable('parent');
        $left = new RelatedStampable('left');
        $right = new RelatedStampable('right');
        $left->related = $parent;
        $right->related = $parent;
        $parent->children->add($left);
        $parent->children->add($right);

        $this->entityManager->persist($parent);
        $this->entityManager->persist($left);
        $this->entityManager->persist($right);
        $this->entityManager->flush();

        // Watcher path traverses the collection and through each element
        // looks at its own ".label". Updating one element drives the
        // walker into the collection branch (instanceof Collection),
        // iterating until it finds the changedRelated element.
        $listener = $this->attachListener(['children.label']);

        $right->label = 'updated';
        $this->entityManager->flush();

        // $parent watches "children.label" and the right child's label
        // changed — the walker reaches the matching element via the
        // collection iteration branch.
        self::assertSame('watcher-fired', $parent->watcherStamp);
        self::assertGreaterThanOrEqual(1, $listener->resolveValueCalls);
    }

    public function testWalkPathCollectionExhaustionReturnsFalse(): void
    {
        $parent = new RelatedStampable('parent');
        $unrelated = new RelatedStampable('unrelated');
        $left = new RelatedStampable('left');
        $left->related = $parent;
        $parent->children->add($left);

        $this->entityManager->persist($parent);
        $this->entityManager->persist($left);
        $this->entityManager->persist($unrelated);
        $this->entityManager->flush();

        $listener = $this->attachListener(['children.label']);

        // Updating $unrelated drives touchRelatedWatchers, which walks
        // $parent's children collection looking for $unrelated. The
        // single element ($left) is not $unrelated and its recursion
        // into "label" hits the path-without-dot bail, so the collection
        // iteration exhausts and returns false.
        $unrelated->label = 'updated';
        $this->entityManager->flush();

        self::assertNull($parent->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testWalkPathRecursesDeeperThroughSingleRelation(): void
    {
        $deep = new RelatedStampable('deep');
        $middle = new RelatedStampable('middle');
        $watcher = new RelatedStampable('watcher');
        $middle->related = $deep;
        $watcher->related = $middle;

        $this->entityManager->persist($deep);
        $this->entityManager->persist($middle);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        // Three-segment path forces the walker through the single-object
        // recursion branch (related.related.label): the first hop lands
        // on $middle (not $changedRelated), so the walker recurses into
        // $middle.related = $deep.
        $listener = $this->attachListener(['related.related.label']);

        $deep->label = 'updated';
        $this->entityManager->flush();

        self::assertSame('watcher-fired', $watcher->watcherStamp);
        self::assertGreaterThanOrEqual(1, $listener->resolveValueCalls);
    }

    public function testValueMatcherRejectionPreventsStampingOnWatcher(): void
    {
        $related = new RelatedStampable('original');
        $watcher = new RelatedStampable('watcher');
        $watcher->related = $related;

        $this->entityManager->persist($related);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        // ChangeBinding with a value matcher that only accepts a specific
        // string. The walker finds the path but the value matcher rejects
        // the new value and the watcher stays untouched.
        $binding = new FakeChangeBinding(
            new \ReflectionProperty(RelatedStampable::class, 'watcherStamp'),
            ['related.label'],
            matchValue: true,
            value: 'specific-target',
        );

        $factory = new FakeBehaviorMetadataFactory([
            RelatedStampable::class => new FakeBehaviorMetadata(changeBindings: [$binding]),
        ]);

        $listener = new FakeFlushListener(
            $factory,
            new ChangeSetMatcher(),
            static fn(): string => 'watcher-fired',
        );

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        // The new value 'updated' does not equal 'specific-target', so
        // the value matcher rejects and the stamp stays null.
        $related->label = 'updated';
        $this->entityManager->flush();

        self::assertNull($watcher->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testTouchScheduledSkipsUpdateBindingAlreadyInChangeSet(): void
    {
        $entity = new RelatedStampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $binding = new FakeUpdateBinding(
            new \ReflectionProperty(RelatedStampable::class, 'watcherStamp'),
        );
        $factory = new FakeBehaviorMetadataFactory([
            RelatedStampable::class => new FakeBehaviorMetadata(updateBindings: [$binding]),
        ]);

        $listener = new FakeFlushListener(
            $factory,
            new ChangeSetMatcher(),
            static fn(): string => 'listener-overwrite',
        );

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        // User-set the property to a value before the listener runs. The
        // touchScheduled "already in change set" guard must NOT overwrite
        // the user value.
        $entity->watcherStamp = 'user-supplied';
        $entity->label = 'changed';
        $this->entityManager->flush();

        self::assertSame('user-supplied', $entity->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testInterfaceFallbackSkipsWhenInterfacePropertyAlreadyInChangeSet(): void
    {
        $entity = new InterfaceStampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $listener = new FakeFlushListener(new FakeBehaviorMetadataFactory(), new ChangeSetMatcher());

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        // Pre-set the interface property so applyInterfaceUpdate's
        // "already in change set" guard fires and the stamper is NOT
        // invoked.
        $entity->setInterfaceStamp('user-set');
        $entity->label = 'changed';
        $this->entityManager->flush();

        self::assertSame('user-set', $entity->getInterfaceStamp());
        self::assertSame(1, $listener->updateFallbackCalls);
    }

    public function testTouchScheduledSkipsChangeBindingAlreadyInChangeSet(): void
    {
        $entity = new RelatedStampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $binding = new FakeChangeBinding(
            new \ReflectionProperty(RelatedStampable::class, 'watcherStamp'),
            ['label'],
        );
        $factory = new FakeBehaviorMetadataFactory([
            RelatedStampable::class => new FakeBehaviorMetadata(changeBindings: [$binding]),
        ]);

        $listener = new FakeFlushListener(
            $factory,
            new ChangeSetMatcher(),
            static fn(): string => 'listener-overwrite',
        );

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        // The user pre-sets the watched-by binding property AND triggers
        // a change to `label` that would otherwise stamp it. The
        // touchScheduled change-binding "already in change set" guard
        // must NOT overwrite the user value.
        $entity->watcherStamp = 'user-supplied';
        $entity->label = 'changed';
        $this->entityManager->flush();

        self::assertSame('user-supplied', $entity->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testTagCollectionClearTriggersCollectionDeletionWatcher(): void
    {
        $parent = new RelatedStampable('parent');
        $tag = new RelatedStampable('tag');
        $parent->tags->add($tag);

        $this->entityManager->persist($parent);
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        $listener = $this->attachListener(['tags']);

        // Clearing an owning-side many-to-many collection registers it
        // in `scheduledCollectionDeletions` — that is the dedicated path
        // touchCollectionWatchers walks alongside `scheduledCollectionUpdates`.
        $parent->tags->clear();
        $this->entityManager->flush();

        self::assertSame('watcher-fired', $parent->watcherStamp);
        self::assertGreaterThanOrEqual(1, $listener->resolveValueCalls);
    }

    public function testCollectionWatcherWithoutAnyChangeBindingsBailsEarly(): void
    {
        $parent = new RelatedStampable('parent');
        $this->entityManager->persist($parent);
        $this->entityManager->flush();

        // Binding the metadata to the entity class but with only persist
        // bindings (no change bindings) exercises the touchCollectionWatchers
        // early-return when getChangeBindings() === [].
        $persistBinding = new \SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakePersistBinding(
            new \ReflectionProperty(RelatedStampable::class, 'watcherStamp'),
        );
        $factory = new FakeBehaviorMetadataFactory([
            RelatedStampable::class => new FakeBehaviorMetadata(persistBindings: [$persistBinding]),
        ]);

        $listener = new FakeFlushListener(
            $factory,
            new ChangeSetMatcher(),
            static fn(): string => 'stamp',
        );

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        $child = new RelatedStampable('child');
        $child->related = $parent;
        $parent->children->add($child);
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        // The persist binding fired once on $child (initial persist) but
        // the collection change on $parent must NOT trigger a stamp
        // because the metadata has no change bindings.
        self::assertNull($parent->watcherStamp);
        self::assertSame('stamp', $child->watcherStamp);
        self::assertSame(1, $listener->resolveValueCalls);
    }

    public function testWalkPathCollectionRecursionFindsMatchDeeper(): void
    {
        $owner = new RelatedStampable('owner');
        $childA = new RelatedStampable('a');
        $childB = new RelatedStampable('b');
        // Wire children's `related` to a different entity that is itself
        // the changedRelated; the walker must descend through the
        // collection element (line 383 recursion) AND then identify the
        // grand-child as $changedRelated.
        $deep = new RelatedStampable('deep');
        $childA->related = $deep;
        $childB->related = $deep;
        $owner->children->add($childA);
        $owner->children->add($childB);

        $this->entityManager->persist($deep);
        $this->entityManager->persist($childA);
        $this->entityManager->persist($childB);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        // Path with two dotted segments forces collection iteration AND
        // a recursive call from inside the collection branch.
        $listener = $this->attachListener(['children.related.label']);

        $deep->label = 'updated';
        $this->entityManager->flush();

        self::assertSame('watcher-fired', $owner->watcherStamp);
    }

    public function testIdentityMapClassesWithoutChangeBindingsAreSkipped(): void
    {
        $related = new RelatedStampable('original');
        $watcher = new RelatedStampable('watcher');
        $watcher->related = $related;

        $this->entityManager->persist($related);
        $this->entityManager->persist($watcher);
        $this->entityManager->flush();

        // Metadata factory returns empty metadata for RelatedStampable so
        // touchRelatedWatchers must skip the class entirely (early continue
        // on the `getChangeBindings() === []` guard).
        $listener = new FakeFlushListener(new FakeBehaviorMetadataFactory(), new ChangeSetMatcher());

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        $related->label = 'updated';
        $this->entityManager->flush();

        self::assertNull($watcher->watcherStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    /**
     * @param list<string> $fields
     */
    private function attachListener(array $fields, string $stamp = 'watcher-fired'): FakeFlushListener
    {
        $binding = new FakeChangeBinding(
            new \ReflectionProperty(RelatedStampable::class, 'watcherStamp'),
            $fields,
        );

        $factory = new FakeBehaviorMetadataFactory([
            RelatedStampable::class => new FakeBehaviorMetadata(changeBindings: [$binding]),
        ]);

        $listener = new FakeFlushListener(
            $factory,
            new ChangeSetMatcher(),
            static fn(): string => $stamp,
        );

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        return $listener;
    }
}
