<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * @internal Inspects the UnitOfWork during onFlush and returns the set of
 *           versioned entities that must receive a snapshot this flush.
 *
 * A relationship change is one logical edit touching both ends, so both
 * versioned endpoints are returned — but only when the relation is
 * bidirectional (the other side maps it back). Unidirectional relations
 * bump only the side that owns the change.
 */
final class SnapshotTargetResolver
{
    public function __construct(
        private readonly VersionableMetadataFactory $metadataFactory,
    ) {
    }

    /**
     * @return \SplObjectStorage<object, true>
     */
    public function resolve(EntityManagerInterface $entityManager): \SplObjectStorage
    {
        $unitOfWork = $entityManager->getUnitOfWork();

        /** @var \SplObjectStorage<object, true> $targets */
        $targets = new \SplObjectStorage();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($this->hasVersionedChangeSetEntry($entity, $unitOfWork)) {
                $targets[$entity] = true;
            }

            foreach ($this->inverseOwnersFromChangeSet($entity, $unitOfWork->getEntityChangeSet($entity), $entityManager) as $owner) {
                $targets[$owner] = true;
            }
        }

        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $this->collectCollectionTargets($collection, $unitOfWork, $entityManager, $targets);
        }

        foreach ($unitOfWork->getScheduledCollectionDeletions() as $collection) {
            $this->collectCollectionTargets($collection, $unitOfWork, $entityManager, $targets);
        }

        foreach ($unitOfWork->getScheduledEntityInsertions() as $insertion) {
            foreach ($this->inverseOwnersFromCurrent($insertion, $entityManager) as $owner) {
                $targets[$owner] = true;
            }
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $deletion) {
            foreach ($this->inverseOwnersFromCurrent($deletion, $entityManager) as $owner) {
                if ($this->isScheduledForDeletion($owner, $unitOfWork)) {
                    continue;
                }

                $targets[$owner] = true;
            }

            foreach ($this->manyToManyElementsOfDeleted($deletion, $unitOfWork, $entityManager) as $element) {
                if ($this->isScheduledForDeletion($element, $unitOfWork)) {
                    continue;
                }

                $targets[$element] = true;
            }
        }

        return $targets;
    }

    /**
     * @param PersistentCollection<int|string, object> $collection
     * @param \SplObjectStorage<object, true> $targets
     */
    private function collectCollectionTargets(
        PersistentCollection $collection,
        UnitOfWork $unitOfWork,
        EntityManagerInterface $entityManager,
        \SplObjectStorage $targets,
    ): void {
        $owner = $this->collectionOwnerIfVersioned($collection);

        if ($owner === null) {
            return;
        }

        // An insert is not a snapshot — a new owner populating its collection
        // for the first time does not bump. Existing elements still do, so the
        // guard wraps only the owner.
        if (!$unitOfWork->isScheduledForInsert($owner)) {
            $targets[$owner] = true;
        }

        foreach ($this->changedManyToManyElements($collection, $unitOfWork, $entityManager) as $element) {
            $targets[$element] = true;
        }
    }

    /**
     * @return iterable<object>
     */
    private function inverseOwnersFromCurrent(object $entity, EntityManagerInterface $entityManager): iterable
    {
        $classMetadata = $entityManager->getClassMetadata($entity::class);

        foreach ($classMetadata->associationMappings as $field => $assoc) {
            if (!$classMetadata->isSingleValuedAssociation($field)) {
                continue;
            }

            $owner = $classMetadata->getReflectionProperty($field)->getValue($entity);

            yield from $this->inverseOwnerIfApplicable($owner, $field, $entityManager);
        }
    }

    /**
     * @param array<string, mixed> $changeSet
     *
     * @return iterable<object>
     */
    private function inverseOwnersFromChangeSet(object $entity, array $changeSet, EntityManagerInterface $entityManager): iterable
    {
        $classMetadata = $entityManager->getClassMetadata($entity::class);

        foreach ($classMetadata->associationMappings as $field => $assoc) {
            if (!$classMetadata->isSingleValuedAssociation($field)) {
                continue;
            }

            if (!array_key_exists($field, $changeSet)) {
                continue;
            }

            [$old, $new] = $changeSet[$field];

            yield from $this->inverseOwnerIfApplicable($old, $field, $entityManager);
            yield from $this->inverseOwnerIfApplicable($new, $field, $entityManager);
        }
    }

    /**
     * @return iterable<object>
     */
    private function inverseOwnerIfApplicable(mixed $owner, string $field, EntityManagerInterface $entityManager): iterable
    {
        if (!is_object($owner)) {
            return;
        }

        if (!$this->metadataFactory->isVersionable($owner::class)) {
            return;
        }

        // The owner is being created in this same flush — an insert is not a
        // snapshot, so the freshly attached relation does not bump it.
        if ($entityManager->getUnitOfWork()->isScheduledForInsert($owner)) {
            return;
        }

        if ($this->mapsInverseOf($owner, $field, $entityManager)) {
            yield $owner;
        }
    }

    /**
     * @return iterable<object>
     */
    private function manyToManyElementsOfDeleted(
        object $entity,
        UnitOfWork $unitOfWork,
        EntityManagerInterface $entityManager,
    ): iterable {
        $classMetadata = $entityManager->getClassMetadata($entity::class);

        foreach ($classMetadata->associationMappings as $field => $assoc) {
            if (!$assoc->isManyToMany()) {
                continue;
            }

            $mappedBy = $assoc->mappedBy ?? null;
            $inversedBy = $assoc->inversedBy ?? null;

            if ($mappedBy === null && $inversedBy === null) {
                continue;
            }

            if ($mappedBy === null) {
                $collection = $classMetadata->getReflectionProperty($field)->getValue($entity);

                if (!is_iterable($collection)) {
                    continue;
                }

                foreach ($collection as $element) {
                    if (!is_object($element)) {
                        continue;
                    }

                    if ($unitOfWork->isScheduledForInsert($element)) {
                        continue;
                    }

                    if (!$this->metadataFactory->isVersionable($element::class)) {
                        continue;
                    }

                    yield $element;
                }

                continue;
            }

            $targetEntity = $assoc->targetEntity;

            if (!$this->metadataFactory->isVersionable($targetEntity)) {
                continue;
            }

            $referencing = $entityManager->createQueryBuilder()
                ->select('owningSide')
                ->from($targetEntity, 'owningSide')
                ->innerJoin('owningSide.' . $mappedBy, 'deletedEntity')
                ->where('deletedEntity = :deleted')
                ->setParameter('deleted', $entity)
                ->getQuery()
                ->getResult();

            foreach ($referencing as $element) {
                if ($unitOfWork->isScheduledForInsert($element)) {
                    continue;
                }

                yield $element;
            }
        }
    }

    /**
     * @param PersistentCollection<int|string, object> $collection
     *
     * @return iterable<object>
     */
    private function changedManyToManyElements(
        PersistentCollection $collection,
        UnitOfWork $unitOfWork,
        EntityManagerInterface $entityManager,
    ): iterable {
        $mapping = $collection->getMapping();

        // Only ManyToMany: a relation change there leaves both rows untouched,
        // so the element is not detected anywhere else. OneToMany elements are
        // children whose own FK changed and are picked up as their own update.
        if (!$mapping->isManyToMany()) {
            return;
        }

        $field = $mapping->fieldName;

        foreach ([...$collection->getInsertDiff(), ...$collection->getDeleteDiff()] as $element) {
            if (!is_object($element)) {
                continue;
            }

            if ($unitOfWork->isScheduledForInsert($element)) {
                continue;
            }

            if (!$this->metadataFactory->isVersionable($element::class)) {
                continue;
            }

            if ($this->mapsInverseOf($element, $field, $entityManager)) {
                yield $element;
            }
        }
    }

    private function mapsInverseOf(object $owner, string $field, EntityManagerInterface $entityManager): bool
    {
        $classMetadata = $entityManager->getClassMetadata($owner::class);

        foreach ($classMetadata->associationMappings as $assoc) {
            if (($assoc->mappedBy ?? null) === $field) {
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

    private function hasVersionedChangeSetEntry(object $entity, UnitOfWork $unitOfWork): bool
    {
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);

        if ($metadata->isEmpty()) {
            return false;
        }

        $changeSet = $unitOfWork->getEntityChangeSet($entity);

        foreach ($metadata->bindings as $binding) {
            if (array_key_exists($binding->property->getName(), $changeSet)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param PersistentCollection<int|string, object> $collection
     */
    private function collectionOwnerIfVersioned(PersistentCollection $collection): ?object
    {
        $owner = $collection->getOwner();

        if ($owner === null) {
            return null;
        }

        if (!$this->metadataFactory->isVersionable($owner::class)) {
            return null;
        }

        $fieldName = $collection->getMapping()->fieldName;

        foreach ($this->metadataFactory->getMetadataFor($owner::class)->bindings as $binding) {
            if ($binding->property->getName() === $fieldName) {
                return $owner;
            }
        }

        return null;
    }
}
