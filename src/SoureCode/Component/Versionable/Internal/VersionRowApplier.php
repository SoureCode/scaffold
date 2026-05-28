<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * @internal Shared row → entity restorer used by both the read path
 *           (hydrating a snapshot for inspection) and the write path
 *           (applying a version onto a live entity). Tests should
 *           reach for the public Versioner / VersionReader / VersionApplier
 *           façades instead.
 *
 * Restores each versioned property in turn: scalar fields and embeddables
 * via Doctrine type conversion, single associations via `findOneBy`,
 * collections by replacing element references through the join table.
 */
final class VersionRowApplier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param class-string $className
     * @param array<string, mixed> $row
     * @param list<string> $onlyFields
     */
    public function applyRow(string $className, object $entity, array $row, array $onlyFields = []): void
    {
        $classMetadata = $this->entityManager->getClassMetadata($className);
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();
        $metadata = $this->metadataFactory->getMetadataFor($className);

        foreach ($metadata->bindings as $binding) {
            $name = $binding->property->getName();

            if ($onlyFields !== [] && !in_array($name, $onlyFields, true)) {
                continue;
            }

            // Embeddables: restore each flat "<prop>.<sub>" individually;
            // Doctrine's setFieldValue rebuilds the value object. Check
            // embeddable BEFORE hasField — ClassMetadata::hasField() returns
            // true for embedded parents too.
            if (isset($classMetadata->embeddedClasses[$name])) {
                foreach ($classMetadata->getFieldNames() as $flat) {
                    if (!str_starts_with($flat, $name . '.')) {
                        continue;
                    }

                    $mapping = $classMetadata->getFieldMapping($flat);
                    $columnName = $classMetadata->getColumnName($flat);
                    $value = Type::getType($mapping->type)->convertToPHPValue($row[$columnName] ?? null, $platform);

                    if (($mapping->enumType ?? null) !== null && $value !== null) {
                        $value = $mapping->enumType::from($value);
                    }

                    $classMetadata->setFieldValue($entity, $flat, $value);
                }

                continue;
            }

            if (isset($classMetadata->fieldMappings[$name])) {
                $mapping = $classMetadata->getFieldMapping($name);
                $columnName = $classMetadata->getColumnName($name);
                $value = Type::getType($mapping->type)->convertToPHPValue($row[$columnName] ?? null, $platform);

                if (($mapping->enumType ?? null) !== null && $value !== null) {
                    $value = $mapping->enumType::from($value);
                }

                $binding->property->setValue($entity, $value);

                continue;
            }

            if (!$classMetadata->hasAssociation($name)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($name);

            if ($classMetadata->isSingleValuedAssociation($name)) {
                if (!$assoc->isOwningSide()) {
                    continue;
                }

                $targetId = $row[ColumnNamer::singleAssociationId($assoc)] ?? null;
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
                $joinTable = $this->metadataFactory->versionTableName($classMetadata->getTableName()) . '_' . $name;
                $joinRows = $this->entityManager->getConnection()->createQueryBuilder()
                    ->select(VersionTableColumns::JOIN_TARGET_ID)
                    ->from($joinTable)
                    ->where(VersionTableColumns::JOIN_VERSION_ID . ' = :version_id')
                    ->orderBy(VersionTableColumns::JOIN_POSITION, 'ASC')
                    ->setParameter('version_id', $row[VersionTableColumns::ID])
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
                    $related = $targetRepository->findOneBy([$targetIdField => $joinRow[VersionTableColumns::JOIN_TARGET_ID]]);

                    if ($related === null) {
                        $this->logger->warning(
                            'Versionable: historical {target} id {id} for {class}::${field} collection no longer resolves; element omitted.',
                            [
                                'class' => $className,
                                'field' => $name,
                                'target' => $assoc->targetEntity,
                                'id' => $joinRow[VersionTableColumns::JOIN_TARGET_ID],
                            ],
                        );

                        continue;
                    }

                    $collection->add($related);
                }
            }
        }
    }
}
