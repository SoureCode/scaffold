<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Internal\ColumnNamer;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * @internal Builds a *History instance from a snapshot row. Reads each
 *           versioned scalar/embedded field, runs DBAL type conversion,
 *           rebuilds embeddeds bypassing their constructor (value-object
 *           style), and constructs the *History via its generated
 *           constructor.
 *
 *           Phase 5: also resolves versioned associations transitively,
 *           recursing into snapshot rows of related entities at the
 *           `<field>_version` (single) or join-table `target_version`
 *           (collection) captured at write time. A `(class, id, version)`
 *           cache short-circuits cycles: a second visit of an already-in-
 *           progress instance yields `null` so the constructor still
 *           completes (the read-only nature of `*History` rules out a
 *           late-bound self-reference).
 */
final class HistoryHydrator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly HistoryClassFactory $classFactory,
    ) {
    }

    /**
     * @param class-string $originalClass
     * @param array<string, mixed> $row
     * @param array<string, object|null> $visited
     */
    public function hydrate(string $originalClass, array $row, array &$visited = []): object
    {
        $historyClass = $this->classFactory->ensureGenerated($originalClass);
        $classMetadata = $this->entityManager->getClassMetadata($originalClass);
        $metadata = $this->metadataFactory->getMetadataFor($originalClass);
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();

        $arguments = [];

        $idField = $classMetadata->getSingleIdentifierFieldName();
        $idType = Type::getType($classMetadata->getFieldMapping($idField)->type);
        $idPhpValue = $idType->convertToPHPValue($row[VersionTableColumns::ENTITY_ID], $platform);
        $arguments['id'] = $idPhpValue;

        $version = (int) $row[VersionTableColumns::VERSION];
        $arguments['version'] = $version;

        $visitKey = $this->visitKey($originalClass, $idPhpValue, $version);

        if (array_key_exists($visitKey, $visited)) {
            // Second entry into the same (class, id, version) tuple during
            // a single hydration pass — cycle short-circuit.
            return $visited[$visitKey] ?? throw new \RuntimeException('cycle in history hydration');
        }

        $visited[$visitKey] = null;

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();

            if (isset($classMetadata->embeddedClasses[$fieldName])) {
                $arguments[$fieldName] = $this->hydrateEmbedded($classMetadata, $fieldName, $row, $platform);

                continue;
            }

            if (isset($classMetadata->fieldMappings[$fieldName])) {
                $mapping = $classMetadata->getFieldMapping($fieldName);
                $type = Type::getType($mapping->type);
                $columnName = $classMetadata->getColumnName($fieldName);
                $value = $type->convertToPHPValue($row[$columnName] ?? null, $platform);

                if (($mapping->enumType ?? null) !== null && $value !== null) {
                    $value = $mapping->enumType::from($value);
                }

                $arguments[$fieldName] = $value;

                continue;
            }

            if (!$classMetadata->hasAssociation($fieldName)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($fieldName);
            $targetClass = $assoc->targetEntity;
            $targetIsVersioned = $this->metadataFactory->isVersionable($targetClass);

            if ($classMetadata->isSingleValuedAssociation($fieldName)) {
                if (!$assoc->isOwningSide()) {
                    $arguments[$fieldName] = null;

                    continue;
                }

                $arguments[$fieldName] = $targetIsVersioned
                    ? $this->hydrateSingleRelation($assoc, $targetClass, $row, $visited)
                    : $this->loadLiveSingleRelation($assoc, $targetClass, $row);

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($fieldName)) {
                $arguments[$fieldName] = $targetIsVersioned
                    ? $this->hydrateCollectionRelation($originalClass, $fieldName, $targetClass, $row, $visited)
                    : $this->loadLiveCollectionRelation($originalClass, $fieldName, $targetClass, $row);
            }
        }

        $instance = new $historyClass(...$arguments);
        $visited[$visitKey] = $instance;

        return $instance;
    }

    /**
     * @param \Doctrine\ORM\Mapping\ClassMetadata<object> $classMetadata
     * @param array<string, mixed> $row
     */
    private function hydrateEmbedded(
        \Doctrine\ORM\Mapping\ClassMetadata $classMetadata,
        string $embeddedFieldName,
        array $row,
        \Doctrine\DBAL\Platforms\AbstractPlatform $platform,
    ): object {
        $embeddedClass = $classMetadata->embeddedClasses[$embeddedFieldName]['class'];
        $reflection = new \ReflectionClass($embeddedClass);
        $embedded = $reflection->newInstanceWithoutConstructor();

        foreach ($classMetadata->getFieldNames() as $flat) {
            if (!str_starts_with($flat, $embeddedFieldName . '.')) {
                continue;
            }

            $mapping = $classMetadata->getFieldMapping($flat);
            $columnName = $classMetadata->getColumnName($flat);
            $type = Type::getType($mapping->type);
            $value = $type->convertToPHPValue($row[$columnName] ?? null, $platform);

            $subProperty = substr($flat, strlen($embeddedFieldName) + 1);
            $reflection->getProperty($subProperty)->setValue($embedded, $value);
        }

        return $embedded;
    }

    /**
     * @param class-string $targetClass
     * @param array<string, mixed> $row
     * @param array<string, object|null> $visited
     */
    /**
     * @param class-string $targetClass
     * @param array<string, mixed> $row
     */
    private function loadLiveSingleRelation(\Doctrine\ORM\Mapping\AssociationMapping $assoc, string $targetClass, array $row): ?object
    {
        $idColumn = ColumnNamer::singleAssociationId($assoc);
        $targetId = $row[$idColumn] ?? null;

        if ($targetId === null) {
            return null;
        }

        return $this->entityManager->find($targetClass, $targetId);
    }

    /**
     * @param class-string $originalClass
     * @param class-string $targetClass
     * @param array<string, mixed> $row
     *
     * @return list<object>
     */
    private function loadLiveCollectionRelation(
        string $originalClass,
        string $fieldName,
        string $targetClass,
        array $row,
    ): array {
        if (!isset($row[VersionTableColumns::ID])) {
            return [];
        }

        $versionRowId = (int) $row[VersionTableColumns::ID];
        $sourceTable = $this->entityManager->getClassMetadata($originalClass)->getTableName();
        $joinTable = $this->metadataFactory->versionTableName($sourceTable) . '_' . $fieldName;

        $targetIds = $this->entityManager->getConnection()->createQueryBuilder()
            ->select(VersionTableColumns::JOIN_TARGET_ID)
            ->from($joinTable)
            ->where(VersionTableColumns::JOIN_VERSION_ID . ' = :version_id')
            ->orderBy(VersionTableColumns::JOIN_POSITION, 'ASC')
            ->setParameter('version_id', $versionRowId)
            ->fetchFirstColumn();

        $elements = [];

        foreach ($targetIds as $targetId) {
            if ($targetId === null) {
                continue;
            }

            $element = $this->entityManager->find($targetClass, $targetId);

            if ($element === null) {
                continue;
            }

            $elements[] = $element;
        }

        return $elements;
    }

    private function hydrateSingleRelation(
        \Doctrine\ORM\Mapping\AssociationMapping $assoc,
        string $targetClass,
        array $row,
        array &$visited,
    ): ?object {
        $idColumn = ColumnNamer::singleAssociationId($assoc);
        $versionColumn = ColumnNamer::singleAssociationVersion($assoc);

        $targetId = $row[$idColumn] ?? null;
        $targetVersion = $row[$versionColumn] ?? null;

        if ($targetId === null || $targetVersion === null) {
            return null;
        }

        $targetRow = $this->fetchSnapshotRow($targetClass, $targetId, (int) $targetVersion);

        if ($targetRow === null) {
            return null;
        }

        return $this->hydrate($targetClass, $targetRow, $visited);
    }

    /**
     * @param class-string $originalClass
     * @param class-string $targetClass
     * @param array<string, mixed> $row
     * @param array<string, object|null> $visited
     *
     * @return list<object>
     */
    private function hydrateCollectionRelation(
        string $originalClass,
        string $fieldName,
        string $targetClass,
        array $row,
        array &$visited,
    ): array {
        if (!isset($row[VersionTableColumns::ID])) {
            return [];
        }

        $versionRowId = (int) $row[VersionTableColumns::ID];
        $sourceTable = $this->entityManager->getClassMetadata($originalClass)->getTableName();
        $joinTable = $this->metadataFactory->versionTableName($sourceTable) . '_' . $fieldName;

        $joinRows = $this->entityManager->getConnection()->createQueryBuilder()
            ->select(VersionTableColumns::JOIN_TARGET_ID, VersionTableColumns::JOIN_TARGET_VERSION)
            ->from($joinTable)
            ->where(VersionTableColumns::JOIN_VERSION_ID . ' = :version_id')
            ->orderBy(VersionTableColumns::JOIN_POSITION, 'ASC')
            ->setParameter('version_id', $versionRowId)
            ->fetchAllAssociative();

        $elements = [];

        foreach ($joinRows as $joinRow) {
            $targetId = $joinRow[VersionTableColumns::JOIN_TARGET_ID] ?? null;
            $targetVersion = $joinRow[VersionTableColumns::JOIN_TARGET_VERSION] ?? null;

            if ($targetId === null || $targetVersion === null) {
                continue;
            }

            $targetRow = $this->fetchSnapshotRow($targetClass, $targetId, (int) $targetVersion);

            if ($targetRow === null) {
                continue;
            }

            $elements[] = $this->hydrate($targetClass, $targetRow, $visited);
        }

        return $elements;
    }

    /**
     * @param class-string $targetClass
     *
     * @return array<string, mixed>|null
     */
    private function fetchSnapshotRow(string $targetClass, mixed $targetId, int $targetVersion): ?array
    {
        $targetTable = $this->metadataFactory->versionTableName(
            $this->entityManager->getClassMetadata($targetClass)->getTableName(),
        );

        $row = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($targetTable)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere(VersionTableColumns::VERSION . ' = :version')
            ->setParameter('entity_id', $targetId)
            ->setParameter('version', $targetVersion)
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    private function visitKey(string $className, mixed $id, int $version): string
    {
        $idPart = is_object($id) ? spl_object_hash($id) : (string) $id;

        return $className . '#' . $idPart . '#' . $version;
    }
}
