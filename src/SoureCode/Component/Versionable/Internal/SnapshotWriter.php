<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Metadata\VersionedBinding;

/**
 * @internal Write-side counterpart to {@see \SoureCode\Component\Versionable\Internal\VersionRowApplier}:
 *           builds and inserts one snapshot row (plus its collection join rows)
 *           for a versioned entity at flush time. Version numbering is owned by
 *           {@see VersionIncrementer}; this reads the already-bumped value.
 */
final class SnapshotWriter
{
    public function __construct(
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function write(object $entity, EntityManagerInterface $entityManager): void
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

                    $flatMapping = $classMetadata->getFieldMapping($flat);
                    $columnName = $classMetadata->getColumnName($flat);
                    $value = $classMetadata->getFieldValue($entity, $flat);

                    if (($flatMapping->enumType ?? null) !== null && $value instanceof \BackedEnum) {
                        $value = $value->value;
                    }

                    $row[$columnName] = Type::getType($flatMapping->type)->convertToDatabaseValue($value, $platform);
                }

                continue;
            }

            if (isset($classMetadata->fieldMappings[$fieldName])) {
                $fieldMapping = $classMetadata->getFieldMapping($fieldName);
                $columnName = $classMetadata->getColumnName($fieldName);
                $value = $binding->property->getValue($entity);

                if (($fieldMapping->enumType ?? null) !== null && $value instanceof \BackedEnum) {
                    $value = $value->value;
                }

                $row[$columnName] = Type::getType($fieldMapping->type)->convertToDatabaseValue($value, $platform);

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

        $versionField = $metadata->versionField;

        if ($versionField === null) {
            throw new \RuntimeException(\sprintf('Versioned entity %s must declare a #[Version] property.', $classMetadata->getName()));
        }

        $row[VersionTableColumns::VERSION] = (int) $classMetadata->getFieldValue($entity, $versionField);

        $connection->insert($versionTable, $row);
        $versionRowId = (int) $connection->lastInsertId();

        foreach ($collectionInserts as $entry) {
            $this->insertCollectionRows($entity, $entry['binding'], $entry['targetClass'], $versionTable, $versionRowId, $entityManager);
        }
    }

    /**
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
            $targetVersion = $this->readTargetVersion($related, $assoc->targetEntity, $entityManager);
        }

        return [$idDbValue, $targetVersion];
    }

    /**
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
                $row[VersionTableColumns::JOIN_TARGET_VERSION] = $this->readTargetVersion($element, $targetClass, $entityManager);
            }

            $connection->insert($joinTable, $row);
        }
    }

    /**
     * Read the related entity's #[Version] field in memory rather than
     * querying its snapshot table. Insert-as-snapshot means partners in the
     * same flush may not have their snapshot row written yet at the moment
     * this runs; the in-memory value is the canonical, already-bumped truth.
     *
     * @param class-string $targetClass
     */
    private function readTargetVersion(object $related, string $targetClass, EntityManagerInterface $entityManager): ?int
    {
        $versionField = $this->metadataFactory->getMetadataFor($targetClass)->versionField;

        if ($versionField === null) {
            return null;
        }

        $value = $entityManager->getClassMetadata($targetClass)->getFieldValue($related, $versionField);

        return $value === null ? null : (int) $value;
    }
}
