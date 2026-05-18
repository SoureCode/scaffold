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

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->touchScheduled($entity, $entityManager, $unitOfWork);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, useChangeSet: true, ignoreValueMatcher: false);
        }

        foreach ($unitOfWork->getScheduledEntityInsertions() as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, useChangeSet: true, ignoreValueMatcher: false);
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, useChangeSet: false, ignoreValueMatcher: true);
        }

        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $this->touchCollectionWatchers($collection, $entityManager, $unitOfWork);
        }

        foreach ($unitOfWork->getScheduledCollectionDeletions() as $collection) {
            $this->touchCollectionWatchers($collection, $entityManager, $unitOfWork);
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

        foreach ($metadata->getUpdateBindings() as $binding) {
            $property = $binding->getProperty();
            $property->setValue($entity, $this->resolveValue($property));
            $touched = true;
        }

        foreach ($metadata->getChangeBindings() as $binding) {
            if ($this->changeSetMatcher->matches($binding, $entity, $unitOfWork)) {
                $property = $binding->getProperty();
                $property->setValue($entity, $this->resolveValue($property));
                $touched = true;
            }
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
     */
    private function touchRelatedWatchers(
        object $changedRelated,
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
        bool $useChangeSet,
        bool $ignoreValueMatcher,
    ): void {
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            $metadata = $this->metadataFactory->getMetadataFor($class);

            if ($metadata->getChangeBindings() === []) {
                continue;
            }

            foreach ($entities as $entity) {
                if ($this->isScheduledForUpdate($entity, $unitOfWork)) {
                    continue;
                }

                if ($this->isScheduledForDeletion($entity, $unitOfWork)) {
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
        $reflection = (new \ReflectionClass($current::class))->getProperty($head);
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

                if ($this->walkPath($element, $tail, $changedRelated, $nested, clone $visited)) {
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

    private function touchCollectionWatchers(
        PersistentCollection $collection,
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
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
            if (!$this->isScheduledForUpdate($owner, $unitOfWork)) {
                $unitOfWork->scheduleExtraUpdate($owner, []);
            }

            $unitOfWork->recomputeSingleEntityChangeSet(
                $entityManager->getClassMetadata($owner::class),
                $owner,
            );
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    private function isScheduledForUpdate(object $entity, UnitOfWork $unitOfWork): bool
    {
        foreach ($unitOfWork->getScheduledEntityUpdates() as $scheduled) {
            if ($scheduled === $entity) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    private function isScheduledForDeletion(object $entity, UnitOfWork $unitOfWork): bool
    {
        foreach ($unitOfWork->getScheduledEntityDeletions() as $scheduled) {
            if ($scheduled === $entity) {
                return true;
            }
        }

        return false;
    }
}
