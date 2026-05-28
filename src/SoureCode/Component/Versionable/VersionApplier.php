<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Internal\ColumnNamer;
use SoureCode\Component\Versionable\Internal\VersionRowApplier;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Mutates a live entity in place with values from a historical version
 * row. The caller is responsible for flushing the entity afterwards; the
 * flush writes a new version row reflecting the revert.
 *
 * The applier is a write-side helper for the version store. For read-only
 * inspection (history queries, hydrated detached copies), use
 * {@see VersionReader}.
 */
final class VersionApplier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly VersionRowApplier $rowApplier,
        private readonly VersionReader $reader,
    ) {
    }

    /**
     * Mutates $entity in place with values from the given historical version.
     *
     * @template T of object
     *
     * @param T $entity
     * @param list<string> $onlyFields property names to restore; empty means "all"
     *
     * @throws \RuntimeException when the version does not exist
     */
    public function applyVersion(
        object $entity,
        int $version,
        array $onlyFields = [],
        bool $cascade = false,
    ): AppliedVersion {
        return $this->applyVersionInternal($entity, $version, $onlyFields, $cascade, new \SplObjectStorage());
    }

    /**
     * @template T of object
     *
     * @param T $entity
     * @param list<string> $onlyFields
     * @param \SplObjectStorage<object, true> $visited
     */
    private function applyVersionInternal(
        object $entity,
        int $version,
        array $onlyFields,
        bool $cascade,
        \SplObjectStorage $visited,
    ): AppliedVersion {
        if (isset($visited[$entity])) {
            return new AppliedVersion($version, []);
        }

        $visited[$entity] = true;

        $className = $entity::class;
        $classMetadata = $this->entityManager->getClassMetadata($className);

        $idField = $classMetadata->getSingleIdentifierFieldName();
        $entityId = $classMetadata->getReflectionProperty($idField)->getValue($entity);

        if ($entityId === null) {
            throw new \RuntimeException(\sprintf('Cannot apply version to %s without an identifier.', $className));
        }

        $row = $this->reader->fetchVersionRow($className, $entityId, $version);

        if ($row === null) {
            throw new \RuntimeException(\sprintf('Version %d for %s#%s not found.', $version, $className, (string) $entityId));
        }

        $beforeValues = $this->snapshotPropertyValues($className, $entity, $onlyFields);
        $this->rowApplier->applyRow($className, $entity, $row, $onlyFields);
        $afterValues = $this->snapshotPropertyValues($className, $entity, $onlyFields);

        $changed = [];

        foreach ($afterValues as $name => $after) {
            if (($beforeValues[$name] ?? null) !== $after) {
                $changed[] = $name;
            }
        }

        if ($cascade) {
            $this->cascadeRestore($entity, $row, $onlyFields, $visited);
        }

        return new AppliedVersion($version, $changed);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $onlyFields
     * @param \SplObjectStorage<object, true> $visited
     */
    private function cascadeRestore(object $entity, array $row, array $onlyFields, \SplObjectStorage $visited): void
    {
        $className = $entity::class;
        $classMetadata = $this->entityManager->getClassMetadata($className);
        $metadata = $this->metadataFactory->getMetadataFor($className);

        foreach ($metadata->bindings as $binding) {
            $name = $binding->property->getName();

            if ($onlyFields !== [] && !in_array($name, $onlyFields, true)) {
                continue;
            }

            if (!$classMetadata->hasAssociation($name)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($name);

            if (!$this->metadataFactory->isVersionable($assoc->targetEntity)) {
                continue;
            }

            if ($classMetadata->isSingleValuedAssociation($name)) {
                if (!$assoc->isOwningSide()) {
                    continue;
                }

                $targetVersion = $row[ColumnNamer::singleAssociationVersion($assoc)] ?? null;
                $related = $binding->property->getValue($entity);

                if ($targetVersion !== null && is_object($related)) {
                    $this->applyVersionInternal($related, (int) $targetVersion, [], cascade: true, visited: $visited);
                }

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($name)) {
                $joinTable = $this->metadataFactory->versionTableName($classMetadata->getTableName()) . '_' . $name;
                $joinRows = $this->entityManager->getConnection()->createQueryBuilder()
                    ->select(VersionTableColumns::JOIN_TARGET_ID, VersionTableColumns::JOIN_TARGET_VERSION)
                    ->from($joinTable)
                    ->where(VersionTableColumns::JOIN_VERSION_ID . ' = :version_id')
                    ->orderBy(VersionTableColumns::JOIN_POSITION, 'ASC')
                    ->setParameter('version_id', $row[VersionTableColumns::ID])
                    ->fetchAllAssociative();

                $targetMetadata = $this->entityManager->getClassMetadata($assoc->targetEntity);
                $targetIdField = $targetMetadata->getSingleIdentifierFieldName();
                $targetRepository = $this->entityManager->getRepository($assoc->targetEntity);

                foreach ($joinRows as $joinRow) {
                    if (($joinRow[VersionTableColumns::JOIN_TARGET_VERSION] ?? null) === null) {
                        continue;
                    }

                    $related = $targetRepository->findOneBy([$targetIdField => $joinRow[VersionTableColumns::JOIN_TARGET_ID]]);

                    if ($related === null) {
                        continue;
                    }

                    $this->applyVersionInternal($related, (int) $joinRow[VersionTableColumns::JOIN_TARGET_VERSION], [], cascade: true, visited: $visited);
                }
            }
        }
    }

    /**
     * @param class-string $className
     * @param list<string> $onlyFields
     *
     * @return array<string, mixed>
     */
    private function snapshotPropertyValues(string $className, object $entity, array $onlyFields): array
    {
        $metadata = $this->metadataFactory->getMetadataFor($className);
        $classMetadata = $this->entityManager->getClassMetadata($className);
        $values = [];

        foreach ($metadata->bindings as $binding) {
            $name = $binding->property->getName();

            if ($onlyFields !== [] && !in_array($name, $onlyFields, true)) {
                continue;
            }

            // Doctrine's setFieldValue mutates the embedded value object in
            // place rather than replacing it, so two snapshots taken before
            // and after a restore are the same object reference. Flatten the
            // sub-fields so the comparison sees the changed values.
            if (isset($classMetadata->embeddedClasses[$name])) {
                $flat = [];

                foreach ($classMetadata->getFieldNames() as $flatField) {
                    if (!str_starts_with($flatField, $name . '.')) {
                        continue;
                    }

                    $flat[$flatField] = $classMetadata->getFieldValue($entity, $flatField);
                }

                $values[$name] = $flat;

                continue;
            }

            $values[$name] = $binding->property->getValue($entity);
        }

        return $values;
    }
}
