<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

        // postFlush fires after the entity transaction has committed; wrap
        // the snapshot writes in their own transaction so a mid-batch
        // failure rolls back the partial audit history instead of leaving
        // half the snapshots written.
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            foreach ($pending as $entity) {
                $this->writeSnapshot($entity, $entityManager);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            $committed = [];

            foreach ($pending as $entity) {
                $committed[] = $entity::class;
            }

            $this->logger->error(
                'Versionable: entity changes were committed but the audit snapshot transaction failed; history for {classes} is missing.',
                [
                    'classes' => implode(', ', array_unique($committed)),
                    'exception' => $exception,
                ],
            );

            throw $exception;
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
        $versionTable = $this->metadataFactory->versionTableName($sourceTable);

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

        $row = [
            VersionTableColumns::ENTITY_ID => $entityIdValue,
            VersionTableColumns::CREATED_AT => Type::getType(Types::DATETIMETZ_IMMUTABLE)
                ->convertToDatabaseValue(\DateTimeImmutable::createFromInterface($this->clock->now()), $platform),
        ];

        // STI: the version table is rooted at the source table and shared
        // across the hierarchy; stamp the discriminator value so restore can
        // re-instantiate the right subclass.
        if (!$classMetadata->isInheritanceTypeNone() && $classMetadata->discriminatorColumn !== null) {
            $row[$classMetadata->discriminatorColumn['name']] = $classMetadata->discriminatorValue;
        }

        /** @var array<int, array{binding: VersionedBinding, targetClass: class-string}> $collectionInserts */
        $collectionInserts = [];

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();

            // Embeddable check first: ClassMetadata::hasField() returns true
            // for embedded parents too. Reading getFieldMapping() on an
            // embedded parent throws.
            if (isset($classMetadata->embeddedClasses[$fieldName])) {
                foreach ($classMetadata->getFieldNames() as $flat) {
                    if (!str_starts_with($flat, $fieldName . '.')) {
                        continue;
                    }

                    $columnName = $classMetadata->getColumnName($flat);
                    $value = $classMetadata->getFieldValue($entity, $flat);
                    $type = Type::getType($classMetadata->getFieldMapping($flat)->type);
                    $row[$columnName] = $type->convertToDatabaseValue($value, $platform);
                }

                continue;
            }

            if (isset($classMetadata->fieldMappings[$fieldName])) {
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
                $row[$fieldName . VersionTableColumns::SINGLE_ASSOC_ID_SUFFIX] = $idValue;

                if ($this->metadataFactory->isVersionable($classMetadata->getAssociationMapping($fieldName)->targetEntity)) {
                    $row[$fieldName . VersionTableColumns::SINGLE_ASSOC_VERSION_SUFFIX] = $targetVersion;
                }

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($fieldName)) {
                $collectionInserts[] = ['binding' => $binding, 'targetClass' => $classMetadata->getAssociationMapping($fieldName)->targetEntity];
            }
        }

        $versionRowId = $this->insertWithRaceRetry($connection, $versionTable, $entityIdValue, $row);

        foreach ($collectionInserts as $entry) {
            $this->insertCollectionRows($entity, $entry['binding'], $entry['targetClass'], $versionTable, $versionRowId, $entityManager);
        }
    }

    /**
     * Computes MAX(version)+1 and inserts the version row, retrying on the
     * (entity_id, version) unique-index violation that two concurrent writers
     * would race into. The unique index is the authority — we just rerun the
     * read-then-insert until it succeeds.
     *
     * @param array<string, mixed> $row
     */
    private function insertWithRaceRetry(
        Connection $connection,
        string $versionTable,
        mixed $entityIdValue,
        array $row,
    ): int {
        $attempts = 0;
        $maxAttempts = 5;

        while (true) {
            $attempts++;

            $row[VersionTableColumns::VERSION] = $this->nextVersionFor($connection, $versionTable, $entityIdValue);

            try {
                $connection->insert($versionTable, $row);

                return (int) $connection->lastInsertId();
            } catch (UniqueConstraintViolationException $e) {
                if ($attempts >= $maxAttempts) {
                    throw $e;
                }

                $this->logger->warning(
                    'Versionable: version race on {table}#{entity_id}, retrying (attempt {attempt}/{max}).',
                    [
                        'table' => $versionTable,
                        'entity_id' => $entityIdValue,
                        'attempt' => $attempts,
                        'max' => $maxAttempts,
                    ],
                );
            }
        }
    }

    private function nextVersionFor(Connection $connection, string $versionTable, mixed $entityIdValue): int
    {
        $value = $connection->createQueryBuilder()
            ->select(\sprintf('MAX(%s)', VersionTableColumns::VERSION))
            ->from($versionTable)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->setParameter('entity_id', $entityIdValue)
            ->fetchOne();

        return $value === null || $value === false ? 1 : ((int) $value) + 1;
    }

    /**
     * @template T of object
     *
     * @param T $entity
     * @param ClassMetadata<object> $classMetadata
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
        $position = 0;

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
                VersionTableColumns::JOIN_VERSION_ID => $versionRowId,
                VersionTableColumns::JOIN_POSITION => $position++,
                VersionTableColumns::JOIN_TARGET_ID => $idDbValue,
            ];

            if ($captureVersion) {
                $row[VersionTableColumns::JOIN_TARGET_VERSION] = $this->loadCurrentTargetVersion($targetClass, $idDbValue, $connection, $entityManager);
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
        $targetVersionTable = $this->metadataFactory->versionTableName(
            $entityManager->getClassMetadata($targetClass)->getTableName(),
        );

        $value = $connection->createQueryBuilder()
            ->select(\sprintf('MAX(%s)', VersionTableColumns::VERSION))
            ->from($targetVersionTable)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->setParameter('entity_id', $targetIdValue)
            ->fetchOne();

        return $value === null || $value === false ? null : (int) $value;
    }
}
