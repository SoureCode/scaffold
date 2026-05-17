<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Repository;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Mixed into a Doctrine EntityRepository to expose version history queries
 * for the host entity.
 */
trait VersionableRepositoryTrait
{
    private static ?VersionableMetadataFactory $versionableMetadataFactory = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function findHistory(int|string $entityId): array
    {
        return $this->fetchVersionRows(
            \sprintf('SELECT * FROM %s WHERE entity_id = ? ORDER BY version ASC', $this->getVersionTable()),
            [$entityId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByVersion(int|string $entityId, int $version): ?array
    {
        $rows = $this->fetchVersionRows(
            \sprintf('SELECT * FROM %s WHERE entity_id = ? AND version = ?', $this->getVersionTable()),
            [$entityId, $version],
        );

        return $rows[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLatestVersion(int|string $entityId): ?array
    {
        $rows = $this->fetchVersionRows(
            \sprintf('SELECT * FROM %s WHERE entity_id = ? ORDER BY version DESC LIMIT 1', $this->getVersionTable()),
            [$entityId],
        );

        return $rows[0] ?? null;
    }

    /**
     * Mutates $entity in place with values from the given historical version.
     * The caller is responsible for flushing; that flush will write a new
     * version row reflecting the revert.
     *
     * Related entities are re-attached at their current state — historical
     * versions of related entities are not restored.
     *
     * @throws \RuntimeException when the version does not exist
     */
    public function applyVersion(object $entity, int $version): void
    {
        $entityManager = $this->getEntityManager();
        $className = $this->getClassName();
        $classMetadata = $entityManager->getClassMetadata($className);
        $platform = $entityManager->getConnection()->getDatabasePlatform();

        $idField = $classMetadata->getSingleIdentifierFieldName();
        $entityId = $classMetadata->getReflectionProperty($idField)->getValue($entity);

        if ($entityId === null) {
            throw new \RuntimeException('Cannot apply version to an entity without an identifier.');
        }

        $row = $this->findByVersion($entityId, $version);

        if ($row === null) {
            throw new \RuntimeException(\sprintf('Version %d for %s#%s not found.', $version, $className, (string) $entityId));
        }

        $metadata = self::versionableMetadataFactory()->getMetadataFor($className);

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
                $related = $targetId !== null ? $entityManager->find($assoc->targetEntity, $targetId) : null;
                $binding->property->setValue($entity, $related);

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($name)) {
                $joinTable = $this->getVersionTable() . '_' . $name;
                $joinRows = $entityManager->getConnection()->fetchAllAssociative(
                    \sprintf('SELECT target_id FROM %s WHERE version_id = ?', $joinTable),
                    [$row['id']],
                );

                $collection = $binding->property->getValue($entity);

                if (!$collection instanceof Collection) {
                    continue;
                }

                $collection->clear();

                foreach ($joinRows as $joinRow) {
                    $related = $entityManager->find($assoc->targetEntity, $joinRow['target_id']);

                    if ($related !== null) {
                        $collection->add($related);
                    }
                }
            }
        }
    }

    abstract protected function getEntityManager(): EntityManagerInterface;

    abstract protected function getClassName(): string;

    private function getVersionTable(): string
    {
        return $this->getEntityManager()->getClassMetadata($this->getClassName())->getTableName() . '_version';
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function fetchVersionRows(string $sql, array $parameters): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $parameters);

        return $rows;
    }

    private static function versionableMetadataFactory(): VersionableMetadataFactory
    {
        return self::$versionableMetadataFactory ??= new VersionableMetadataFactory();
    }
}
