<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Psr\Clock\ClockInterface;
use SoureCode\Component\Timestampable\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\Timestampable\Metadata\ChangedAtBinding;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Timestampable\TimestampableInterface;

final class TimestampableListener
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly TimestampableMetadataFactory $metadataFactory,
        private readonly TimestampFactory $timestampFactory,
        private readonly ChangeSetMatcher $changeSetMatcher,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);

        if (!$metadata->isEmpty()) {
            foreach ($metadata->createdBindings as $binding) {
                if ($binding->property->getValue($entity) === null) {
                    $binding->property->setValue($entity, $this->timestampFactory->makeFor($binding->property));
                }
            }

            foreach ($metadata->updatedBindings as $binding) {
                if ($binding->nullable) {
                    continue;
                }

                if ($binding->property->getValue($entity) === null) {
                    $binding->property->setValue($entity, $this->timestampFactory->makeFor($binding->property));
                }
            }

            return;
        }

        if (!$entity instanceof TimestampableInterface) {
            return;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if ($entity->getCreatedAt() === null) {
            $entity->setCreatedAt($now);
        }

        if ($entity->getUpdatedAt() === null) {
            $entity->setUpdatedAt($now);
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->touchScheduled($entity, $entityManager, $unitOfWork);
        }

        $changedRelatedUpdates = $unitOfWork->getScheduledEntityUpdates();
        $changedRelatedInserts = $unitOfWork->getScheduledEntityInsertions();
        $changedRelatedDeletes = $unitOfWork->getScheduledEntityDeletions();

        foreach ($changedRelatedUpdates as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, useChangeSet: true, ignoreValueMatcher: false);
        }

        foreach ($changedRelatedInserts as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, useChangeSet: true, ignoreValueMatcher: false);
        }

        foreach ($changedRelatedDeletes as $changedRelated) {
            $this->touchRelatedWatchers($changedRelated, $entityManager, $unitOfWork, useChangeSet: false, ignoreValueMatcher: true);
        }

        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $this->touchCollectionWatchers($collection, $entityManager, $unitOfWork);
        }

        foreach ($unitOfWork->getScheduledCollectionDeletions() as $collection) {
            $this->touchCollectionWatchers($collection, $entityManager, $unitOfWork);
        }
    }

    private function touchScheduled(object $entity, EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): void
    {
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);

        if ($metadata->isEmpty()) {
            $this->touchInterfaceFallback($entity, $entityManager, $unitOfWork);

            return;
        }

        $touched = false;

        foreach ($metadata->updatedBindings as $binding) {
            $binding->property->setValue($entity, $this->timestampFactory->makeFor($binding->property));
            $touched = true;
        }

        foreach ($metadata->changedBindings as $binding) {
            if ($this->changeSetMatcher->matches($binding, $entity, $unitOfWork)) {
                $binding->property->setValue($entity, $this->timestampFactory->makeFor($binding->property));
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

    private function touchInterfaceFallback(object $entity, EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): void
    {
        if (!$entity instanceof TimestampableInterface) {
            return;
        }

        $entity->setUpdatedAt(\DateTimeImmutable::createFromInterface($this->clock->now()));
        $unitOfWork->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata($entity::class),
            $entity,
        );
    }

    private function touchRelatedWatchers(
        object $changedRelated,
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
        bool $useChangeSet,
        bool $ignoreValueMatcher,
    ): void {
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            $metadata = $this->metadataFactory->getMetadataFor($class);

            if ($metadata->changedBindings === []) {
                continue;
            }

            foreach ($entities as $entity) {
                if ($this->isScheduledForUpdate($entity, $unitOfWork)) {
                    continue;
                }

                if ($this->isScheduledForDeletion($entity, $unitOfWork)) {
                    continue;
                }

                if ($this->touchWatcher($entity, $changedRelated, $unitOfWork, $metadata->changedBindings, $useChangeSet, $ignoreValueMatcher)) {
                    $unitOfWork->recomputeSingleEntityChangeSet(
                        $entityManager->getClassMetadata($entity::class),
                        $entity,
                    );
                }
            }
        }
    }

    /**
     * @param list<ChangedAtBinding> $bindings
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
            foreach ($binding->fields as $field) {
                if (!str_contains($field, '.')) {
                    continue;
                }

                $nested = null;

                if (!$this->pathPointsTo($entity, $field, $changedRelated, $nested)) {
                    continue;
                }

                if ($ignoreValueMatcher) {
                    $binding->property->setValue($entity, $this->timestampFactory->makeFor($binding->property));
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

                    $binding->property->setValue($entity, $this->timestampFactory->makeFor($binding->property));
                    $touched = true;
                }
            }
        }

        return $touched;
    }

    private function pathPointsTo(object $entity, string $path, object $changedRelated, ?string &$nested): bool
    {
        return $this->walkPath($entity, $path, $changedRelated, $nested, new \SplObjectStorage());
    }

    /**
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

        if ($related instanceof \Doctrine\Common\Collections\Collection) {
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

        if ($metadata->changedBindings === []) {
            return;
        }

        $touched = false;

        foreach ($metadata->changedBindings as $binding) {
            foreach ($binding->fields as $field) {
                if ($field !== $fieldName) {
                    continue;
                }

                $binding->property->setValue($owner, $this->timestampFactory->makeFor($binding->property));
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

    private function isScheduledForUpdate(object $entity, UnitOfWork $unitOfWork): bool
    {
        foreach ($unitOfWork->getScheduledEntityUpdates() as $scheduled) {
            if ($scheduled === $entity) {
                return true;
            }
        }

        return false;
    }

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
