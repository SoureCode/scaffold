<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataFactoryInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\ChangeBindingInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;

abstract class AbstractFlushListener
{
    public function __construct(
        protected readonly BehaviorMetadataFactoryInterface $metadataFactory,
        protected readonly ChangeSetMatcher $changeSetMatcher,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->shouldRun()) {
            return;
        }

        $metadata = $this->metadataFactory->getMetadataFor($entity::class);

        if (!$metadata->isEmpty()) {
            foreach ($metadata->getPersistBindings() as $binding) {
                $property = $binding->getProperty();

                if ($property->getValue($entity) === null) {
                    $property->setValue($entity, $this->resolveValue($property));
                }
            }

            foreach ($metadata->getUpdateBindings() as $binding) {
                if ($binding->isNullable()) {
                    continue;
                }

                $property = $binding->getProperty();

                if ($property->getValue($entity) === null) {
                    $property->setValue($entity, $this->resolveValue($property));
                }
            }

            return;
        }

        $this->handlePersistInterfaceFallback($entity);
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        // Both isScheduledForUpdate and isScheduledForDeletion used to scan
        // these arrays linearly per identity-map entry, turning the flush
        // into O(n²). Materialize them once per flush so every check is
        // O(1).
        /** @var \SplObjectStorage<object, true> $scheduledUpdates */
        $scheduledUpdates = new \SplObjectStorage();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $scheduled) {
            $scheduledUpdates[$scheduled] = true;
        }

        /** @var \SplObjectStorage<object, true> $scheduledDeletions */
        $scheduledDeletions = new \SplObjectStorage();

        foreach ($unitOfWork->getScheduledEntityDeletions() as $scheduled) {
            $scheduledDeletions[$scheduled] = true;
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->touchScheduled($entity, $entityManager, $unitOfWork);
        }

        // Same list iterated again on purpose: pass 1 stamps the entity itself; pass 2 walks the
        // identity map to stamp any *other* entity that watches one of these via a dotted path.
        foreach ($unitOfWork->getScheduledEntityUpdates() as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, $scheduledUpdates, $scheduledDeletions, useChangeSet: true, ignoreValueMatcher: false);
        }

        foreach ($unitOfWork->getScheduledEntityInsertions() as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, $scheduledUpdates, $scheduledDeletions, useChangeSet: true, ignoreValueMatcher: false);
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, $scheduledUpdates, $scheduledDeletions, useChangeSet: false, ignoreValueMatcher: true);
        }

        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $this->touchCollectionWatchers($collection, $entityManager, $unitOfWork, $scheduledUpdates);
        }

        foreach ($unitOfWork->getScheduledCollectionDeletions() as $collection) {
            $this->touchCollectionWatchers($collection, $entityManager, $unitOfWork, $scheduledUpdates);
        }
    }

    abstract protected function shouldRun(): bool;

    abstract protected function resolveValue(\ReflectionProperty $property): mixed;

    /**
     * @template T of object
     *
     * @param T $entity
     */
    abstract protected function handlePersistInterfaceFallback(object $entity): void;

    /**
     * @template T of object
     *
     * @param T $entity
     */
    abstract protected function handleUpdateInterfaceFallback(object $entity, EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): void;

    /**
     * Shared shape for interface-fallback update stamping: skip if the
     * property already shows up in the entity's change set, otherwise run
     * the supplied stamper and recompute the change set so the new value
     * actually ships with the flush.
     *
     * The stamper's return value is intentionally untyped: most concrete
     * implementations call a void setter from a `fn` arrow function,
     * which PHP treats as a `mixed`-returning expression.
     *
     * @template T of object
     *
     * @param T $entity
     * @param callable(T): mixed $stamp
     */
    protected function applyInterfaceUpdate(
        object $entity,
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
        string $propertyName,
        callable $stamp,
    ): void {
        if (array_key_exists($propertyName, $unitOfWork->getEntityChangeSet($entity))) {
            return;
        }

        $stamp($entity);

        $unitOfWork->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata($entity::class),
            $entity,
        );
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    private function touchScheduled(object $entity, EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): void
    {
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);

        if ($metadata->isEmpty()) {
            $this->handleUpdateInterfaceFallback($entity, $entityManager, $unitOfWork);

            return;
        }

        $touched = false;
        $changeSet = $unitOfWork->getEntityChangeSet($entity);

        foreach ($metadata->getUpdateBindings() as $binding) {
            $property = $binding->getProperty();

            if (array_key_exists($property->getName(), $changeSet)) {
                continue;
            }

            $property->setValue($entity, $this->resolveValue($property));
            $touched = true;
        }

        foreach ($metadata->getChangeBindings() as $binding) {
            if (!$this->changeSetMatcher->matches($binding, $entity, $unitOfWork)) {
                continue;
            }

            $property = $binding->getProperty();

            if (array_key_exists($property->getName(), $changeSet)) {
                continue;
            }

            $property->setValue($entity, $this->resolveValue($property));
            $touched = true;
        }

        if ($touched) {
            $unitOfWork->recomputeSingleEntityChangeSet(
                $entityManager->getClassMetadata($entity::class),
                $entity,
            );
        }
    }

    /**
     * @template T of object
     *
     * @param T $changedRelated
     * @param \SplObjectStorage<object, true> $scheduledUpdates
     * @param \SplObjectStorage<object, true> $scheduledDeletions
     */
    private function touchRelatedWatchers(
        object $changedRelated,
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
        \SplObjectStorage $scheduledUpdates,
        \SplObjectStorage $scheduledDeletions,
        bool $useChangeSet,
        bool $ignoreValueMatcher,
    ): void {
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            $metadata = $this->metadataFactory->getMetadataFor($class);

            if ($metadata->getChangeBindings() === []) {
                continue;
            }

            foreach ($entities as $entity) {
                if (isset($scheduledUpdates[$entity])) {
                    continue;
                }

                if (isset($scheduledDeletions[$entity])) {
                    continue;
                }

                if ($this->touchWatcher($entity, $changedRelated, $unitOfWork, $metadata->getChangeBindings(), $useChangeSet, $ignoreValueMatcher)) {
                    $unitOfWork->recomputeSingleEntityChangeSet(
                        $entityManager->getClassMetadata($entity::class),
                        $entity,
                    );
                }
            }
        }
    }

    /**
     * @template TEntity of object
     * @template TRelated of object
     *
     * @param TEntity $entity
     * @param TRelated $changedRelated
     * @param list<ChangeBindingInterface> $bindings
     */
    private function touchWatcher(
        object $entity,
        object $changedRelated,
        UnitOfWork $unitOfWork,
        array $bindings,
        bool $useChangeSet,
        bool $ignoreValueMatcher,
    ): bool {
        $touched = false;

        foreach ($bindings as $binding) {
            foreach ($binding->getFields() as $field) {
                // Non-dotted fields target the entity itself; those are handled by touchScheduled.
                // This pass only walks relational paths to find *other* entities that watch the changed one.
                if (!str_contains($field, '.')) {
                    continue;
                }

                $nested = null;

                if (!$this->pathPointsTo($entity, $field, $changedRelated, $nested)) {
                    continue;
                }

                $property = $binding->getProperty();

                if ($ignoreValueMatcher) {
                    $property->setValue($entity, $this->resolveValue($property));
                    $touched = true;

                    continue;
                }

                if ($useChangeSet) {
                    $relatedChangeSet = $unitOfWork->getEntityChangeSet($changedRelated);

                    if (!array_key_exists($nested, $relatedChangeSet)) {
                        continue;
                    }

                    if (!$this->changeSetMatcher->valueMatches($binding, $relatedChangeSet[$nested][1] ?? null)) {
                        continue;
                    }

                    $property->setValue($entity, $this->resolveValue($property));
                    $touched = true;
                }
            }
        }

        return $touched;
    }

    /**
     * @template TEntity of object
     * @template TRelated of object
     *
     * @param TEntity $entity
     * @param TRelated $changedRelated
     */
    private function pathPointsTo(object $entity, string $path, object $changedRelated, ?string &$nested): bool
    {
        return $this->walkPath($entity, $path, $changedRelated, $nested, new \SplObjectStorage());
    }

    /**
     * @template TCurrent of object
     * @template TRelated of object
     *
     * @param TCurrent $current
     * @param TRelated $changedRelated
     * @param \SplObjectStorage<object, true> $visited
     */
    private function walkPath(object $current, string $path, object $changedRelated, ?string &$nested, \SplObjectStorage $visited): bool
    {
        if (isset($visited[$current])) {
            return false;
        }

        $visited[$current] = true;

        if (!str_contains($path, '.')) {
            return false;
        }

        [$head, $tail] = explode('.', $path, 2);
        $reflection = $this->changeSetMatcher->findProperty($current::class, $head);

        if ($reflection === null) {
            return false;
        }

        $related = $reflection->getValue($current);

        if ($related === $changedRelated) {
            $nested = $tail;

            return true;
        }

        if ($related instanceof Collection) {
            foreach ($related as $element) {
                if (!is_object($element)) {
                    continue;
                }

                if ($element === $changedRelated) {
                    $nested = $tail;

                    return true;
                }

                // Share $visited across sibling elements: cloning per
                // element used to defeat the cycle guard (an element could
                // walk back into a sibling that was already in flight) and
                // allocated a fresh SplObjectStorage per element.
                if ($this->walkPath($element, $tail, $changedRelated, $nested, $visited)) {
                    return true;
                }
            }

            return false;
        }

        if (!is_object($related)) {
            return false;
        }

        return $this->walkPath($related, $tail, $changedRelated, $nested, $visited);
    }

    /**
     * @param PersistentCollection<int|string, object> $collection
     * @param \SplObjectStorage<object, true> $scheduledUpdates
     */
    private function touchCollectionWatchers(
        PersistentCollection $collection,
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
        \SplObjectStorage $scheduledUpdates,
    ): void {
        $owner = $collection->getOwner();

        if ($owner === null) {
            return;
        }

        $fieldName = $collection->getMapping()->fieldName;
        $metadata = $this->metadataFactory->getMetadataFor($owner::class);

        if ($metadata->getChangeBindings() === []) {
            return;
        }

        $touched = false;

        foreach ($metadata->getChangeBindings() as $binding) {
            foreach ($binding->getFields() as $field) {
                if ($field !== $fieldName) {
                    continue;
                }

                $property = $binding->getProperty();
                $property->setValue($owner, $this->resolveValue($property));
                $touched = true;
            }
        }

        if ($touched) {
            if (!isset($scheduledUpdates[$owner])) {
                // Empty changeset here is a placeholder — recomputeSingleEntityChangeSet below
                // populates it from the owner's current property values.
                $unitOfWork->scheduleExtraUpdate($owner, []);
                $scheduledUpdates[$owner] = true;
            }

            $unitOfWork->recomputeSingleEntityChangeSet(
                $entityManager->getClassMetadata($owner::class),
                $owner,
            );
        }
    }
}
