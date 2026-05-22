<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

final class Remover implements RemoverInterface
{
    /**
     * @param iterable<DeletionMarkerProviderInterface> $markerProviders
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly TimestampableMetadataFactory $timestampableMetadata,
        private readonly iterable $markerProviders = [],
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Note: `$soft = false` with `$flush = false` is legal but a no-op until the
     * next flush — `EntityManager::remove()` only schedules the delete.
     *
     * @template T of object
     *
     * @param T $entity
     */
    public function remove(object $entity, bool $soft = true, bool $flush = true): void
    {
        if ($soft) {
            $this->fillDeletionMarkers($entity);
        } else {
            $this->entityManager->remove($entity);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    public function restore(object $entity, bool $flush = true): void
    {
        $this->clearDeletionMarkers($entity);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function batchRemove(iterable $entities, bool $soft = true, bool $flush = true): int
    {
        $count = 0;

        foreach ($entities as $entity) {
            if ($soft) {
                $this->fillDeletionMarkers($entity);
            } else {
                $this->entityManager->remove($entity);
            }
            $count++;
        }

        if ($count > 0 && $flush) {
            $this->entityManager->flush();
        }

        return $count;
    }

    public function purge(string $entityClass, \DateTimeInterface $olderThan, bool $flush = true): int
    {
        $deletedAtBindings = $this->timestampableMetadata
            ->getMetadataFor($entityClass)
            ->getDeletedBindings();

        if ($deletedAtBindings === []) {
            throw new \LogicException(\sprintf(
                'Entity "%s" has no #[DeletedAt] marker — cannot purge.',
                $entityClass,
            ));
        }

        $classMetadata = $this->entityManager->getClassMetadata($entityClass);
        $deletedColumn = $this->resolveDeletedAtColumn($classMetadata, $deletedAtBindings[0]->getProperty()->getName());

        if ($deletedColumn === null) {
            throw new \LogicException(\sprintf(
                'Entity "%s" #[DeletedAt] property is not a registered Doctrine field — cannot purge.',
                $entityClass,
            ));
        }

        $queryBuilder = $this->entityManager->getConnection()->createQueryBuilder()
            ->delete($classMetadata->getTableName())
            ->where(\sprintf('%s IS NOT NULL', $deletedColumn))
            ->andWhere(\sprintf('%s < :cutoff', $deletedColumn))
            ->setParameter(
                'cutoff',
                \DateTimeImmutable::createFromInterface($olderThan),
                Types::DATETIME_IMMUTABLE,
            );

        $affected = (int) $queryBuilder->executeStatement();

        if ($flush) {
            $this->entityManager->flush();
        }

        return $affected;
    }

    /**
     * Single source of truth shared with `SoftDeleteFilter`: resolve the
     * SQL column for a `#[DeletedAt]` property strictly through Doctrine's
     * field mapping. Absence of a mapping means the property is embedded
     * or otherwise not reachable as a flat column — return null so the
     * caller decides what to do.
     */
    private function resolveDeletedAtColumn(ClassMetadata $classMetadata, string $propertyName): ?string
    {
        if (!isset($classMetadata->fieldMappings[$propertyName])) {
            return null;
        }

        return $classMetadata->getColumnName($propertyName);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    private function fillDeletionMarkers(object $entity): void
    {
        $deletedAtBindings = $this->timestampableMetadata
            ->getMetadataFor($entity::class)
            ->getDeletedBindings();

        if ($deletedAtBindings === []) {
            throw new \LogicException(\sprintf(
                'Entity "%s" has no #[DeletedAt] marker — cannot soft-remove.',
                $entity::class,
            ));
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($deletedAtBindings as $binding) {
            $binding->getProperty()->setValue($entity, $now);
        }

        foreach ($this->markerProviders as $provider) {
            $provider->fillDeletionMarkers($entity);
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    private function clearDeletionMarkers(object $entity): void
    {
        $deletedAtBindings = $this->timestampableMetadata
            ->getMetadataFor($entity::class)
            ->getDeletedBindings();

        if ($deletedAtBindings === []) {
            throw new \LogicException(\sprintf(
                'Entity "%s" has no #[DeletedAt] marker — cannot restore.',
                $entity::class,
            ));
        }

        $alreadyLive = true;

        foreach ($deletedAtBindings as $binding) {
            if ($binding->getProperty()->getValue($entity) !== null) {
                $alreadyLive = false;
            }

            $binding->getProperty()->setValue($entity, null);
        }

        if ($alreadyLive) {
            $this->logger->warning(
                'Removable: restore() called on {class} but deletedAt was already null — no-op.',
                ['class' => $entity::class],
            );
        }

        foreach ($this->markerProviders as $provider) {
            $provider->clearDeletionMarkers($entity);
        }
    }
}
