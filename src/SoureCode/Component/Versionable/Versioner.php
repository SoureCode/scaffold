<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

final class Versioner implements VersionerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return list<T>
     */
    public function findHistory(string $className, int|string $entityId): array
    {
        $rows = $this->newQuery($className)
            ->where('entity_id = :entity_id')
            ->orderBy('version', 'ASC')
            ->setParameter('entity_id', $entityId)
            ->fetchAllAssociative();

        $entities = [];

        foreach ($rows as $row) {
            $entities[] = $this->hydrate($className, $row);
        }

        return $entities;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public function findByVersion(string $className, int|string $entityId, int $version): ?object
    {
        $row = $this->newQuery($className)
            ->where('entity_id = :entity_id')
            ->andWhere('version = :version')
            ->setParameter('entity_id', $entityId)
            ->setParameter('version', $version)
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($className, $row);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public function findLatestVersion(string $className, int|string $entityId): ?object
    {
        $row = $this->newQuery($className)
            ->where('entity_id = :entity_id')
            ->orderBy('version', 'DESC')
            ->setMaxResults(1)
            ->setParameter('entity_id', $entityId)
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($className, $row);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     * @param list<string> $onlyFields
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

        $row = $this->newQuery($className)
            ->where('entity_id = :entity_id')
            ->andWhere('version = :version')
            ->setParameter('entity_id', $entityId)
            ->setParameter('version', $version)
            ->fetchAssociative();

        if ($row === false) {
            throw new \RuntimeException(\sprintf('Version %d for %s#%s not found.', $version, $className, (string) $entityId));
        }

        $beforeValues = $this->snapshotPropertyValues($className, $entity, $onlyFields);
        $this->applyRowOntoEntity($className, $entity, $row, $onlyFields);
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
                $targetVersion = $row[$name . '_version'] ?? null;
                $related = $binding->property->getValue($entity);

                if ($targetVersion !== null && is_object($related)) {
                    $this->applyVersionInternal($related, (int) $targetVersion, [], cascade: true, visited: $visited);
                }

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($name)) {
                $joinTable = $this->getVersionTable($className) . '_' . $name;
                $joinRows = $this->entityManager->getConnection()->createQueryBuilder()
                    ->select('target_id', 'target_version')
                    ->from($joinTable)
                    ->where('version_id = :version_id')
                    ->orderBy('position', 'ASC')
                    ->setParameter('version_id', $row['id'])
                    ->fetchAllAssociative();

                $targetMetadata = $this->entityManager->getClassMetadata($assoc->targetEntity);
                $targetIdField = $targetMetadata->getSingleIdentifierFieldName();
                $targetRepository = $this->entityManager->getRepository($assoc->targetEntity);

                foreach ($joinRows as $joinRow) {
                    if (($joinRow['target_version'] ?? null) === null) {
                        continue;
                    }

                    $related = $targetRepository->findOneBy([$targetIdField => $joinRow['target_id']]);

                    if ($related === null) {
                        continue;
                    }

                    $this->applyVersionInternal($related, (int) $joinRow['target_version'], [], cascade: true, visited: $visited);
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
        $values = [];

        foreach ($metadata->bindings as $binding) {
            $name = $binding->property->getName();

            if ($onlyFields !== [] && !in_array($name, $onlyFields, true)) {
                continue;
            }

            $values[$name] = $binding->property->getValue($entity);
        }

        return $values;
    }

    /**
     * @internal Bypasses the entity constructor via Doctrine's newInstance(); the returned
     *           object is a partial snapshot copy and MUST NOT be persisted via the EntityManager.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     * @param array<string, mixed> $row
     *
     * @return T
     */
    private function hydrate(string $className, array $row): object
    {
        $classMetadata = $this->entityManager->getClassMetadata($className);

        /** @var T $entity */
        $entity = $classMetadata->newInstance();

        $idField = $classMetadata->getSingleIdentifierFieldName();
        $idType = Type::getType($classMetadata->getFieldMapping($idField)->type);
        $classMetadata->getReflectionProperty($idField)->setValue(
            $entity,
            $idType->convertToPHPValue($row['entity_id'], $this->entityManager->getConnection()->getDatabasePlatform()),
        );

        $this->applyRowOntoEntity($className, $entity, $row);

        return $entity;
    }

    /**
     * @param class-string $className
     * @param array<string, mixed> $row
     * @param list<string> $onlyFields
     */
    private function applyRowOntoEntity(string $className, object $entity, array $row, array $onlyFields = []): void
    {
        $classMetadata = $this->entityManager->getClassMetadata($className);
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();
        $metadata = $this->metadataFactory->getMetadataFor($className);

        foreach ($metadata->bindings as $binding) {
            $name = $binding->property->getName();

            if ($onlyFields !== [] && !in_array($name, $onlyFields, true)) {
                continue;
            }

            if ($classMetadata->hasField($name)) {
                $type = Type::getType($classMetadata->getFieldMapping($name)->type);
                $columnName = $classMetadata->getColumnName($name);
                $binding->property->setValue($entity, $type->convertToPHPValue($row[$columnName] ?? null, $platform));

                continue;
            }

            // Embeddables: restore each flat "<prop>.<sub>" individually;
            // Doctrine's setFieldValue rebuilds the value object.
            if (isset($classMetadata->embeddedClasses[$name])) {
                foreach ($classMetadata->getFieldNames() as $flat) {
                    if (!str_starts_with($flat, $name . '.')) {
                        continue;
                    }

                    $columnName = $classMetadata->getColumnName($flat);
                    $type = Type::getType($classMetadata->getFieldMapping($flat)->type);
                    $classMetadata->setFieldValue($entity, $flat, $type->convertToPHPValue($row[$columnName] ?? null, $platform));
                }

                continue;
            }

            if (!$classMetadata->hasAssociation($name)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($name);

            if ($classMetadata->isSingleValuedAssociation($name)) {
                $targetId = $row[$name . '_id'] ?? null;
                // Route through findOneBy so the existence check actually runs a SELECT;
                // EntityManager::find returns an uninitialized lazy ghost for missing ids
                // under PHP 8.4+ native lazy objects, which would silence the warning below.
                $related = $targetId !== null
                    ? $this->entityManager->getRepository($assoc->targetEntity)->findOneBy([
                        $this->entityManager->getClassMetadata($assoc->targetEntity)->getSingleIdentifierFieldName() => $targetId,
                    ])
                    : null;

                if ($targetId !== null && $related === null) {
                    $this->logger->warning(
                        'Versionable: historical {target} id {id} for {class}::${field} no longer resolves; field set to null.',
                        [
                            'class' => $className,
                            'field' => $name,
                            'target' => $assoc->targetEntity,
                            'id' => $targetId,
                        ],
                    );
                }

                $binding->property->setValue($entity, $related);

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($name)) {
                $joinTable = $this->getVersionTable($className) . '_' . $name;
                $joinRows = $this->entityManager->getConnection()->createQueryBuilder()
                    ->select('target_id')
                    ->from($joinTable)
                    ->where('version_id = :version_id')
                    ->orderBy('position', 'ASC')
                    ->setParameter('version_id', $row['id'])
                    ->fetchAllAssociative();

                $collection = $binding->property->getValue($entity);

                if (!$collection instanceof Collection) {
                    $collection = new ArrayCollection();
                    $binding->property->setValue($entity, $collection);
                }

                // Doctrine PersistentCollection::clear() dissociates elements; for a OneToMany
                // without orphanRemoval the previously-attached children remain in the database.
                $collection->clear();

                $targetIdField = $this->entityManager->getClassMetadata($assoc->targetEntity)->getSingleIdentifierFieldName();
                $targetRepository = $this->entityManager->getRepository($assoc->targetEntity);

                foreach ($joinRows as $joinRow) {
                    $related = $targetRepository->findOneBy([$targetIdField => $joinRow['target_id']]);

                    if ($related === null) {
                        $this->logger->warning(
                            'Versionable: historical {target} id {id} for {class}::${field} collection no longer resolves; element omitted.',
                            [
                                'class' => $className,
                                'field' => $name,
                                'target' => $assoc->targetEntity,
                                'id' => $joinRow['target_id'],
                            ],
                        );

                        continue;
                    }

                    $collection->add($related);
                }
            }
        }
    }

    public function diff(string $className, int|string $entityId, int $fromVersion, int $toVersion): ?VersionDiff
    {
        $before = $this->findByVersion($className, $entityId, $fromVersion);
        $after = $this->findByVersion($className, $entityId, $toVersion);

        if ($before === null || $after === null) {
            return null;
        }

        $classMetadata = $this->entityManager->getClassMetadata($className);
        $metadata = $this->metadataFactory->getMetadataFor($className);
        $changes = [];

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();
            $beforeValue = $binding->property->getValue($before);
            $afterValue = $binding->property->getValue($after);

            if ($classMetadata->isCollectionValuedAssociation($fieldName)) {
                $beforeIds = $this->collectionIds($beforeValue, $classMetadata, $fieldName);
                $afterIds = $this->collectionIds($afterValue, $classMetadata, $fieldName);

                if ($beforeIds !== $afterIds) {
                    $changes[$fieldName] = ['before' => $beforeIds, 'after' => $afterIds];
                }

                continue;
            }

            if ($beforeValue !== $afterValue) {
                $changes[$fieldName] = ['before' => $beforeValue, 'after' => $afterValue];
            }
        }

        return new VersionDiff($fromVersion, $toVersion, $changes);
    }

    public function prune(string $className, \DateTimeInterface $olderThan, int $keepLast = 1): int
    {
        if ($keepLast < 0) {
            throw new \InvalidArgumentException('keepLast must be >= 0');
        }

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $cutoff = Type::getType(Types::DATETIMETZ_IMMUTABLE)
            ->convertToDatabaseValue(\DateTimeImmutable::createFromInterface($olderThan), $platform);

        $versionTable = $this->getVersionTable($className);

        $entityIds = $connection->createQueryBuilder()
            ->select('DISTINCT entity_id')
            ->from($versionTable)
            ->where('created_at < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->fetchFirstColumn();

        $deleted = 0;

        foreach ($entityIds as $entityId) {
            $keepers = $connection->createQueryBuilder()
                ->select('id')
                ->from($versionTable)
                ->where('entity_id = :entity_id')
                ->orderBy('version', 'DESC')
                ->setMaxResults($keepLast)
                ->setParameter('entity_id', $entityId)
                ->fetchFirstColumn();

            $queryBuilder = $connection->createQueryBuilder()
                ->delete($versionTable)
                ->where('entity_id = :entity_id')
                ->andWhere('created_at < :cutoff')
                ->setParameter('entity_id', $entityId)
                ->setParameter('cutoff', $cutoff);

            if ($keepers !== []) {
                $queryBuilder
                    ->andWhere('id NOT IN (:keepers)')
                    ->setParameter('keepers', $keepers, ArrayParameterType::INTEGER);
            }

            $deleted += (int) $queryBuilder->executeStatement();
        }

        return $deleted;
    }

    /**
     * @return list<int|string|null>
     */
    private function collectionIds(mixed $collection, ClassMetadata $classMetadata, string $fieldName): array
    {
        if (!$collection instanceof Collection) {
            return [];
        }

        $assoc = $classMetadata->getAssociationMapping($fieldName);
        $targetMetadata = $this->entityManager->getClassMetadata($assoc->targetEntity);
        $idField = $targetMetadata->getSingleIdentifierFieldName();
        $idProperty = $targetMetadata->getReflectionProperty($idField);

        $ids = [];

        foreach ($collection as $element) {
            if (!is_object($element)) {
                continue;
            }

            $value = $idProperty->getValue($element);
            $ids[] = is_scalar($value) || $value === null ? $value : (string) $value;
        }

        sort($ids);

        return $ids;
    }

    /**
     * @param class-string $className
     */
    private function newQuery(string $className): QueryBuilder
    {
        return $this->entityManager->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->getVersionTable($className));
    }

    /**
     * @param class-string $className
     */
    private function getVersionTable(string $className): string
    {
        return VersionableMetadataFactory::versionTableName(
            $this->entityManager->getClassMetadata($className)->getTableName(),
        );
    }
}
