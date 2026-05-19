<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
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
     */
    public function applyVersion(object $entity, int $version): void
    {
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

        $this->applyRowOntoEntity($className, $entity, $row);
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
     */
    private function applyRowOntoEntity(string $className, object $entity, array $row): void
    {
        $classMetadata = $this->entityManager->getClassMetadata($className);
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();
        $metadata = $this->metadataFactory->getMetadataFor($className);

        foreach ($metadata->bindings as $binding) {
            $name = $binding->property->getName();

            if ($classMetadata->hasField($name)) {
                $type = Type::getType($classMetadata->getFieldMapping($name)->type);
                $columnName = $classMetadata->getColumnName($name);
                $binding->property->setValue($entity, $type->convertToPHPValue($row[$columnName] ?? null, $platform));

                continue;
            }

            if (!$classMetadata->hasAssociation($name)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($name);

            if ($classMetadata->isSingleValuedAssociation($name)) {
                $targetId = $row[$name . '_id'] ?? null;
                $related = $targetId !== null ? $this->entityManager->find($assoc->targetEntity, $targetId) : null;

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

                foreach ($joinRows as $joinRow) {
                    $related = $this->entityManager->find($assoc->targetEntity, $joinRow['target_id']);

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
