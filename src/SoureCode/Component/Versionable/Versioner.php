<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\Internal\VersionRowApplier;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Façade over the version store. Internally delegates to {@see VersionReader}
 * (history queries + diff), {@see VersionApplier} (apply-onto-live-entity),
 * and {@see VersionPruner} (history purge). Host applications that only
 * need one half of the API may inject the sub-services directly.
 */
final class Versioner implements VersionerInterface
{
    private readonly VersionReader $reader;
    private readonly VersionApplier $applier;
    private readonly VersionPruner $pruner;

    public function __construct(
        EntityManagerInterface $entityManager,
        VersionableMetadataFactory $metadataFactory,
        LoggerInterface $logger = new NullLogger(),
    ) {
        $rowApplier = new VersionRowApplier($entityManager, $metadataFactory, $logger);

        $this->reader = new VersionReader($entityManager, $metadataFactory, $rowApplier);
        $this->applier = new VersionApplier($entityManager, $metadataFactory, $rowApplier, $this->reader);
        $this->pruner = new VersionPruner($entityManager, $metadataFactory);
    }

    public function findHistory(string $className, int|string $entityId): array
    {
        return $this->reader->findHistory($className, $entityId);
    }

    public function findByVersion(string $className, int|string $entityId, int $version): ?object
    {
        return $this->reader->findByVersion($className, $entityId, $version);
    }

    public function findLatestVersion(string $className, int|string $entityId): ?object
    {
        return $this->reader->findLatestVersion($className, $entityId);
    }

    public function applyVersion(
        object $entity,
        int $version,
        array $onlyFields = [],
        bool $cascade = false,
    ): AppliedVersion {
        return $this->applier->applyVersion($entity, $version, $onlyFields, $cascade);
    }

    public function diff(string $className, int|string $entityId, int $fromVersion, int $toVersion): ?VersionDiff
    {
        return $this->reader->diff($className, $entityId, $fromVersion, $toVersion);
    }

    public function prune(string $className, \DateTimeInterface $olderThan, int $keepLast = 1): int
    {
        return $this->pruner->prune($className, $olderThan, $keepLast);
    }
}
