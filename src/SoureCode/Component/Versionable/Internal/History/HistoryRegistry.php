<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Internal\ColumnNamer;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * @internal Process-wide lookup table that the runtime-generated entity
 *           proxies call into. Holds the EntityManager and the historical
 *           hydration helpers so the generated code can stay tiny — every
 *           `get<Field>History()` method delegates straight here.
 *
 *           A static registry is the only way the file-based proxy classes
 *           can reach the live container without becoming entities
 *           themselves. The application binds an instance at boot via
 *           {@see bind()}.
 */
final class HistoryRegistry
{
    private static ?self $instance = null;

    private function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly HistoryHydrator $hydrator,
    ) {
    }

    public static function bind(
        EntityManagerInterface $entityManager,
        VersionableMetadataFactory $metadataFactory,
        HistoryHydrator $hydrator,
    ): void {
        self::$instance = new self($entityManager, $metadataFactory, $hydrator);
    }

    public static function unbind(): void
    {
        self::$instance = null;
    }

    /**
     * Resolve the pinned `*History` for a single-valued versioned relation
     * on the given live entity. Reads the `<field>_version` column from the
     * live row and queries the corresponding snapshot row.
     */
    public static function historyFor(object $entity, string $fieldName): ?object
    {
        $registry = self::$instance;

        if ($registry === null) {
            throw new \RuntimeException('HistoryRegistry has not been bound — the application must call HistoryRegistry::bind() at boot.');
        }

        $entityManager = $registry->entityManager;
        $classMetadata = $entityManager->getClassMetadata($entity::class);

        $idField = $classMetadata->getSingleIdentifierFieldName();
        $entityId = $classMetadata->getReflectionProperty($idField)->getValue($entity);

        if ($entityId === null) {
            return null;
        }

        $assoc = $classMetadata->getAssociationMapping($fieldName);
        $targetClass = $assoc->targetEntity;

        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $idType = Type::getType($classMetadata->getFieldMapping($idField)->type);
        $idDbValue = $idType->convertToDatabaseValue($entityId, $platform);

        $pinColumn = ColumnNamer::singleAssociationVersion($assoc);
        $fkColumn = ColumnNamer::singleAssociationId($assoc);

        $row = $connection->createQueryBuilder()
            ->select($pinColumn, $fkColumn)
            ->from($classMetadata->getTableName())
            ->where($classMetadata->getColumnName($idField) . ' = :id')
            ->setParameter('id', $idDbValue)
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        $targetId = $row[$fkColumn] ?? null;
        $targetVersion = $row[$pinColumn] ?? null;

        if ($targetId === null || $targetVersion === null) {
            return null;
        }

        $targetVersionTable = $registry->metadataFactory->versionTableName(
            $entityManager->getClassMetadata($targetClass)->getTableName(),
        );

        $snapshot = $connection->createQueryBuilder()
            ->select('*')
            ->from($targetVersionTable)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere(VersionTableColumns::VERSION . ' = :version')
            ->setParameter('entity_id', $targetId)
            ->setParameter('version', (int) $targetVersion)
            ->fetchAssociative();

        if ($snapshot === false) {
            return null;
        }

        return $registry->hydrator->hydrate($targetClass, $snapshot);
    }
}
