<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Internal\VersionRowApplier;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Read-side of the version store. Queries the version table for historical
 * snapshots and hydrates them into detached entity instances, and compares
 * two versions to produce a per-field diff.
 *
 * Hydrated entities are detached copies — they are constructed via
 * `ClassMetadata::newInstance()` and must NOT be persisted via the
 * EntityManager.
 */
final class VersionReader
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly VersionRowApplier $rowApplier,
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
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->orderBy(VersionTableColumns::VERSION, 'ASC')
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
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere(VersionTableColumns::VERSION . ' = :version')
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
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->orderBy(VersionTableColumns::VERSION, 'DESC')
            ->setMaxResults(1)
            ->setParameter('entity_id', $entityId)
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($className, $row);
    }

    /**
     * @param class-string $className
     *
     * @return array<string, mixed>|null
     */
    public function fetchVersionRow(string $className, int|string $entityId, int $version): ?array
    {
        $row = $this->newQuery($className)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere(VersionTableColumns::VERSION . ' = :version')
            ->setParameter('entity_id', $entityId)
            ->setParameter('version', $version)
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    /**
     * @param class-string $className
     */
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
            $idType->convertToPHPValue($row[VersionTableColumns::ENTITY_ID], $this->entityManager->getConnection()->getDatabasePlatform()),
        );

        $this->rowApplier->applyRow($className, $entity, $row);

        return $entity;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     *
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
            ->from($this->metadataFactory->versionTableName(
                $this->entityManager->getClassMetadata($className)->getTableName(),
            ));
    }
}
