<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Metadata\VersionedBinding;

final class VersionableListener
{
    /**
     * @var \SplObjectStorage<object, true>|null
     */
    private ?\SplObjectStorage $pendingSnapshots = null;

    public function __construct(
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $targets = $this->pendingSnapshots ?? new \SplObjectStorage();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($this->hasVersionedChangeSetEntry($entity, $unitOfWork)) {
                $targets[$entity] = true;
            }
        }

        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $owner = $this->collectionOwnerIfVersioned($collection);

            if ($owner !== null) {
                $targets[$owner] = true;
            }
        }

        foreach ($unitOfWork->getScheduledCollectionDeletions() as $collection) {
            $owner = $this->collectionOwnerIfVersioned($collection);

            if ($owner !== null) {
                $targets[$owner] = true;
            }
        }

        foreach ($unitOfWork->getScheduledEntityInsertions() as $insertion) {
            foreach ($this->resolveInverseOwners($insertion, $entityManager) as $owner) {
                $targets[$owner] = true;
            }
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $deletion) {
            foreach ($this->resolveInverseOwners($deletion, $entityManager) as $owner) {
                if ($this->isScheduledForDeletion($owner, $unitOfWork)) {
                    continue;
                }

                $targets[$owner] = true;
            }
        }

        $this->pendingSnapshots = $targets;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pendingSnapshots === null || $this->pendingSnapshots->count() === 0) {
            $this->pendingSnapshots = null;

            return;
        }

        $entityManager = $args->getObjectManager();
        $pending = $this->pendingSnapshots;
        $this->pendingSnapshots = null;

        foreach ($pending as $entity) {
            $this->writeSnapshot($entity, $entityManager);
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return iterable<object>
     */
    private function resolveInverseOwners(object $entity, EntityManagerInterface $entityManager): iterable
    {
        $classMetadata = $entityManager->getClassMetadata($entity::class);

        foreach ($classMetadata->associationMappings as $field => $assoc) {
            if (!$classMetadata->isSingleValuedAssociation($field)) {
                continue;
            }

            $owner = $classMetadata->getReflectionProperty($field)->getValue($entity);

            if (!is_object($owner)) {
                continue;
            }

            if (!$this->metadataFactory->isVersionable($owner::class)) {
                continue;
            }

            $ownerMetadata = $this->metadataFactory->getMetadataFor($owner::class);

            $ownerClassMetadata = $entityManager->getClassMetadata($owner::class);

            foreach ($ownerMetadata->bindings as $binding) {
                $name = $binding->property->getName();

                if (!$ownerClassMetadata->hasAssociation($name)) {
                    continue;
                }

                if (!$ownerClassMetadata->isCollectionValuedAssociation($name)) {
                    continue;
                }

                $ownerAssoc = $ownerClassMetadata->getAssociationMapping($name);

                if (($ownerAssoc->mappedBy ?? null) !== $field) {
                    continue;
                }

                yield $owner;
            }
        }
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

    /**
     * @template T of object
     *
     * @param T $entity
     */
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

    /**
     * @template T of object
     *
     * @param T $entity
     */
    private function writeSnapshot(object $entity, EntityManagerInterface $entityManager): void
    {
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);
        $classMetadata = $entityManager->getClassMetadata($entity::class);
        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $sourceTable = $classMetadata->getTableName();
        $versionTable = VersionableMetadataFactory::versionTableName($sourceTable);

        $idField = $classMetadata->getSingleIdentifierFieldName();
        $entityId = $classMetadata->getReflectionProperty($idField)->getValue($entity);

        if ($entityId === null) {
            $this->logger->warning(
                'Versionable: skipped snapshot for {class} because the entity has no identifier at postFlush — this indicates the entity was scheduled for a version write but never persisted.',
                ['class' => $entity::class],
            );

            return;
        }

        $idType = Type::getType($classMetadata->getFieldMapping($idField)->type);
        $entityIdValue = $idType->convertToDatabaseValue($entityId, $platform);

        // MAX(version) + 1 is racy under concurrent writers; the (entity_id, version)
        // unique index on the version table rejects duplicates. Callers that expect
        // contention must wrap the flush in a retry loop.
        $nextVersion = ((int) $connection->fetchOne(
            \sprintf('SELECT MAX(version) FROM %s WHERE entity_id = ?', $versionTable),
            [$entityIdValue],
        )) + 1;

        $row = [
            'entity_id' => $entityIdValue,
            'version' => $nextVersion,
            'created_at' => Type::getType(Types::DATETIMETZ_IMMUTABLE)
                ->convertToDatabaseValue(\DateTimeImmutable::createFromInterface($this->clock->now()), $platform),
        ];

        /** @var array<int, array{binding: VersionedBinding, targetClass: class-string}> $collectionInserts */
        $collectionInserts = [];

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();

            if ($classMetadata->hasField($fieldName)) {
                $columnName = $classMetadata->getColumnName($fieldName);
                $value = $binding->property->getValue($entity);
                $type = Type::getType($classMetadata->getFieldMapping($fieldName)->type);
                $row[$columnName] = $type->convertToDatabaseValue($value, $platform);

                continue;
            }

            if (!$classMetadata->hasAssociation($fieldName)) {
                continue;
            }

            if ($classMetadata->isSingleValuedAssociation($fieldName)) {
                [$idValue, $targetVersion] = $this->captureSingleAssociation($entity, $binding, $classMetadata, $connection, $entityManager);
                $row[$fieldName . '_id'] = $idValue;

                if ($this->metadataFactory->isVersionable($classMetadata->getAssociationMapping($fieldName)->targetEntity)) {
                    $row[$fieldName . '_version'] = $targetVersion;
                }

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($fieldName)) {
                $collectionInserts[] = ['binding' => $binding, 'targetClass' => $classMetadata->getAssociationMapping($fieldName)->targetEntity];
            }
        }

        $connection->insert($versionTable, $row);
        $versionRowId = (int) $connection->lastInsertId();

        foreach ($collectionInserts as $entry) {
            $this->insertCollectionRows($entity, $entry['binding'], $entry['targetClass'], $versionTable, $versionRowId, $entityManager);
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return array{0: mixed, 1: int|null}
     */
    private function captureSingleAssociation(
        object $entity,
        VersionedBinding $binding,
        ClassMetadata $classMetadata,
        Connection $connection,
        EntityManagerInterface $entityManager,
    ): array {
        $related = $binding->property->getValue($entity);

        if (!is_object($related)) {
            return [null, null];
        }

        $assoc = $classMetadata->getAssociationMapping($binding->property->getName());
        $targetMetadata = $entityManager->getClassMetadata($assoc->targetEntity);
        $targetIdField = $targetMetadata->getSingleIdentifierFieldName();
        $targetIdValue = $targetMetadata->getReflectionProperty($targetIdField)->getValue($related);

        if ($targetIdValue === null) {
            return [null, null];
        }

        $platform = $connection->getDatabasePlatform();
        $idType = Type::getType($targetMetadata->getFieldMapping($targetIdField)->type);
        $idDbValue = $idType->convertToDatabaseValue($targetIdValue, $platform);

        $targetVersion = null;
        if ($this->metadataFactory->isVersionable($assoc->targetEntity)) {
            $targetVersion = $this->loadCurrentTargetVersion($assoc->targetEntity, $idDbValue, $connection, $entityManager);
        }

        return [$idDbValue, $targetVersion];
    }

    /**
     * @template T of object
     *
     * @param T $entity
     * @param class-string $targetClass
     */
    private function insertCollectionRows(
        object $entity,
        VersionedBinding $binding,
        string $targetClass,
        string $versionTable,
        int $versionRowId,
        EntityManagerInterface $entityManager,
    ): void {
        $value = $binding->property->getValue($entity);

        if (!$value instanceof Collection) {
            return;
        }

        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $targetMetadata = $entityManager->getClassMetadata($targetClass);
        $targetIdField = $targetMetadata->getSingleIdentifierFieldName();
        $idType = Type::getType($targetMetadata->getFieldMapping($targetIdField)->type);

        $joinTable = $versionTable . '_' . $binding->property->getName();
        $captureVersion = $this->metadataFactory->isVersionable($targetClass);

        foreach ($value as $element) {
            if (!is_object($element)) {
                continue;
            }

            $elementId = $targetMetadata->getReflectionProperty($targetIdField)->getValue($element);

            if ($elementId === null) {
                continue;
            }

            $idDbValue = $idType->convertToDatabaseValue($elementId, $platform);

            $row = [
                'version_id' => $versionRowId,
                'target_id' => $idDbValue,
            ];

            if ($captureVersion) {
                $row['target_version'] = $this->loadCurrentTargetVersion($targetClass, $idDbValue, $connection, $entityManager);
            }

            $connection->insert($joinTable, $row);
        }
    }

    /**
     * @param class-string $targetClass
     */
    private function loadCurrentTargetVersion(
        string $targetClass,
        mixed $targetIdValue,
        Connection $connection,
        EntityManagerInterface $entityManager,
    ): ?int {
        $targetVersionTable = VersionableMetadataFactory::versionTableName(
            $entityManager->getClassMetadata($targetClass)->getTableName(),
        );

        $value = $connection->fetchOne(
            \sprintf('SELECT MAX(version) FROM %s WHERE entity_id = ?', $targetVersionTable),
            [$targetIdValue],
        );

        return $value === null || $value === false ? null : (int) $value;
    }
}
