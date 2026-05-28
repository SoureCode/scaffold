<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Internal\ColumnNamer;
use SoureCode\Component\Versionable\Internal\History\HistoryHydrator;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Read-side of the version store. Queries the version table for historical
 * snapshots and hydrates them into runtime-generated `*History` instances
 * (read-only DTOs). Also compares two versions to produce a per-field diff.
 *
 * The returned objects are NOT the host entity class — they are distinct
 * `*History` classes generated under the `SoureCode\Versionable\Generated\`
 * namespace and intentionally have no setters. They expose the entity's own
 * scalar/embedded fields plus `getId()` and `getVersion()`.
 */
final class VersionReader
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly HistoryHydrator $historyHydrator,
    ) {
    }

    /**
     * @param class-string $className
     *
     * @return list<object>
     */
    public function findHistory(string $className, mixed $entityId): array
    {
        $rows = $this->newQuery($className)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->orderBy(VersionTableColumns::VERSION, 'ASC')
            ->setParameter('entity_id', $entityId, $this->idTypeName($className))
            ->fetchAllAssociative();

        $entities = [];

        foreach ($rows as $row) {
            $entities[] = $this->historyHydrator->hydrate($className, $row);
        }

        return $entities;
    }

    /**
     * @param class-string $className
     */
    public function findByVersion(string $className, mixed $entityId, int $version): ?object
    {
        $row = $this->newQuery($className)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere(VersionTableColumns::VERSION . ' = :version')
            ->setParameter('entity_id', $entityId, $this->idTypeName($className))
            ->setParameter('version', $version)
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->historyHydrator->hydrate($className, $row);
    }

    /**
     * @param class-string $className
     */
    public function findLatestVersion(string $className, mixed $entityId): ?object
    {
        $row = $this->newQuery($className)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->orderBy(VersionTableColumns::VERSION, 'DESC')
            ->setMaxResults(1)
            ->setParameter('entity_id', $entityId, $this->idTypeName($className))
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->historyHydrator->hydrate($className, $row);
    }

    /**
     * @param class-string $className
     *
     * @return array<string, mixed>|null
     */
    public function fetchVersionRow(string $className, mixed $entityId, int $version): ?array
    {
        $row = $this->newQuery($className)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere(VersionTableColumns::VERSION . ' = :version')
            ->setParameter('entity_id', $entityId, $this->idTypeName($className))
            ->setParameter('version', $version)
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    /**
     * @param class-string $className
     */
    public function diff(string $className, mixed $entityId, int $fromVersion, int $toVersion): ?VersionDiff
    {
        $beforeRow = $this->fetchVersionRow($className, $entityId, $fromVersion);
        $afterRow = $this->fetchVersionRow($className, $entityId, $toVersion);

        if ($beforeRow === null || $afterRow === null) {
            return null;
        }

        $classMetadata = $this->entityManager->getClassMetadata($className);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();
        $changes = [];

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();

            if (isset($classMetadata->embeddedClasses[$fieldName])) {
                foreach ($classMetadata->getFieldNames() as $flat) {
                    if (!str_starts_with($flat, $fieldName . '.')) {
                        continue;
                    }

                    $mapping = $classMetadata->getFieldMapping($flat);
                    $type = Type::getType($mapping->type);
                    $columnName = $classMetadata->getColumnName($flat);
                    $before = $type->convertToPHPValue($beforeRow[$columnName] ?? null, $platform);
                    $after = $type->convertToPHPValue($afterRow[$columnName] ?? null, $platform);

                    if ($before !== $after) {
                        $changes[$flat] = ['before' => $before, 'after' => $after];
                    }
                }

                continue;
            }

            if (isset($classMetadata->fieldMappings[$fieldName])) {
                $mapping = $classMetadata->getFieldMapping($fieldName);
                $type = Type::getType($mapping->type);
                $columnName = $classMetadata->getColumnName($fieldName);
                $before = $type->convertToPHPValue($beforeRow[$columnName] ?? null, $platform);
                $after = $type->convertToPHPValue($afterRow[$columnName] ?? null, $platform);

                $enumType = $mapping->enumType ?? null;

                if ($enumType !== null && $before !== null) {
                    $before = $enumType::from($before);
                }

                if ($enumType !== null && $after !== null) {
                    $after = $enumType::from($after);
                }

                if ($before !== $after) {
                    $changes[$fieldName] = ['before' => $before, 'after' => $after];
                }

                continue;
            }

            if (!$classMetadata->hasAssociation($fieldName)) {
                continue;
            }

            if ($classMetadata->isSingleValuedAssociation($fieldName)) {
                $idColumn = ColumnNamer::singleAssociationId($classMetadata->getAssociationMapping($fieldName));
                $before = $beforeRow[$idColumn] ?? null;
                $after = $afterRow[$idColumn] ?? null;

                if ($before !== $after) {
                    $changes[$fieldName] = ['before' => $before, 'after' => $after];
                }

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($fieldName)) {
                $beforeIds = $this->collectionIdsForVersion($className, $entityId, $fromVersion, $fieldName);
                $afterIds = $this->collectionIdsForVersion($className, $entityId, $toVersion, $fieldName);

                if ($beforeIds !== $afterIds) {
                    $changes[$fieldName] = ['before' => $beforeIds, 'after' => $afterIds];
                }
            }
        }

        return new VersionDiff($fromVersion, $toVersion, $changes);
    }

    /**
     * @param class-string $className
     *
     * @return list<int|string|null>
     */
    private function collectionIdsForVersion(string $className, mixed $entityId, int $version, string $fieldName): array
    {
        $versionTable = $this->metadataFactory->versionTableName(
            $this->entityManager->getClassMetadata($className)->getTableName(),
        );
        $joinTable = $versionTable . '_' . $fieldName;

        $ids = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('jt.' . VersionTableColumns::JOIN_TARGET_ID)
            ->from($joinTable, 'jt')
            ->innerJoin('jt', $versionTable, 'av', 'av.' . VersionTableColumns::ID . ' = jt.' . VersionTableColumns::JOIN_VERSION_ID)
            ->where('av.' . VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere('av.' . VersionTableColumns::VERSION . ' = :version')
            ->orderBy('jt.' . VersionTableColumns::JOIN_POSITION, 'ASC')
            ->setParameter('entity_id', $entityId, $this->idTypeName($className))
            ->setParameter('version', $version)
            ->fetchFirstColumn();

        $normalised = array_map(static fn ($value) => is_scalar($value) || $value === null ? $value : (string) $value, $ids);
        sort($normalised);

        return $normalised;
    }

    /**
     * @param class-string $className
     */
    private function newQuery(string $className): QueryBuilder
    {
        return $this->entityManager->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->metadataFactory->versionTableName(
                $this->entityManager->getClassMetadata($className)->getTableName(),
            ));
    }

    /**
     * @param class-string $className
     */
    private function idTypeName(string $className): string
    {
        $classMetadata = $this->entityManager->getClassMetadata($className);

        return $classMetadata->getFieldMapping($classMetadata->getSingleIdentifierFieldName())->type;
    }
}
