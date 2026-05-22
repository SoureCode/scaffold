<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Hard-deletes version rows older than a cutoff, with a per-entity "keep
 * at least N" floor so the most recent history is always preserved.
 *
 * Separated from the read and apply paths because pruning is the only
 * destructive operation in the version store and many host applications
 * gate it behind a scheduled command or a messenger handler — they need
 * to inject the pruner without also exposing the rest of the API.
 */
final class VersionPruner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
    ) {
    }

    /**
     * @param class-string $className
     */
    public function prune(string $className, \DateTimeInterface $olderThan, int $keepLast = 1): int
    {
        if ($keepLast < 0) {
            throw new \InvalidArgumentException('keepLast must be >= 0');
        }

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $cutoff = Type::getType(Types::DATETIMETZ_IMMUTABLE)
            ->convertToDatabaseValue(\DateTimeImmutable::createFromInterface($olderThan), $platform);

        $versionTable = $this->metadataFactory->versionTableName(
            $this->entityManager->getClassMetadata($className)->getTableName(),
        );

        $entityIds = $connection->createQueryBuilder()
            ->select(\sprintf('DISTINCT %s', VersionTableColumns::ENTITY_ID))
            ->from($versionTable)
            ->where(VersionTableColumns::CREATED_AT . ' < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->fetchFirstColumn();

        $deleted = 0;

        foreach ($entityIds as $entityId) {
            $keepers = $connection->createQueryBuilder()
                ->select(VersionTableColumns::ID)
                ->from($versionTable)
                ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
                ->orderBy(VersionTableColumns::VERSION, 'DESC')
                ->setMaxResults($keepLast)
                ->setParameter('entity_id', $entityId)
                ->fetchFirstColumn();

            $queryBuilder = $connection->createQueryBuilder()
                ->delete($versionTable)
                ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
                ->andWhere(VersionTableColumns::CREATED_AT . ' < :cutoff')
                ->setParameter('entity_id', $entityId)
                ->setParameter('cutoff', $cutoff);

            if ($keepers !== []) {
                $queryBuilder
                    ->andWhere(VersionTableColumns::ID . ' NOT IN (:keepers)')
                    ->setParameter('keepers', $keepers, ArrayParameterType::INTEGER);
            }

            $deleted += (int) $queryBuilder->executeStatement();
        }

        return $deleted;
    }
}
